<?php
/**
 * API Functionality Test for Nexiosolution Collabora
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "\n" . str_repeat("=", 60) . "\n";
echo "NEXIOSOLUTION COLLABORA - API TEST\n";
echo str_repeat("=", 60) . "\n\n";

$baseUrl = 'http://localhost/Nexiosolution/collabora';
$results = [];

// Test 1: Login
echo "1. Testing Login API...\n";
$ch = curl_init($baseUrl . '/api/auth_simple.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'action' => 'login',
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'Ricord@1991'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "   ✓ Login successful\n";
        $results['login'] = true;
    } else {
        echo "   ✗ Login failed: " . ($data['message'] ?? 'Unknown error') . "\n";
        $results['login'] = false;
    }
} else {
    echo "   ✗ Login returned HTTP $httpCode\n";
    $results['login'] = false;
}

// Test other APIs only if login successful
if ($results['login']) {

    // Test 2: Calendars API
    echo "\n2. Testing Calendars API...\n";
    $ch = curl_init($baseUrl . '/api/calendars.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✓ Calendars API working (HTTP 200)\n";
        $results['calendars'] = true;
    } else {
        echo "   ✗ Calendars API returned HTTP $httpCode\n";
        if ($response) echo "   Response: " . substr($response, 0, 100) . "\n";
        $results['calendars'] = false;
    }

    // Test 3: Events API
    echo "\n3. Testing Events API...\n";
    $ch = curl_init($baseUrl . '/api/events.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✓ Events API working (HTTP 200)\n";
        $results['events'] = true;
    } else {
        echo "   ✗ Events API returned HTTP $httpCode\n";
        if ($response) echo "   Response: " . substr($response, 0, 100) . "\n";
        $results['events'] = false;
    }

    // Test 4: Task Lists API
    echo "\n4. Testing Task Lists API...\n";
    $ch = curl_init($baseUrl . '/api/task-lists.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✓ Task Lists API working (HTTP 200)\n";
        $results['task-lists'] = true;
    } else {
        echo "   ✗ Task Lists API returned HTTP $httpCode\n";
        if ($response) echo "   Response: " . substr($response, 0, 100) . "\n";
        $results['task-lists'] = false;
    }

    // Test 5: Channels API
    echo "\n5. Testing Channels API...\n";
    $ch = curl_init($baseUrl . '/api/channels.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✓ Channels API working (HTTP 200)\n";
        $results['channels'] = true;
    } else {
        echo "   ✗ Channels API returned HTTP $httpCode\n";
        if ($response) echo "   Response: " . substr($response, 0, 100) . "\n";
        $results['channels'] = false;
    }

    // Test 6: Messages API
    echo "\n6. Testing Messages API...\n";
    $ch = curl_init($baseUrl . '/api/messages.php?action=get_last_id');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✓ Messages API working (HTTP 200)\n";
        $results['messages'] = true;
    } else {
        echo "   ✗ Messages API returned HTTP $httpCode\n";
        if ($response) echo "   Response: " . substr($response, 0, 100) . "\n";
        $results['messages'] = false;
    }

    // Test 7: Users API
    echo "\n7. Testing Users API...\n";
    $ch = curl_init($baseUrl . '/api/users.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "   ✓ Users API working (HTTP 200)\n";
        $results['users'] = true;
    } else {
        echo "   ✗ Users API returned HTTP $httpCode\n";
        if ($response) echo "   Response: " . substr($response, 0, 100) . "\n";
        $results['users'] = false;
    }
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "TEST RESULTS SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

$total = count($results);
$passed = array_sum($results);
$percentage = $total > 0 ? round(($passed / $total) * 100) : 0;

echo "Tests passed: $passed/$total ($percentage%)\n\n";

foreach ($results as $test => $result) {
    echo sprintf("%-15s: %s\n", ucfirst($test), $result ? '✓ PASS' : '✗ FAIL');
}

if ($percentage === 100) {
    echo "\n✅ ALL APIS ARE WORKING CORRECTLY!\n";
    echo "\nThe system is ready for use.\n";
} else {
    echo "\n⚠️ SOME APIS NEED ATTENTION\n";
    echo "\nFailed APIs need to be debugged individually.\n";
    echo "Check the PHP error logs for more details.\n";
}

// Clean up
if (file_exists('cookies.txt')) {
    unlink('cookies.txt');
}

echo "\n";