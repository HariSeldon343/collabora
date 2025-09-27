<?php
/**
 * Test script to identify API errors
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

echo "=== Testing API Endpoints for Errors ===\n\n";

$endpoints = [
    'channels.php',
    'chat-poll.php',
    'calendars.php',
    'events.php',
    'users.php'
];

foreach ($endpoints as $endpoint) {
    echo "Testing: /api/$endpoint\n";
    echo str_repeat('-', 50) . "\n";

    $file = __DIR__ . '/api/' . $endpoint;

    if (!file_exists($file)) {
        echo "ERROR: File does not exist!\n\n";
        continue;
    }

    // Check PHP syntax
    $output = [];
    $return_var = 0;
    exec("php -l \"$file\" 2>&1", $output, $return_var);

    if ($return_var !== 0) {
        echo "SYNTAX ERROR:\n";
        echo implode("\n", $output) . "\n\n";
    } else {
        echo "Syntax OK\n";

        // Try to include the file to catch runtime errors
        echo "Checking for runtime errors...\n";

        // Capture any output/errors
        ob_start();
        $error = '';

        // Set up a custom error handler
        set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$error) {
            $error .= "Error [$errno]: $errstr in $errfile on line $errline\n";
            return true;
        });

        try {
            // Prevent actual execution by setting REQUEST_METHOD
            $_SERVER['REQUEST_METHOD'] = 'OPTIONS';

            // Include the file
            require_once $file;

        } catch (Exception $e) {
            $error .= "Exception: " . $e->getMessage() . "\n";
            $error .= "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        } catch (Error $e) {
            $error .= "Fatal Error: " . $e->getMessage() . "\n";
            $error .= "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        }

        restore_error_handler();
        $output = ob_get_clean();

        if ($error) {
            echo "RUNTIME ERRORS:\n$error\n";
        } elseif ($output && $output !== '{}' && $output !== '') {
            echo "OUTPUT: $output\n";
        } else {
            echo "No runtime errors detected\n";
        }
    }

    echo "\n";
}

// Now test actual HTTP requests with proper session handling
echo "=== Testing Actual HTTP Requests ===\n\n";

// Start session to simulate authenticated user
session_name('NEXIO_SESS');
session_start();

// Check current session
if (isset($_SESSION['user_id'])) {
    echo "Session active for user ID: " . $_SESSION['user_id'] . "\n\n";
} else {
    echo "No active session - APIs will return 401 Unauthorized\n\n";
}

echo "Test complete.\n";