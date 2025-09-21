<?php
/**
 * Test script to verify API endpoints are working properly
 */

// Start session for authentication
session_start();

// Test configuration
$baseUrl = 'http://localhost/Nexiosolution/collabora';
$testEmail = 'asamodeo@fortibyte.it';
$testPassword = 'Ricord@1991';

// Colors for output
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[0;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "================================\n";
echo "${blue}API Endpoint Test Suite${reset}\n";
echo "================================\n\n";

// Function to make API request
function makeRequest($url, $method = 'GET', $data = null, $cookies = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    if ($cookies !== null) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'header' => $header,
        'body' => $body,
        'json' => json_decode($body, true)
    ];
}

// Function to extract cookies from header
function extractCookies($header) {
    preg_match_all('/Set-Cookie:\s*([^;]+);/mi', $header, $matches);
    return implode('; ', $matches[1]);
}

// Test results tracker
$testResults = [];

echo "${yellow}Step 1: Login to get session${reset}\n";
echo "-------------------------------\n";

// Login first
$loginResponse = makeRequest(
    "$baseUrl/api/auth_simple.php",
    'POST',
    [
        'action' => 'login',
        'email' => $testEmail,
        'password' => $testPassword
    ]
);

if ($loginResponse['http_code'] === 200 && isset($loginResponse['json']['success']) && $loginResponse['json']['success']) {
    echo "${green}✓${reset} Login successful\n";
    $cookies = extractCookies($loginResponse['header']);
    echo "Session established: " . substr($cookies, 0, 50) . "...\n";
} else {
    echo "${red}✗${reset} Login failed\n";
    echo "HTTP Code: " . $loginResponse['http_code'] . "\n";
    echo "Response: " . $loginResponse['body'] . "\n";
    exit(1);
}

echo "\n${yellow}Step 2: Test API Endpoints${reset}\n";
echo "-------------------------------\n";

// List of endpoints to test
$endpoints = [
    ['name' => 'Calendars List', 'url' => '/api/calendars.php', 'method' => 'GET', 'expect_code' => 200],
    ['name' => 'Events List', 'url' => '/api/events.php', 'method' => 'GET', 'expect_code' => 200],
    ['name' => 'Task Lists', 'url' => '/api/task-lists.php', 'method' => 'GET', 'expect_code' => 200],
    ['name' => 'Chat Channels', 'url' => '/api/channels.php', 'method' => 'GET', 'expect_code' => 200],
    ['name' => 'Users List', 'url' => '/api/users.php', 'method' => 'GET', 'expect_code' => 200],
    ['name' => 'Messages Last ID', 'url' => '/api/messages.php?action=get_last_id', 'method' => 'GET', 'expect_code' => 200],
    ['name' => 'Chat Poll', 'url' => '/api/chat-poll.php?last_message_id=0&timeout=1', 'method' => 'GET', 'expect_code' => 200]
];

foreach ($endpoints as $endpoint) {
    echo "\nTesting: {$endpoint['name']}\n";
    echo "  URL: {$endpoint['url']}\n";

    $response = makeRequest(
        $baseUrl . $endpoint['url'],
        $endpoint['method'],
        null,
        $cookies
    );

    $passed = false;
    $status = "${red}FAILED${reset}";
    $details = "";

    if ($response['http_code'] === $endpoint['expect_code']) {
        if (isset($response['json']['success'])) {
            if ($response['json']['success']) {
                $passed = true;
                $status = "${green}PASSED${reset}";
                $details = "Response OK";
            } else {
                $details = "Success=false: " . ($response['json']['message'] ?? 'No message');
            }
        } else {
            // For some endpoints, just getting 200 is enough
            if ($response['http_code'] === 200) {
                $passed = true;
                $status = "${green}PASSED${reset}";
                $details = "HTTP 200 OK";
            } else {
                $details = "No success field in response";
            }
        }
    } else {
        $details = "Expected {$endpoint['expect_code']}, got {$response['http_code']}";

        // Check for specific error types
        if ($response['http_code'] === 500) {
            $details .= " (Server Error)";
            if (isset($response['json']['debug'])) {
                $details .= " - " . $response['json']['debug'];
            }
        } elseif ($response['http_code'] === 401) {
            $details .= " (Unauthorized)";
        } elseif ($response['http_code'] === 400) {
            $details .= " (Bad Request)";
            if (isset($response['json']['message'])) {
                $details .= " - " . $response['json']['message'];
            }
        }
    }

    echo "  Status: $status\n";
    echo "  HTTP Code: {$response['http_code']}\n";
    echo "  Details: $details\n";

    $testResults[] = [
        'name' => $endpoint['name'],
        'passed' => $passed,
        'http_code' => $response['http_code'],
        'details' => $details
    ];
}

// Summary
echo "\n================================\n";
echo "${blue}Test Summary${reset}\n";
echo "================================\n";

$totalTests = count($testResults);
$passedTests = array_filter($testResults, fn($r) => $r['passed']);
$failedTests = array_filter($testResults, fn($r) => !$r['passed']);

echo "Total Tests: $totalTests\n";
echo "${green}Passed: " . count($passedTests) . "${reset}\n";
echo "${red}Failed: " . count($failedTests) . "${reset}\n";

if (count($failedTests) > 0) {
    echo "\n${yellow}Failed Tests:${reset}\n";
    foreach ($failedTests as $test) {
        echo "  - {$test['name']}: {$test['details']}\n";
    }
}

$successRate = round((count($passedTests) / $totalTests) * 100, 1);
echo "\nSuccess Rate: $successRate%\n";

if ($successRate === 100.0) {
    echo "\n${green}✓ All API endpoints are working correctly!${reset}\n";
} elseif ($successRate >= 80.0) {
    echo "\n${yellow}⚠ Most API endpoints are working, but some issues remain.${reset}\n";
} else {
    echo "\n${red}✗ Many API endpoints are failing. Further investigation needed.${reset}\n";
}

echo "\n";