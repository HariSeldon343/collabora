<?php
/**
 * Test Script - UI Fixes Verification
 * Verifies that calendar.php, tasks.php, and chat.php have been properly fixed
 * Date: 2025-09-21
 */

session_start();
require_once 'config_v2.php';

// Colors for output
$green = "\033[32m";
$red = "\033[31m";
$yellow = "\033[33m";
$reset = "\033[0m";

echo "========================================\n";
echo "   UI FIXES VERIFICATION TEST\n";
echo "========================================\n\n";

$tests_passed = 0;
$tests_failed = 0;
$warnings = [];

// Files to test
$pages_to_test = [
    'calendar.php',
    'tasks.php',
    'chat.php'
];

// Test 1: Check if pages exist and are readable
echo "1. Checking page files exist...\n";
foreach ($pages_to_test as $page) {
    $file_path = __DIR__ . '/' . $page;
    if (file_exists($file_path) && is_readable($file_path)) {
        echo "   {$green}✓{$reset} $page exists and is readable\n";
        $tests_passed++;
    } else {
        echo "   {$red}✗{$reset} $page missing or not readable\n";
        $tests_failed++;
    }
}
echo "\n";

// Test 2: Check for proper layout structure
echo "2. Checking proper layout structure...\n";
$required_structure = [
    'app-layout' => 'Main app container',
    'main-wrapper' => 'Main wrapper for content',
    'main-content' => 'Main content area',
    'components/sidebar.php' => 'Sidebar include',
    'components/header.php' => 'Header include'
];

foreach ($pages_to_test as $page) {
    $file_path = __DIR__ . '/' . $page;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);

        echo "   Testing $page:\n";
        foreach ($required_structure as $pattern => $description) {
            if (strpos($content, $pattern) !== false) {
                echo "     {$green}✓{$reset} Contains $description\n";
                $tests_passed++;
            } else {
                echo "     {$red}✗{$reset} Missing $description\n";
                $tests_failed++;
            }
        }
    }
}
echo "\n";

// Test 3: Check for anthracite theme colors
echo "3. Checking for anthracite theme...\n";
$sidebar_file = __DIR__ . '/components/sidebar.php';
if (file_exists($sidebar_file)) {
    $sidebar_content = file_get_contents($sidebar_file);

    // Check for color variables
    if (strpos($sidebar_content, '--color-sidebar: #111827') !== false ||
        strpos($sidebar_content, '#111827') !== false ||
        strpos($sidebar_content, 'var(--color-sidebar)') !== false) {
        echo "   {$green}✓{$reset} Sidebar uses anthracite color (#111827)\n";
        $tests_passed++;
    } else {
        echo "   {$yellow}⚠{$reset} Sidebar color not explicitly set (may use CSS)\n";
        $warnings[] = "Sidebar color defined in CSS file";
    }
}

// Check styles.css for theme colors
$styles_file = __DIR__ . '/assets/css/styles.css';
if (file_exists($styles_file)) {
    $styles_content = file_get_contents($styles_file);
    if (strpos($styles_content, '--color-sidebar: #111827') !== false) {
        echo "   {$green}✓{$reset} styles.css defines anthracite sidebar color\n";
        $tests_passed++;
    } else {
        echo "   {$red}✗{$reset} styles.css missing anthracite color definition\n";
        $tests_failed++;
    }
}
echo "\n";

// Test 4: Check for inline SVG icons (Heroicons)
echo "4. Checking for inline SVG icons...\n";
foreach ($pages_to_test as $page) {
    $file_path = __DIR__ . '/' . $page;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);

        // Count SVG tags
        $svg_count = substr_count($content, '<svg');

        // Check for external icon libraries
        $has_external = false;
        if (strpos($content, 'heroicons.com') !== false ||
            strpos($content, '@heroicons') !== false ||
            strpos($content, 'heroicons.min') !== false) {
            $has_external = true;
        }

        if ($svg_count > 0 && !$has_external) {
            echo "   {$green}✓{$reset} $page has $svg_count inline SVG icons (no external libs)\n";
            $tests_passed++;
        } elseif ($has_external) {
            echo "   {$red}✗{$reset} $page uses external icon libraries\n";
            $tests_failed++;
        } else {
            echo "   {$yellow}⚠{$reset} $page has no SVG icons\n";
            $warnings[] = "$page has no SVG icons";
        }
    }
}
echo "\n";

