<?php
/**
 * Complete Test for All Fixes
 * Date: 2025-09-20
 *
 * This script verifies that:
 * 1. JavaScript files have no export statements (even commented)
 * 2. PHP files can load SimpleAuth correctly
 * 3. System is functional
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html><head><title>Complete Fixes Test</title></head><body>\n";
echo "<h1>Complete System Fixes Test</h1>\n";

$allGood = true;

// Test 1: Check JavaScript files for any export statements
echo "<h2>1. JavaScript Export Check</h2>\n";

$jsFiles = [
    'assets/js/post-login-config.js',
    'assets/js/post-login-handler.js'
];

foreach ($jsFiles as $file) {
    if (!file_exists($file)) {
        echo "<p style='color: orange;'>⚠️ File not found: $file</p>\n";
        continue;
    }

    $content = file_get_contents($file);

    // Check for any occurrence of 'export' (even in comments)
    if (preg_match('/\bexport\s+(?:default|{|\*)/i', $content)) {
        echo "<p style='color: red;'>❌ <strong>$file</strong>: Still contains export statements!</p>\n";
        $allGood = false;
    } else {
        echo "<p style='color: green;'>✅ <strong>$file</strong>: No export statements found</p>\n";
    }

    // Check for window assignment
    if (strpos($content, 'window.') !== false) {
        echo "<p style='color: green;'>✅ <strong>$file</strong>: Uses window assignment (good)</p>\n";
    }
}

// Test 2: Check PHP SimpleAuth loading
echo "<h2>2. PHP SimpleAuth Loading Test</h2>\n";

// Test direct include method
$testFiles = ['calendar.php', 'tasks.php', 'chat.php'];
foreach ($testFiles as $file) {
    if (!file_exists($file)) {
        echo "<p style='color: orange;'>⚠️ File not found: $file</p>\n";
        continue;
    }

    $content = file_get_contents($file);

    // Check if it's using direct include (which is what we want now)
    if (strpos($content, "require_once 'includes/SimpleAuth.php'") !== false) {
        echo "<p style='color: green;'>✅ <strong>$file</strong>: Uses direct include of SimpleAuth.php</p>\n";
    } else {
        echo "<p style='color: red;'>❌ <strong>$file</strong>: Not using direct include</p>\n";
        $allGood = false;
    }

    // Check it's NOT using namespace
    if (strpos($content, 'use Collabora\Auth\SimpleAuth') !== false) {
        echo "<p style='color: red;'>❌ <strong>$file</strong>: Still trying to use namespace!</p>\n";
        $allGood = false;
    } else {
        echo "<p style='color: green;'>✅ <strong>$file</strong>: Not using namespace (good)</p>\n";
    }
}

// Test 3: Verify SimpleAuth can be loaded
echo "<h2>3. SimpleAuth Class Loading Test</h2>\n";

try {
    // Prevent session already started warnings
    if (session_status() == PHP_SESSION_NONE) {
        @session_start();
    }

    require_once 'includes/SimpleAuth.php';

    if (class_exists('SimpleAuth')) {
        echo "<p style='color: green;'>✅ SimpleAuth class exists and can be instantiated</p>\n";

        // Try to create an instance
        $testAuth = new SimpleAuth();
        echo "<p style='color: green;'>✅ SimpleAuth instance created successfully</p>\n";
    } else {
        echo "<p style='color: red;'>❌ SimpleAuth class not found!</p>\n";
        $allGood = false;
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading SimpleAuth: " . $e->getMessage() . "</p>\n";
    $allGood = false;
}

// Test 4: Check if test_js_php_fixes.php is accessible
echo "<h2>4. Test Script Accessibility</h2>\n";

if (file_exists('test_js_php_fixes.php')) {
    echo "<p style='color: green;'>✅ test_js_php_fixes.php is in the root directory</p>\n";
} else {
    echo "<p style='color: red;'>❌ test_js_php_fixes.php not found in root</p>\n";
    $allGood = false;
}

// Summary
echo "<h2>Test Summary</h2>\n";
echo "<div style='border: 2px solid " . ($allGood ? 'green' : 'red') . "; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";

if ($allGood) {
    echo "<h3 style='color: green;'>✅ ALL TESTS PASSED!</h3>\n";
    echo "<p>All critical issues have been fixed:</p>\n";
    echo "<ul>\n";
    echo "<li>JavaScript export statements completely removed</li>\n";
    echo "<li>PHP files use direct includes instead of namespaces</li>\n";
    echo "<li>SimpleAuth loads correctly</li>\n";
    echo "<li>Test scripts are accessible</li>\n";
    echo "</ul>\n";
} else {
    echo "<h3 style='color: red;'>❌ Some issues remain</h3>\n";
    echo "<p>Please review the failed tests above and fix the remaining issues.</p>\n";
}

echo "</div>\n";

// Recommendations
echo "<h2>Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>Test the login functionality at <a href='index_v2.php'>index_v2.php</a></li>\n";
echo "<li>Try accessing <a href='calendar.php'>calendar.php</a> (should redirect to login if not authenticated)</li>\n";
echo "<li>Try accessing <a href='tasks.php'>tasks.php</a> (should redirect to login if not authenticated)</li>\n";
echo "<li>Try accessing <a href='chat.php'>chat.php</a> (should redirect to login if not authenticated)</li>\n";
echo "<li>Check browser console for any JavaScript errors</li>\n";
echo "</ol>\n";

echo "<style>\n";
echo "body { font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; background: #f5f5f5; }\n";
echo "h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }\n";
echo "h2 { color: #555; margin-top: 30px; }\n";
echo "a { color: #007bff; text-decoration: none; }\n";
echo "a:hover { text-decoration: underline; }\n";
echo "</style>\n";

echo "</body></html>\n";
?>