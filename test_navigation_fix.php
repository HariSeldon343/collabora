<?php
/**
 * Test Navigation Fix
 * Verifies that navigation links work correctly from admin subdirectory
 */

require_once 'config_v2.php';

echo "=== NAVIGATION FIX TEST ===\n\n";

// Show current configuration
echo "1. CONFIGURATION CHECK:\n";
echo "   BASE_URL defined as: " . BASE_URL . "\n";
echo "   Protocol detected: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "\n";
echo "   Host: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n\n";

// Test sidebar URL generation logic
echo "2. SIDEBAR URL GENERATION TEST:\n";

// Simulate being in admin directory
$_SERVER['PHP_SELF'] = '/Nexiosolution/collabora/admin/index.php';
$_SERVER['REQUEST_URI'] = '/Nexiosolution/collabora/admin/index.php';

// Include sidebar logic (same as components/sidebar.php)
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

echo "   Base URL calculated: $base_url\n\n";

// Test generated links
echo "3. GENERATED LINKS (from admin context):\n";
$links = [
    'Dashboard' => $base_url . '/index_v2.php',
    'Calendar' => $base_url . '/calendar.php',
    'Tasks' => $base_url . '/tasks.php',
    'Chat' => $base_url . '/chat.php',
    'Admin Dashboard' => $base_url . '/admin/index.php',
    'User Management' => $base_url . '/admin/users.php',
];

foreach ($links as $name => $url) {
    echo "   $name: $url\n";
}

echo "\n4. EXPECTED BEHAVIOR:\n";
echo "   ✓ Links should be absolute URLs when BASE_URL includes protocol\n";
echo "   ✓ Links should work from any subdirectory (including /admin/)\n";
echo "   ✓ No relative paths that would break in subdirectories\n";

echo "\n5. VERIFICATION:\n";
// Check if files exist
$files_to_check = [
    'calendar.php',
    'tasks.php',
    'chat.php',
    'admin/index.php',
    'admin/users.php'
];

foreach ($files_to_check as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "   ✓ File exists: $file\n";
    } else {
        echo "   ✗ File missing: $file\n";
    }
}

echo "\n6. BROWSER TEST URLS:\n";
echo "   Open browser and test these URLs:\n";
foreach ($links as $name => $url) {
    // If URL starts with http, it's already complete
    if (strpos($url, 'http') === 0) {
        echo "   - $name: $url\n";
    } else {
        // Add protocol and host for browser testing
        echo "   - $name: http://localhost$url\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
echo "\nTo fix any issues:\n";
echo "1. Ensure BASE_URL in config_v2.php includes full protocol and host\n";
echo "2. Sidebar should use \$base_url for all links\n";
echo "3. All links should be absolute (start with http:// or /)\n";