// Test 5: Check JavaScript files for export errors
echo "5. Checking JavaScript files for export errors...\n";
$js_files = [
    'assets/js/post-login-config.js',
    'assets/js/post-login-handler.js',
    'assets/js/calendar.js',
    'assets/js/tasks.js',
    'assets/js/chat.js'
];

foreach ($js_files as $js_file) {
    $file_path = __DIR__ . '/' . $js_file;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);

        // Check for ES6 export statements
        if (preg_match('/^export\s+(default\s+|class\s+|function\s+|const\s+|let\s+|var\s+|\{)/m', $content)) {
            echo "   {$red}✗{$reset} $js_file contains ES6 export statements\n";
            $tests_failed++;
        } else {
            echo "   {$green}✓{$reset} $js_file has no ES6 export issues\n";
            $tests_passed++;
        }
    } else {
        echo "   {$yellow}⚠{$reset} $js_file not found\n";
        $warnings[] = "$js_file not found";
    }
}
echo "\n";

// Test 6: Check for responsive layout classes
echo "6. Checking for responsive layout...\n";
$responsive_patterns = [
    'grid' => 'CSS Grid',
    'flex' => 'Flexbox',
    'responsive' => 'Responsive classes',
    '@media' => 'Media queries'
];

$css_files = [
    'assets/css/styles.css',
    'assets/css/calendar.css',
    'assets/css/tasks.css',
    'assets/css/chat.css'
];

foreach ($css_files as $css_file) {
    $file_path = __DIR__ . '/' . $css_file;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        $basename = basename($css_file);

        $has_responsive = false;
        foreach ($responsive_patterns as $pattern => $description) {
            if (stripos($content, $pattern) !== false) {
                $has_responsive = true;
                break;
            }
        }

        if ($has_responsive) {
            echo "   {$green}✓{$reset} $basename has responsive layout support\n";
            $tests_passed++;
        } else {
            echo "   {$yellow}⚠{$reset} $basename may lack responsive features\n";
            $warnings[] = "$basename may lack responsive features";
        }
    }
}
echo "\n";

// Test 7: Check PHP syntax
echo "7. Checking PHP syntax...\n";
foreach ($pages_to_test as $page) {
    $file_path = __DIR__ . '/' . $page;
    if (file_exists($file_path)) {
        $output = [];
        $return_code = 0;
        exec("php -l $file_path 2>&1", $output, $return_code);

        if ($return_code === 0) {
            echo "   {$green}✓{$reset} $page has valid PHP syntax\n";
            $tests_passed++;
        } else {
            echo "   {$red}✗{$reset} $page has PHP syntax errors\n";
            $tests_failed++;
        }
    }
}
echo "\n";

// Summary
echo "========================================\n";
echo "   TEST SUMMARY\n";
echo "========================================\n";
echo "{$green}Tests Passed:{$reset} $tests_passed\n";
echo "{$red}Tests Failed:{$reset} $tests_failed\n";
if (count($warnings) > 0) {
    echo "{$yellow}Warnings:{$reset} " . count($warnings) . "\n";
    foreach ($warnings as $warning) {
        echo "  - $warning\n";
    }
}

$total_tests = $tests_passed + $tests_failed;
$pass_rate = $total_tests > 0 ? round(($tests_passed / $total_tests) * 100, 1) : 0;

echo "\n";
echo "Pass Rate: $pass_rate%\n";

if ($tests_failed === 0) {
    echo "\n{$green}✅ ALL UI FIXES SUCCESSFULLY APPLIED!{$reset}\n";
} else {
    echo "\n{$red}❌ Some issues need attention{$reset}\n";
}

// Recommendations
echo "\n========================================\n";
echo "   RECOMMENDATIONS\n";
echo "========================================\n";

if ($tests_failed > 0) {
    echo "1. Review failed tests above\n";
    echo "2. Check that all includes are using proper paths\n";
    echo "3. Verify CSS files are properly linked\n";
} else {
    echo "1. Test pages in browser for visual confirmation\n";
    echo "2. Check JavaScript console for runtime errors\n";
    echo "3. Test responsive layout on different screen sizes\n";
}

echo "\nTest URLs:\n";
echo "- http://localhost/Nexiosolution/collabora/calendar.php\n";
echo "- http://localhost/Nexiosolution/collabora/tasks.php\n";
echo "- http://localhost/Nexiosolution/collabora/chat.php\n";

echo "\n";
?>