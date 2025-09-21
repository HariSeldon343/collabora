<?php declare(strict_types=1);

/**
 * Comprehensive API Test Script
 * Tests all API endpoints for proper error handling
 */

// Test configuration
$baseUrl = 'http://localhost/Nexiosolution/collabora/api';
$testEmail = 'test@example.com';
$testPassword = 'test123';

// Color codes for output
$colors = [
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'reset' => "\033[0m"
];

function testAPI(string $endpoint, string $method = 'GET', array $data = [], array $headers = []): array {
    global $baseUrl;

    $url = $baseUrl . '/' . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    // Set headers
    $defaultHeaders = ['Content-Type: application/json'];
    $allHeaders = array_merge($defaultHeaders, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

    // Set data for POST/PUT
    if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    // Handle cookies for session
    $cookieFile = __DIR__ . '/test_cookies.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'json' => json_decode($body, true)
    ];
}

function printResult(string $test, bool $passed, string $details = ''): void {
    global $colors;

    $icon = $passed ? '✅' : '❌';
    $color = $passed ? $colors['green'] : $colors['red'];

    echo sprintf(
        "%s %s%s%s %s\n",
        $icon,
        $color,
        $test,
        $colors['reset'],
        $details
    );
}

echo "========================================\n";
echo "    API ENDPOINT TESTING SUITE         \n";
echo "========================================\n\n";

// Test results storage
$results = [];

// 1. Test authentication endpoints
echo $colors['blue'] . "Testing Authentication APIs..." . $colors['reset'] . "\n";

// Test login without credentials (should return 400)
$response = testAPI('auth_simple.php', 'POST', []);
$passed = $response['code'] === 400;
$results['auth_no_creds'] = $passed;
printResult(
    "POST /auth_simple.php (no credentials)",
    $passed,
    "HTTP " . $response['code'] . " - " . ($response['json']['message'] ?? 'No message')
);

// Test login with invalid credentials (should return 401)
$response = testAPI('auth_simple.php', 'POST', [
    'action' => 'login',
    'email' => 'invalid@example.com',
    'password' => 'wrongpass'
]);
$passed = $response['code'] === 401;
$results['auth_invalid'] = $passed;
printResult(
    "POST /auth_simple.php (invalid credentials)",
    $passed,
    "HTTP " . $response['code']
);

echo "\n";

// 2. Test protected endpoints without authentication
echo $colors['blue'] . "Testing Protected Endpoints (No Auth)..." . $colors['reset'] . "\n";

$protectedEndpoints = [
    'users.php' => 'GET',
    'calendars.php' => 'GET',
    'events.php' => 'GET',
    'task-lists.php' => 'GET',
    'channels.php' => 'GET',
    'messages.php' => 'GET',
    'chat-poll.php' => 'GET'
];

foreach ($protectedEndpoints as $endpoint => $method) {
    $response = testAPI($endpoint, $method);
    $passed = $response['code'] === 401;
    $results[$endpoint . '_noauth'] = $passed;

    $message = $response['json']['message'] ?? $response['json']['error'] ?? 'No message';
    printResult(
        "$method /$endpoint (no auth)",
        $passed,
        "HTTP " . $response['code'] . " - $message"
    );
}

echo "\n";

// 3. Test required parameters
echo $colors['blue'] . "Testing Required Parameters..." . $colors['reset'] . "\n";

// First, authenticate with admin
$response = testAPI('auth_simple.php', 'POST', [
    'action' => 'login',
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'Ricord@1991'
]);

if ($response['code'] === 200) {
    echo $colors['green'] . "✅ Authenticated as admin\n" . $colors['reset'];

    // Test messages without channel_id
    $response = testAPI('messages.php', 'GET');
    $passed = $response['code'] === 400 &&
              isset($response['json']['error']) &&
              $response['json']['error'] === 'missing_channel_id';
    $results['messages_no_channel'] = $passed;
    printResult(
        "GET /messages.php (no channel_id)",
        $passed,
        "HTTP " . $response['code']
    );

    // Test sending message without content
    $response = testAPI('messages.php', 'POST', ['channel_id' => 1]);
    $passed = $response['code'] === 400 &&
              isset($response['json']['error']) &&
              $response['json']['error'] === 'missing_content';
    $results['messages_no_content'] = $passed;
    printResult(
        "POST /messages.php (no content)",
        $passed,
        "HTTP " . $response['code']
    );

} else {
    echo $colors['red'] . "❌ Failed to authenticate for parameter tests\n" . $colors['reset'];
}

echo "\n";

// 4. Summary
echo $colors['blue'] . "========================================" . $colors['reset'] . "\n";
echo $colors['blue'] . "              TEST SUMMARY              " . $colors['reset'] . "\n";
echo $colors['blue'] . "========================================" . $colors['reset'] . "\n";

$totalTests = count($results);
$passedTests = array_sum($results);
$failedTests = $totalTests - $passedTests;

$summaryColor = $failedTests === 0 ? $colors['green'] : ($failedTests <= 2 ? $colors['yellow'] : $colors['red']);

echo sprintf(
    "%sTotal: %d | Passed: %d | Failed: %d%s\n",
    $summaryColor,
    $totalTests,
    $passedTests,
    $failedTests,
    $colors['reset']
);

if ($failedTests > 0) {
    echo "\n" . $colors['red'] . "Failed Tests:" . $colors['reset'] . "\n";
    foreach ($results as $test => $passed) {
        if (!$passed) {
            echo "  - $test\n";
        }
    }
}

echo "\n";

// Clean up
if (file_exists(__DIR__ . '/test_cookies.txt')) {
    unlink(__DIR__ . '/test_cookies.txt');
}