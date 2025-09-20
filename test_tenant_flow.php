<?php
/**
 * Test Script for Tenant-Less Login Flow
 * Tests the complete authentication and tenant management system
 */

// Test configuration
$baseUrl = 'http://localhost/Nexiosolution/collabora';
$cookieFile = tempnam(sys_get_temp_dir(), 'test_cookies_');

// Test users
$testUsers = [
    'admin' => [
        'email' => 'asamodeo@fortibyte.it',
        'password' => 'Ricord@1991',
        'expected_role' => 'admin',
        'can_switch' => true,
        'multi_tenant' => true
    ],
    // Add more test users as needed
];

// Colors for terminal output
$colors = [
    'success' => "\033[32m", // Green
    'error' => "\033[31m",   // Red
    'info' => "\033[36m",    // Cyan
    'warning' => "\033[33m", // Yellow
    'reset' => "\033[0m"
];

function printTest($message, $type = 'info') {
    global $colors;
    echo $colors[$type] . $message . $colors['reset'] . PHP_EOL;
}

function makeRequest($url, $method = 'GET', $data = null, $useCookies = true) {
    global $cookieFile;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if ($useCookies) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?? $response
    ];
}

function testLogin($user) {
    global $baseUrl;

    printTest("\n=== Testing Login for {$user['email']} ===", 'info');

    $response = makeRequest(
        "$baseUrl/api/auth_simple.php",
        'POST',
        [
            'action' => 'login',
            'email' => $user['email'],
            'password' => $user['password']
        ]
    );

    if ($response['code'] === 200 && $response['body']['success']) {
        printTest("✓ Login successful", 'success');

        // Check user role
        $actualRole = $response['body']['user']['role'] ?? '';
        if ($actualRole === $user['expected_role']) {
            printTest("✓ Role correct: $actualRole", 'success');
        } else {
            printTest("✗ Role mismatch. Expected: {$user['expected_role']}, Got: $actualRole", 'error');
        }

        // Check tenants
        $tenants = $response['body']['tenants'] ?? [];
        $tenantCount = count($tenants);
        printTest("  Found $tenantCount tenant(s)", 'info');

        foreach ($tenants as $tenant) {
            printTest("  - {$tenant['name']} (ID: {$tenant['id']})", 'info');
        }

        // Check auto-selection
        if (isset($response['body']['auto_selected'])) {
            $autoSelected = $response['body']['auto_selected'] ? 'Yes' : 'No';
            printTest("  Auto-selected: $autoSelected", 'info');
        }

        // Check if needs tenant selection
        if (isset($response['body']['needs_tenant_selection'])) {
            $needsSelection = $response['body']['needs_tenant_selection'] ? 'Yes' : 'No';
            printTest("  Needs tenant selection: $needsSelection", 'info');
        }

        return $response['body'];
    } else {
        printTest("✗ Login failed: " . json_encode($response['body']), 'error');
        return null;
    }
}

function testGetTenants() {
    global $baseUrl;

    printTest("\n=== Testing Get Tenants ===", 'info');

    // Test via auth_simple.php
    $response1 = makeRequest(
        "$baseUrl/api/auth_simple.php",
        'POST',
        ['action' => 'get_tenants']
    );

    if ($response1['code'] === 200 && $response1['body']['success']) {
        printTest("✓ Get tenants via auth_simple.php successful", 'success');
        $tenants = $response1['body']['tenants'] ?? [];
        printTest("  Found " . count($tenants) . " tenant(s)", 'info');
    } else {
        printTest("✗ Get tenants via auth_simple.php failed", 'error');
    }

    // Test via dedicated endpoint
    $response2 = makeRequest("$baseUrl/api/user-tenants.php");

    if ($response2['code'] === 200 && $response2['body']['success']) {
        printTest("✓ Get tenants via user-tenants.php successful", 'success');
        $data = $response2['body']['data'] ?? [];
        printTest("  Total tenants: " . ($data['total'] ?? 0), 'info');
        printTest("  Can switch: " . ($data['user']['can_switch'] ? 'Yes' : 'No'), 'info');
    } else {
        printTest("✗ Get tenants via user-tenants.php failed", 'error');
    }

    return $response1['body']['tenants'] ?? [];
}

function testSwitchTenant($tenantId) {
    global $baseUrl;

    printTest("\n=== Testing Switch to Tenant ID: $tenantId ===", 'info');

    $response = makeRequest(
        "$baseUrl/api/switch-tenant.php",
        'POST',
        ['tenant_id' => $tenantId]
    );

    if ($response['code'] === 200 && $response['body']['success']) {
        printTest("✓ Tenant switch successful", 'success');
        $newTenant = $response['body']['data']['current_tenant'] ?? [];
        printTest("  Now on tenant: {$newTenant['name']} (ID: {$newTenant['id']})", 'info');
        return true;
    } else {
        $error = $response['body']['error']['message'] ?? 'Unknown error';
        printTest("✗ Tenant switch failed: $error", 'error');
        return false;
    }
}

function testSessionCheck() {
    global $baseUrl;

    printTest("\n=== Testing Session Check ===", 'info');

    $response = makeRequest(
        "$baseUrl/api/auth_simple.php",
        'POST',
        ['action' => 'check']
    );

    if ($response['code'] === 200 && $response['body']['authenticated']) {
        printTest("✓ Session is active", 'success');
        $user = $response['body']['user'] ?? [];
        printTest("  User: {$user['email']} (Role: {$user['role']})", 'info');
        return true;
    } else {
        printTest("✗ Session not active", 'error');
        return false;
    }
}

function testLogout() {
    global $baseUrl;

    printTest("\n=== Testing Logout ===", 'info');

    $response = makeRequest(
        "$baseUrl/api/auth_simple.php",
        'POST',
        ['action' => 'logout']
    );

    if ($response['code'] === 200 && $response['body']['success']) {
        printTest("✓ Logout successful", 'success');
        return true;
    } else {
        printTest("✗ Logout failed", 'error');
        return false;
    }
}

// Main test execution
printTest("====================================", 'info');
printTest("Tenant-Less Login Flow Test Suite", 'info');
printTest("====================================", 'info');

foreach ($testUsers as $userType => $user) {
    printTest("\n>>> Testing $userType user <<<", 'warning');

    // 1. Test login
    $loginResult = testLogin($user);
    if (!$loginResult) {
        continue;
    }

    // 2. Test get tenants
    $tenants = testGetTenants();

    // 3. Test tenant switching (if user can switch)
    if ($user['can_switch'] && count($tenants) > 1) {
        // Try switching to second tenant
        $targetTenant = $tenants[1]['id'] ?? null;
        if ($targetTenant) {
            testSwitchTenant($targetTenant);

            // Verify switch worked
            testSessionCheck();

            // Switch back to first tenant
            testSwitchTenant($tenants[0]['id']);
        }
    }

    // 4. Test session check
    testSessionCheck();

    // 5. Test logout
    testLogout();
}

// Cleanup
unlink($cookieFile);

printTest("\n====================================", 'info');
printTest("Test Suite Complete", 'info');
printTest("====================================", 'info');