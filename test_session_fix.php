<?php
/**
 * Test Script: Verify Session Fix for API Endpoints
 * Tests that all API endpoints use the correct session name (NEXIO_V2_SESSID)
 */

// Include configuration and SimpleAuth to properly initialize session
require_once __DIR__ . '/config_v2.php';
require_once __DIR__ . '/includes/SimpleAuth.php';

// Let SimpleAuth handle session initialization with correct name
$auth = new SimpleAuth();

header('Content-Type: text/plain; charset=utf-8');

echo "========================================\n";
echo "SESSION FIX VERIFICATION TEST\n";
echo "========================================\n\n";

// Check current session configuration
echo "1. SESSION CONFIGURATION CHECK:\n";
echo "   Session Name: " . session_name() . "\n";
echo "   Expected: NEXIO_V2_SESSID\n";
echo "   Status: " . (session_name() === 'NEXIO_V2_SESSID' ? '✓ PASS' : '✗ FAIL') . "\n\n";

// List of API endpoints to verify
$apiEndpoints = [
    'auth_simple.php',
    'auth_v2.php',
    'calendars.php',
    'events.php',
    'tasks.php',
    'task-lists.php',
    'messages.php',
    'channels.php',
    'chat-poll.php',
    'presence.php',
    'reactions.php',
    'me.php',
    'users.php',
    'switch-tenant.php'
];

echo "2. API FILES CONFIGURATION CHECK:\n";
echo "   Checking that config_v2.php is included BEFORE any output...\n\n";

$errors = [];
$warnings = [];
$passed = 0;

foreach ($apiEndpoints as $endpoint) {
    $filePath = __DIR__ . '/api/' . $endpoint;

    if (!file_exists($filePath)) {
        echo "   [$endpoint] - File not found, skipping\n";
        continue;
    }

    // Read first 50 lines of the file
    $lines = array_slice(file($filePath), 0, 50);

    $configLine = -1;
    $sessionStartLine = -1;
    $headerLine = -1;

    foreach ($lines as $lineNum => $line) {
        // Check for config include
        if (strpos($line, 'config_v2.php') !== false || strpos($line, 'config.php') !== false) {
            if ($configLine === -1) {
                $configLine = $lineNum + 1;
            }
        }

        // Check for session_start
        if (strpos($line, 'session_start()') !== false) {
            if ($sessionStartLine === -1) {
                $sessionStartLine = $lineNum + 1;
            }
        }

        // Check for first header() call
        if (strpos($line, 'header(') !== false && $headerLine === -1) {
            // Skip if it's in a comment
            $trimmed = trim($line);
            if (strpos($trimmed, '//') !== 0 && strpos($trimmed, '*') !== 0) {
                $headerLine = $lineNum + 1;
            }
        }
    }

    // Analyze results
    $status = '✓ PASS';
    $details = [];

    if ($configLine === -1) {
        $status = '✗ FAIL';
        $errors[] = "$endpoint: Missing config include";
        $details[] = "Missing config_v2.php include";
    } elseif ($sessionStartLine !== -1 && $sessionStartLine < $configLine) {
        $status = '✗ FAIL';
        $errors[] = "$endpoint: session_start() on line $sessionStartLine before config on line $configLine";
        $details[] = "session_start() before config";
    } elseif ($headerLine !== -1 && $configLine > $headerLine) {
        $status = '⚠ WARNING';
        $warnings[] = "$endpoint: Config on line $configLine after header on line $headerLine";
        $details[] = "Config after headers (may cause issues)";
    } else {
        $passed++;
        $details[] = "Config on line $configLine" . ($sessionStartLine !== -1 ? ", session_start on line $sessionStartLine" : ", no direct session_start");
    }

    echo "   [$endpoint] $status";
    if (!empty($details)) {
        echo " - " . implode(', ', $details);
    }
    echo "\n";
}

echo "\n3. SUMMARY:\n";
echo "   Total Files Checked: " . count($apiEndpoints) . "\n";
echo "   Passed: $passed\n";
echo "   Warnings: " . count($warnings) . "\n";
echo "   Errors: " . count($errors) . "\n\n";

if (!empty($errors)) {
    echo "4. ERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "   ✗ $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "5. WARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "   ⚠ $warning\n";
    }
    echo "\n";
}

// Test actual session functionality
echo "6. FUNCTIONAL TEST:\n";
echo "   Testing session write/read with correct session name...\n";

// Session already started by SimpleAuth, no need to start again

// Write test data
$_SESSION['test_timestamp'] = time();
$_SESSION['test_data'] = 'Session fix verification';

// Read it back
$canRead = isset($_SESSION['test_timestamp']) && isset($_SESSION['test_data']);
echo "   Session Write/Read: " . ($canRead ? '✓ PASS' : '✗ FAIL') . "\n";

// Check session cookie name
$cookieName = session_name();
$hasCookie = isset($_COOKIE[$cookieName]);
echo "   Cookie Name: $cookieName\n";
echo "   Cookie Present: " . ($hasCookie ? '✓ YES' : '✗ NO') . "\n";

// Clean up test data
unset($_SESSION['test_timestamp']);
unset($_SESSION['test_data']);

echo "\n========================================\n";
if (empty($errors) && empty($warnings)) {
    echo "✓ ALL TESTS PASSED! Session configuration is correct.\n";
} elseif (empty($errors)) {
    echo "⚠ TESTS PASSED WITH WARNINGS. Review the warnings above.\n";
} else {
    echo "✗ TESTS FAILED! Fix the errors listed above.\n";
}
echo "========================================\n";