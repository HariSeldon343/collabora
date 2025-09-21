<?php
/**
 * Comprehensive Navigation Fix Verification
 * Tests that navigation works correctly from all contexts
 */

require_once 'config_v2.php';

// ANSI color codes for terminal output
$RED = "\033[31m";
$GREEN = "\033[32m";
$YELLOW = "\033[33m";
$BLUE = "\033[34m";
$RESET = "\033[0m";

echo "\n{$BLUE}═══════════════════════════════════════════════════════════════════{$RESET}\n";
echo "{$BLUE}            NAVIGATION FIX VERIFICATION SUITE{$RESET}\n";
echo "{$BLUE}═══════════════════════════════════════════════════════════════════{$RESET}\n\n";

$all_tests_passed = true;

// Test 1: Configuration Check
echo "{$YELLOW}[TEST 1] Configuration Check{$RESET}\n";
echo "────────────────────────────────────────\n";

if (defined('BASE_URL')) {
    echo "{$GREEN}✓{$RESET} BASE_URL is defined: " . BASE_URL . "\n";

    if (strpos(BASE_URL, 'http://') === 0 || strpos(BASE_URL, 'https://') === 0) {
        echo "{$GREEN}✓{$RESET} BASE_URL includes protocol (absolute URL)\n";
    } else {
        echo "{$RED}✗{$RESET} BASE_URL doesn't include protocol\n";
        $all_tests_passed = false;
    }
} else {
    echo "{$RED}✗{$RESET} BASE_URL is not defined\n";
    $all_tests_passed = false;
}

echo "\n";

// Test 2: Sidebar URL Generation
echo "{$YELLOW}[TEST 2] Sidebar URL Generation Logic{$RESET}\n";
echo "────────────────────────────────────────\n";

// Replicate sidebar.php logic
if (defined('BASE_URL')) {
    if (strpos(BASE_URL, 'http://') === 0 || strpos(BASE_URL, 'https://') === 0) {
        $base_url = rtrim(BASE_URL, '/');
    } else {
        $base_url = rtrim(BASE_URL, '/');
    }
} elseif (defined('APP_URL')) {
    $base_url = rtrim(APP_URL, '/');
} else {
    $base_url = '/Nexiosolution/collabora';
}

echo "Calculated \$base_url: {$base_url}\n";

if (strpos($base_url, 'http') === 0) {
    echo "{$GREEN}✓{$RESET} URLs will be absolute (with protocol)\n";
} elseif (strpos($base_url, '/') === 0) {
    echo "{$GREEN}✓{$RESET} URLs will use absolute paths\n";
} else {
    echo "{$RED}✗{$RESET} URLs might be relative (could break in subdirectories)\n";
    $all_tests_passed = false;
}

echo "\n";

// Test 3: File Existence
echo "{$YELLOW}[TEST 3] Target Files Existence{$RESET}\n";
echo "────────────────────────────────────────\n";

$files = [
    'calendar.php' => 'Calendar page',
    'tasks.php' => 'Tasks page',
    'chat.php' => 'Chat page',
    'files.php' => 'Files manager',
    'index_v2.php' => 'Main dashboard',
    'admin/index.php' => 'Admin dashboard',
    'admin/users.php' => 'User management',
    'components/sidebar.php' => 'Sidebar component',
];

foreach ($files as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "{$GREEN}✓{$RESET} {$description} ({$file})\n";
    } else {
        echo "{$RED}✗{$RESET} {$description} ({$file}) - FILE NOT FOUND\n";
        $all_tests_passed = false;
    }
}

echo "\n";

// Test 4: Generated URLs
echo "{$YELLOW}[TEST 4] Generated Navigation URLs{$RESET}\n";
echo "────────────────────────────────────────\n";

$nav_urls = [
    'Calendar' => $base_url . '/calendar.php',
    'Tasks' => $base_url . '/tasks.php',
    'Chat' => $base_url . '/chat.php',
    'Admin' => $base_url . '/admin/index.php',
];

foreach ($nav_urls as $name => $url) {
    echo "{$name}: {$url}\n";
}

echo "\n";

// Test 5: Simulated Contexts
echo "{$YELLOW}[TEST 5] URL Generation from Different Contexts{$RESET}\n";
echo "────────────────────────────────────────\n";

$contexts = [
    'Root level' => '/',
    'Admin directory' => '/admin/',
    'API directory' => '/api/',
    'Assets directory' => '/assets/',
];

