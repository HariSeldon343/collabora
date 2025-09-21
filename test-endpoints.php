<?php
/**
 * Endpoint Diagnostic Script for Nexio Collabora
 * Tests all failing endpoints and captures exact errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/endpoint-errors.log');

// Clear previous error log
file_put_contents(__DIR__ . '/endpoint-errors.log', '');

// Base URL - adjust if needed
$baseUrl = 'http://localhost/Nexiosolution/collabora';

// Test endpoints with their expected methods
$endpoints = [
    ['url' => '/api/calendars.php', 'method' => 'GET', 'expectedError' => '500'],
    ['url' => '/api/events.php', 'method' => 'GET', 'expectedError' => '500'],
    ['url' => '/api/task-lists.php', 'method' => 'GET', 'expectedError' => '500'],
    ['url' => '/api/channels.php', 'method' => 'GET', 'expectedError' => '500'],
    ['url' => '/api/chat-poll.php', 'method' => 'GET', 'expectedError' => '500'],
    ['url' => '/api/users.php', 'method' => 'GET', 'expectedError' => '401']
];

// Colors for terminal output
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[1;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "{$blue}=====================================\n";
echo "NEXIO COLLABORA ENDPOINT DIAGNOSTICS\n";
echo "====================================={$reset}\n\n";

// Function to make HTTP request and capture response
function testEndpoint($url, $method = 'GET', $headers = [], $data = null) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'url' => $url
    ];
}

// Function to directly test PHP file for errors
function testDirectPHP($filepath) {
    ob_start();
    $errorOutput = '';

    // Custom error handler to capture all errors
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorOutput) {
        $errorOutput .= "PHP Error [$errno]: $errstr in $errfile on line $errline\n";
    });

    // Try to include the file
    try {
        // Suppress direct output and capture any fatal errors
        @include $filepath;
    } catch (Exception $e) {
        $errorOutput .= "Exception: " . $e->getMessage() . "\n";
        $errorOutput .= "Stack trace:\n" . $e->getTraceAsString() . "\n";
    } catch (ParseError $e) {
        $errorOutput .= "Parse Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
    } catch (Error $e) {
        $errorOutput .= "Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
        $errorOutput .= "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }

    restore_error_handler();
    $output = ob_get_clean();

    return [
        'output' => $output,
        'errors' => $errorOutput
    ];
}

$results = [];

// Test each endpoint
foreach ($endpoints as $endpoint) {
    echo "{$yellow}Testing: {$endpoint['url']}{$reset}\n";
    echo str_repeat('-', 40) . "\n";

    // Test via HTTP
    $fullUrl = $baseUrl . $endpoint['url'];
    $httpResult = testEndpoint($fullUrl, $endpoint['method']);

    echo "HTTP Status: ";
    if ($httpResult['http_code'] == $endpoint['expectedError']) {
        echo "{$red}{$httpResult['http_code']} (Expected){$reset}\n";
    } else {
        echo "{$green}{$httpResult['http_code']}{$reset}\n";
    }

    // Try to parse JSON error if exists
    $jsonError = json_decode($httpResult['body'], true);
    if ($jsonError && isset($jsonError['error'])) {
        echo "Error Message: {$red}{$jsonError['error']}{$reset}\n";
        if (isset($jsonError['details'])) {
            echo "Details: {$jsonError['details']}\n";
        }
    } else if (!empty($httpResult['body'])) {
        // Check if body contains HTML error
        if (strpos($httpResult['body'], 'Fatal error') !== false ||
            strpos($httpResult['body'], 'Parse error') !== false ||
            strpos($httpResult['body'], 'Warning') !== false) {

            // Extract error message from HTML
            preg_match('/<b>(Fatal error|Parse error|Warning)<\/b>:\s*(.*?)(?:\sin\s|<br)/s', $httpResult['body'], $matches);
            if (!empty($matches)) {
                echo "PHP Error: {$red}{$matches[1]}: {$matches[2]}{$reset}\n";
            }
        } else {
            echo "Response Body (first 200 chars): " . substr($httpResult['body'], 0, 200) . "\n";
        }
    }

    // Test direct PHP inclusion to get detailed errors
    $phpFile = __DIR__ . $endpoint['url'];
    if (file_exists($phpFile)) {
        echo "\n{$blue}Direct PHP Test:{$reset}\n";
        $directResult = testDirectPHP($phpFile);

        if (!empty($directResult['errors'])) {
            echo "{$red}Errors found:{$reset}\n";
            echo $directResult['errors'];
        }

        // Save for report
        $results[$endpoint['url']] = [
            'http_code' => $httpResult['http_code'],
            'http_body' => $httpResult['body'],
            'direct_errors' => $directResult['errors'],
            'direct_output' => $directResult['output']
        ];
    }

    echo "\n";
}

// Check if database tables exist
echo "{$blue}=====================================\n";
echo "DATABASE TABLE CHECK\n";
echo "====================================={$reset}\n\n";

// Database connection for testing
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'nexiosolution',
    'user' => 'root',
    'password' => ''
];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']}",
        $dbConfig['user'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check for expected tables
    $expectedTables = [
        'calendars',
        'events',
        'tasks',
        'task_lists',
        'chat_channels',
        'chat_messages',
        'rooms',  // Might be old name
        'users',
        'tenants'
    ];

    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($expectedTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "{$green}✓{$reset} Table '{$table}' exists\n";
        } else {
            echo "{$red}✗{$reset} Table '{$table}' NOT FOUND\n";
        }
    }

    // Check for table structure issues
    echo "\n{$blue}Table Structure Analysis:{$reset}\n";

    // Check calendars table
    if (in_array('calendars', $existingTables)) {
        $stmt = $pdo->query("DESCRIBE calendars");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Calendars columns: " . implode(', ', $columns) . "\n";
    }

    // Check if rooms or chat_channels exists
    if (in_array('rooms', $existingTables) && !in_array('chat_channels', $existingTables)) {
        echo "{$yellow}Warning: Found 'rooms' table but not 'chat_channels' - possible naming mismatch{$reset}\n";
    }

} catch (PDOException $e) {
    echo "{$red}Database Connection Error: " . $e->getMessage() . "{$reset}\n";
}

// Check PHP error log
echo "\n{$blue}=====================================\n";
echo "PHP ERROR LOG (Last 20 lines)\n";
echo "====================================={$reset}\n\n";

$errorLogPath = ini_get('error_log');
if (file_exists($errorLogPath)) {
    $lines = file($errorLogPath);
    $lastLines = array_slice($lines, -20);
    foreach ($lastLines as $line) {
        if (strpos($line, 'nexiosolution') !== false || strpos($line, 'collabora') !== false) {
            echo $line;
        }
    }
} else {
    echo "Error log not found at: $errorLogPath\n";
}

// Generate summary report
echo "\n{$blue}=====================================\n";
echo "SUMMARY REPORT\n";
echo "====================================={$reset}\n\n";

$reportFile = __DIR__ . '/error-report.txt';
$report = "NEXIO COLLABORA - ERROR DIAGNOSTIC REPORT\n";
$report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
$report .= str_repeat('=', 50) . "\n\n";

foreach ($results as $endpoint => $result) {
    $report .= "ENDPOINT: $endpoint\n";
    $report .= "HTTP Status: {$result['http_code']}\n";

    if (!empty($result['direct_errors'])) {
        $report .= "ERRORS:\n" . $result['direct_errors'] . "\n";
    }

    // Extract specific SQL errors
    if (strpos($result['http_body'], 'SQLSTATE') !== false) {
        preg_match('/SQLSTATE\[([^\]]+)\].*?:\s*(.+?)(?:\s*in\s*|$)/s', $result['http_body'], $matches);
        if (!empty($matches)) {
            $report .= "SQL ERROR: {$matches[2]}\n";
        }
    }

    $report .= str_repeat('-', 30) . "\n\n";
}

file_put_contents($reportFile, $report);

echo "{$green}Report saved to: $reportFile{$reset}\n";
echo "{$green}Error log saved to: " . __DIR__ . "/endpoint-errors.log{$reset}\n";

// Final recommendations
echo "\n{$blue}IMMEDIATE ACTIONS REQUIRED:{$reset}\n";
echo "1. Check database schema mismatches (rooms vs chat_channels)\n";
echo "2. Verify all required tables exist\n";
echo "3. Check include paths and class autoloading\n";
echo "4. Review authentication middleware for /api/users.php\n";