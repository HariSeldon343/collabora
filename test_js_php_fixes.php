<?php
/**
 * Test Script for JavaScript Export and PHP Autoload Fixes
 * Date: 2025-09-20
 *
 * This script verifies that:
 * 1. JavaScript files no longer have export statements
 * 2. PHP files use proper autoload instead of direct includes
 */

// Test autoloader
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

$results = [];

// Test PHP Autoload
echo "<h1>Test JavaScript Export and PHP Autoload Fixes</h1>";
echo "<hr>";

// Test 1: Check if SimpleAuth can be instantiated via autoload
echo "<h2>1. PHP Autoload Test</h2>";
try {
    $auth = new SimpleAuth();
    $results['php_autoload'] = [
        'status' => 'success',
        'message' => 'SimpleAuth class loaded successfully via autoloader'
    ];
    echo "<p style='color: green;'>✅ SimpleAuth class loaded successfully via autoloader</p>";
} catch (Exception $e) {
    $results['php_autoload'] = [
        'status' => 'error',
        'message' => 'Failed to load SimpleAuth: ' . $e->getMessage()
    ];
    echo "<p style='color: red;'>❌ Failed to load SimpleAuth: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 2: Check JavaScript files for export statements
echo "<h2>2. JavaScript Export Test</h2>";

$jsFiles = [
    'assets/js/post-login-config.js',
    'assets/js/post-login-handler.js',
    'assets/js/filemanager.js',
    'assets/js/components.js'
];

foreach ($jsFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);

        // Check for export statements (but not in comments)
        $lines = explode("\n", $content);
        $hasExport = false;
        $exportLine = null;

        foreach ($lines as $lineNum => $line) {
            // Skip commented lines
            if (preg_match('/^\s*\/\//', $line)) {
                continue;
            }
            // Check for export keyword
            if (preg_match('/^\s*export\s+(default|class|function|const|let|var|{)/', $line)) {
                $hasExport = true;
                $exportLine = $lineNum + 1;
                break;
            }
        }

        if ($hasExport) {
            $results['js_' . basename($file)] = [
                'status' => 'error',
                'message' => "Export statement found at line $exportLine"
            ];
            echo "<p style='color: red;'>❌ <strong>$file</strong>: Export statement found at line $exportLine</p>";
        } else {
            // Check if window assignment exists
            $hasWindowAssignment = false;
            if (strpos($file, 'post-login-config.js') !== false) {
                $hasWindowAssignment = strpos($content, 'window.PostLoginConfig') !== false;
            } elseif (strpos($file, 'post-login-handler.js') !== false) {
                $hasWindowAssignment = strpos($content, 'window.PostLoginHandler') !== false;
            } elseif (strpos($file, 'filemanager.js') !== false) {
                $hasWindowAssignment = strpos($content, 'window.FileManager') !== false;
            } elseif (strpos($file, 'components.js') !== false) {
                $hasWindowAssignment = strpos($content, 'window.Components') !== false;
            }

            if ($hasWindowAssignment) {
                $results['js_' . basename($file)] = [
                    'status' => 'success',
                    'message' => 'No export statements, window assignment found'
                ];
                echo "<p style='color: green;'>✅ <strong>$file</strong>: No export statements, window assignment found</p>";
            } else {
                $results['js_' . basename($file)] = [
                    'status' => 'warning',
                    'message' => 'No export statements, but no window assignment found'
                ];
                echo "<p style='color: orange;'>⚠️ <strong>$file</strong>: No export statements, but no window assignment found</p>";
            }
        }
    } else {
        $results['js_' . basename($file)] = [
            'status' => 'error',
            'message' => 'File not found'
        ];
        echo "<p style='color: red;'>❌ <strong>$file</strong>: File not found</p>";
    }
}

// Test 3: Check PHP files for proper autoload usage
echo "<h2>3. PHP Autoload Usage Test</h2>";

$phpFiles = [
    'calendar.php',
    'tasks.php',
    'chat.php'
];