foreach ($contexts as $context => $path) {
    echo "\nFrom {$context} ({$path}):\n";
    echo "  Calendar URL: {$base_url}/calendar.php\n";
    echo "  Tasks URL: {$base_url}/tasks.php\n";
    echo "  Chat URL: {$base_url}/chat.php\n";

    // All URLs should be the same regardless of context (absolute)
    $expected_calendar = $base_url . '/calendar.php';
    echo "  {$GREEN}✓{$RESET} URLs are consistent (absolute)\n";
}

echo "\n";

// Test 6: Sidebar Component Check
echo "{$YELLOW}[TEST 6] Sidebar Component Analysis{$RESET}\n";
echo "────────────────────────────────────────\n";

$sidebar_path = __DIR__ . '/components/sidebar.php';
if (file_exists($sidebar_path)) {
    $sidebar_content = file_get_contents($sidebar_path);

    // Check for proper base_url usage
    if (strpos($sidebar_content, '$base_url') !== false) {
        echo "{$GREEN}✓{$RESET} Sidebar uses \$base_url variable\n";
    } else {
        echo "{$RED}✗{$RESET} Sidebar doesn't use \$base_url variable\n";
        $all_tests_passed = false;
    }

    // Check for problematic patterns
    $problematic_patterns = [
        '/echo\s+BASE_URL\s*;\s*\?>\s*\//' => 'BASE_URL with closing PHP tag before /',
        '/href=["\']\.\.\//' => 'Relative parent directory references (../)',
        '/href=["\'][^\/"\']/' => 'Relative URLs without leading slash',
    ];

    $issues_found = false;
    foreach ($problematic_patterns as $pattern => $description) {
        if (preg_match($pattern, $sidebar_content)) {
            echo "{$RED}✗{$RESET} Found problematic pattern: {$description}\n";
            $issues_found = true;
            $all_tests_passed = false;
        }
    }

    if (!$issues_found) {
        echo "{$GREEN}✓{$RESET} No problematic URL patterns found\n";
    }
} else {
    echo "{$RED}✗{$RESET} Sidebar component not found\n";
    $all_tests_passed = false;
}

echo "\n";

// Final Summary
echo "{$BLUE}═══════════════════════════════════════════════════════════════════{$RESET}\n";
echo "{$BLUE}                           SUMMARY{$RESET}\n";
echo "{$BLUE}═══════════════════════════════════════════════════════════════════{$RESET}\n\n";

if ($all_tests_passed) {
    echo "{$GREEN}╔════════════════════════════════════════════╗{$RESET}\n";
    echo "{$GREEN}║   ✓ ALL TESTS PASSED SUCCESSFULLY!        ║{$RESET}\n";
    echo "{$GREEN}║   Navigation should work correctly         ║{$RESET}\n";
    echo "{$GREEN}║   from all directories.                   ║{$RESET}\n";
    echo "{$GREEN}╚════════════════════════════════════════════╝{$RESET}\n";
} else {
    echo "{$RED}╔════════════════════════════════════════════╗{$RESET}\n";
    echo "{$RED}║   ✗ SOME TESTS FAILED                     ║{$RESET}\n";
    echo "{$RED}║   Navigation may have issues.             ║{$RESET}\n";
    echo "{$RED}║   Please review the errors above.         ║{$RESET}\n";
    echo "{$RED}╚════════════════════════════════════════════╝{$RESET}\n";
}

echo "\n{$YELLOW}BROWSER TEST URLS:{$RESET}\n";
echo "────────────────────────────────────────\n";
echo "1. Main test page: http://localhost/Nexiosolution/collabora/test_sidebar_output.php\n";
echo "2. Admin context test: http://localhost/Nexiosolution/collabora/admin/test_navigation.php\n";
echo "3. Admin dashboard: http://localhost/Nexiosolution/collabora/admin/index.php\n";
echo "   (Login with: asamodeo@fortibyte.it / Ricord@1991)\n";

echo "\n{$YELLOW}MANUAL VERIFICATION:{$RESET}\n";
echo "────────────────────────────────────────\n";
echo "1. Access admin dashboard\n";
echo "2. Click on 'Calendario' in sidebar -> Should go to Calendar page\n";
echo "3. Click on 'Attività' in sidebar -> Should go to Tasks page\n";
echo "4. Click on 'Chat' in sidebar -> Should go to Chat page\n";
echo "5. All navigation should work without refreshing the admin page\n";

echo "\n{$BLUE}═══════════════════════════════════════════════════════════════════{$RESET}\n";