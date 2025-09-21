<?php
session_start();
require_once __DIR__ . '/../config_v2.php';

// Output headers for plain text
header('Content-Type: text/plain; charset=utf-8');

echo "=== NAVIGATION DEBUG FROM ADMIN DIRECTORY ===\n\n";

// Show current location
echo "Current Directory: " . __DIR__ . "\n";
echo "Current Script: " . $_SERVER['PHP_SELF'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n\n";

// Show BASE_URL configuration
echo "=== BASE_URL Configuration ===\n";
echo "BASE_URL defined: " . (defined('BASE_URL') ? 'Yes' : 'No') . "\n";
if (defined('BASE_URL')) {
    echo "BASE_URL value: " . BASE_URL . "\n";
}
echo "\n";

// Show what sidebar.php would generate
echo "=== Sidebar URL Generation ===\n";
if (defined('BASE_URL')) {
    if (strpos(BASE_URL, 'http://') === 0 || strpos(BASE_URL, 'https://') === 0) {
        $base_url = rtrim(BASE_URL, '/');
    } else {
        $base_url = rtrim(BASE_URL, '/');
    }
} else {
    $base_url = '/Nexiosolution/collabora';
}

echo "\$base_url = $base_url\n\n";

echo "Generated URLs:\n";
echo "Calendar: " . $base_url . '/calendar.php' . "\n";
echo "Tasks: " . $base_url . '/tasks.php' . "\n";
echo "Chat: " . $base_url . '/chat.php' . "\n\n";

// Check if files exist from admin perspective
echo "=== File Existence Check (from admin dir) ===\n";
$files = ['../calendar.php', '../tasks.php', '../chat.php'];
foreach ($files as $file) {
    echo "$file: " . (file_exists($file) ? 'EXISTS' : 'NOT FOUND') . "\n";
}
echo "\n";

// Show actual HTML that would be generated
echo "=== Actual HTML Links ===\n";
echo '<a href="' . $base_url . '/calendar.php">Calendar Link</a>' . "\n";
echo '<a href="' . $base_url . '/tasks.php">Tasks Link</a>' . "\n";
echo '<a href="' . $base_url . '/chat.php">Chat Link</a>' . "\n\n";

// Test include of sidebar
echo "=== Including Sidebar to See Real Output ===\n";
echo "Attempting to include components/sidebar.php...\n\n";

// Capture sidebar output
ob_start();
include __DIR__ . '/../components/sidebar.php';
$sidebar_html = ob_get_clean();

// Extract just the calendar, tasks, chat links
preg_match_all('/<a href="([^"]*(?:calendar|tasks|chat)\.php[^"]*)"/', $sidebar_html, $matches);

echo "Extracted Links from Sidebar:\n";
foreach ($matches[1] as $link) {
    echo "- $link\n";
}

echo "\n=== DIAGNOSIS ===\n";
if (strpos($base_url, 'http') === 0) {
    echo "✓ BASE_URL is absolute (includes http://)\n";
    echo "✓ Links should work from any directory\n";
} else {
    echo "✗ BASE_URL is relative\n";
    echo "✗ Links may not work from subdirectories\n";
}

echo "\n=== SOLUTION ===\n";
echo "The links generated are:\n";
echo "- " . $base_url . '/calendar.php' . "\n";
echo "- " . $base_url . '/tasks.php' . "\n";
echo "- " . $base_url . '/chat.php' . "\n";
echo "\nThese should be absolute URLs starting with http://\n";
echo "If they're not working, check browser console for JavaScript errors.\n";
?>