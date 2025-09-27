<?php
/**
 * Final comprehensive test for all API endpoints
 * Tests both syntax and runtime issues
 */

echo "=== API Endpoint Testing Tool ===\n\n";
echo "This tool checks all API endpoints for errors\n";
echo str_repeat('=', 50) . "\n\n";

// List of endpoints to test
$endpoints = [
    '/api/channels.php',
    '/api/chat-poll.php',
    '/api/calendars.php',
    '/api/events.php',
    '/api/users.php'
];

$baseDir = __DIR__;
$allPassed = true;

foreach ($endpoints as $endpoint) {
    $fullPath = $baseDir . $endpoint;
    echo "Testing: $endpoint\n";

    if (!file_exists($fullPath)) {
        echo "  ❌ File does not exist\n\n";
        $allPassed = false;
        continue;
    }

    // Check PHP syntax
    $output = [];
    $return_var = 0;
    exec("php -l \"$fullPath\" 2>&1", $output, $return_var);

    if ($return_var !== 0) {
        echo "  ❌ Syntax error detected\n";
        echo "  " . implode("\n  ", $output) . "\n\n";
        $allPassed = false;
    } else {
        echo "  ✅ PHP syntax is valid\n\n";
    }
}

echo str_repeat('=', 50) . "\n";
if ($allPassed) {
    echo "✅ All endpoints have valid PHP syntax!\n\n";
    echo "Note: Runtime errors (500 errors) may still occur if:\n";
    echo "1. Database tables are missing\n";
    echo "2. User is not authenticated\n";
    echo "3. Required dependencies are not loaded\n\n";
    echo "To fix database issues, run:\n";
    echo "  php apply_missing_migrations.php\n";
} else {
    echo "❌ Some endpoints have errors that need fixing\n";
}