foreach ($phpFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);

        // Check for direct SimpleAuth.php include
        $hasDirectInclude = preg_match('/require(_once)?\s*[\'"]includes\/SimpleAuth\.php[\'"]/', $content);

        // Check for autoload include
        $hasAutoload = preg_match('/require(_once)?\s*[\'"]includes\/autoload\.php[\'"]/', $content);

        // Check for use statement
        $hasUseStatement = strpos($content, 'use Collabora\Auth\SimpleAuth;') !== false;

        if ($hasDirectInclude) {
            $results['php_' . basename($file)] = [
                'status' => 'error',
                'message' => 'Direct include of SimpleAuth.php found (should use autoload)'
            ];
            echo "<p style='color: red;'>❌ <strong>$file</strong>: Direct include of SimpleAuth.php found (should use autoload)</p>";
        } elseif ($hasAutoload && $hasUseStatement) {
            $results['php_' . basename($file)] = [
                'status' => 'success',
                'message' => 'Correctly uses autoload and namespace'
            ];
            echo "<p style='color: green;'>✅ <strong>$file</strong>: Correctly uses autoload and namespace</p>";
        } elseif ($hasAutoload && !$hasUseStatement) {
            $results['php_' . basename($file)] = [
                'status' => 'warning',
                'message' => 'Uses autoload but missing use statement'
            ];
            echo "<p style='color: orange;'>⚠️ <strong>$file</strong>: Uses autoload but missing use statement</p>";
        } else {
            $results['php_' . basename($file)] = [
                'status' => 'info',
                'message' => 'Does not use SimpleAuth directly'
            ];
            echo "<p style='color: blue;'>ℹ️ <strong>$file</strong>: Does not use SimpleAuth directly</p>";
        }
    } else {
        $results['php_' . basename($file)] = [
            'status' => 'error',
            'message' => 'File not found'
        ];
        echo "<p style='color: red;'>❌ <strong>$file</strong>: File not found</p>";
    }
}

// Summary
echo "<h2>4. Test Summary</h2>";

$totalTests = count($results);
$passed = count(array_filter($results, function($r) { return $r['status'] === 'success'; }));
$failed = count(array_filter($results, function($r) { return $r['status'] === 'error'; }));
$warnings = count(array_filter($results, function($r) { return $r['status'] === 'warning'; }));

echo "<div style='border: 2px solid #333; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>Results:</h3>";
echo "<p><strong>Total Tests:</strong> $totalTests</p>";
echo "<p style='color: green;'><strong>Passed:</strong> $passed</p>";
echo "<p style='color: red;'><strong>Failed:</strong> $failed</p>";
echo "<p style='color: orange;'><strong>Warnings:</strong> $warnings</p>";

if ($failed === 0) {
    echo "<h3 style='color: green;'>✅ All critical fixes have been applied successfully!</h3>";
} else {
    echo "<h3 style='color: red;'>❌ Some fixes are still needed. Please review the failed tests above.</h3>";
}
echo "</div>";

// Recommendations
echo "<h2>5. Recommendations</h2>";
echo "<ul>";
echo "<li>Test the application in the browser to ensure no JavaScript errors appear in the console</li>";
echo "<li>Access calendar.php, tasks.php, and chat.php to verify authentication works correctly</li>";
echo "<li>Check PHP error logs for any 'Class not found' errors</li>";
echo "<li>Run this test again after making any changes to verify fixes are still in place</li>";
echo "</ul>";

// Show detailed results in JSON for debugging
echo "<h2>6. Detailed Results (JSON)</h2>";
echo "<pre style='background: #f0f0f0; padding: 10px; border-radius: 5px;'>";
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "</pre>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    background: #f5f5f5;
}
h1 {
    color: #333;
    border-bottom: 3px solid #007bff;
    padding-bottom: 10px;
}
h2 {
    color: #555;
    margin-top: 30px;
}
pre {
    overflow-x: auto;
}
</style>