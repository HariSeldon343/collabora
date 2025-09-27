<?php
// Test to identify the namespace issue

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "Testing namespace loading issues:\n\n";

// Test 1: Check if autoload.php works
echo "1. Testing autoload.php:\n";
if (file_exists(__DIR__ . '/includes/autoload.php')) {
    require_once __DIR__ . '/includes/autoload.php';
    echo "   - autoload.php loaded successfully\n";
} else {
    echo "   - ERROR: autoload.php not found!\n";
}

// Test 2: Try to use CalendarManager with namespace
echo "\n2. Testing CalendarManager with namespace:\n";
try {
    if (class_exists('Collabora\Calendar\CalendarManager')) {
        echo "   - CalendarManager class found with namespace\n";
    } else {
        echo "   - CalendarManager class NOT found with namespace\n";
    }
} catch (Exception $e) {
    echo "   - ERROR: " . $e->getMessage() . "\n";
}

// Test 3: Try to use SessionHelper with namespace
echo "\n3. Testing SessionHelper with namespace:\n";
try {
    if (class_exists('Collabora\Session\SessionHelper')) {
        echo "   - SessionHelper class found with namespace\n";
    } else {
        echo "   - SessionHelper class NOT found with namespace\n";
    }
} catch (Exception $e) {
    echo "   - ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Try to use TaskManager with namespace
echo "\n4. Testing TaskManager with namespace:\n";
try {
    if (class_exists('Collabora\Tasks\TaskManager')) {
        echo "   - TaskManager class found with namespace\n";
    } else {
        echo "   - TaskManager class NOT found with namespace\n";
    }
} catch (Exception $e) {
    echo "   - ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Check if files exist
echo "\n5. Checking if files exist:\n";
$files = [
    'includes/CalendarManager.php',
    'includes/session_helper.php',
    'includes/TaskManager.php',
    'includes/ChatManager.php'
];

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "   - $file EXISTS\n";

        // Check for namespace declaration
        $content = file_get_contents($fullPath);
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            echo "     Namespace: " . $matches[1] . "\n";
        } else {
            echo "     No namespace found\n";
        }
    } else {
        echo "   - $file NOT FOUND\n";
    }
}

echo "\nTest complete.\n";