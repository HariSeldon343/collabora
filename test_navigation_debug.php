<?php
/**
 * Navigation Debug Test
 * This script helps identify why navigation links might not be working
 */

session_start();
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

// Check authentication
$auth = new SimpleAuth();
$isAuthenticated = $auth->isAuthenticated();

// Get session data
$sessionData = $_SESSION ?? [];

// Get current page info
$currentPage = basename($_SERVER['PHP_SELF']);
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';

// Get BASE_URL calculation
$base_url = rtrim(defined('BASE_URL') ? BASE_URL : (defined('APP_URL') ? APP_URL : '/Nexiosolution/collabora'), '/');

// Check if target files exist
$targetFiles = ['calendar.php', 'tasks.php', 'chat.php'];
$fileStatus = [];
foreach ($targetFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $fileStatus[$file] = [
        'exists' => file_exists($fullPath),
        'readable' => is_readable($fullPath),
        'size' => file_exists($fullPath) ? filesize($fullPath) : 0,
        'url' => $base_url . '/' . $file
    ];
}

// Check for potential JavaScript issues
$jsFiles = [
    'assets/js/app.js',
    'assets/js/components.js',
    'assets/js/auth_v2.js'
];
$jsStatus = [];
foreach ($jsFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $jsStatus[$file] = [
        'exists' => file_exists($fullPath),
        'readable' => is_readable($fullPath),
        'size' => file_exists($fullPath) ? filesize($fullPath) : 0
    ];
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Debug Test - Nexio Solution</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin-bottom: 30px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; }
        .status-ok { color: green; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
        .status-warning { color: orange; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f5f5f5; }
        .test-links { margin: 10px 0; }
        .test-links a { display: inline-block; margin: 5px 10px 5px 0; padding: 10px 15px; background: #007cba; color: white; text-decoration: none; border-radius: 3px; }
        .test-links a:hover { background: #005a87; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Navigation Debug Test</h1>
    <p>This test helps identify why navigation links to calendar.php, tasks.php, and chat.php might not be working correctly.</p>

    <div class="section">
        <h3>Authentication Status</h3>
        <p><strong>Is Authenticated:</strong> <span class="<?php echo $isAuthenticated ? 'status-ok' : 'status-error'; ?>"><?php echo $isAuthenticated ? 'YES' : 'NO'; ?></span></p>
        <?php if ($isAuthenticated): ?>
            <?php $user = $auth->getCurrentUser(); ?>
            <p><strong>User:</strong> <?php echo htmlspecialchars($user['name'] ?? 'Unknown'); ?> (<?php echo htmlspecialchars($user['email'] ?? 'Unknown'); ?>)</p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role'] ?? 'Unknown'); ?></p>
            <?php $tenant = $auth->getCurrentTenant(); ?>
            <p><strong>Current Tenant:</strong> <?php echo htmlspecialchars($tenant['name'] ?? 'None'); ?></p>
        <?php else: ?>
            <p class="status-error">User is not authenticated. This would cause redirects to index_v2.php</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h3>Environment Information</h3>
        <p><strong>Current Page:</strong> <?php echo htmlspecialchars($currentPage); ?></p>
        <p><strong>Request URI:</strong> <?php echo htmlspecialchars($requestUri); ?></p>
        <p><strong>HTTP Host:</strong> <?php echo htmlspecialchars($httpHost); ?></p>
        <p><strong>Protocol:</strong> <?php echo htmlspecialchars($protocol); ?></p>
        <p><strong>Calculated BASE_URL:</strong> <?php echo htmlspecialchars($base_url); ?></p>
        <p><strong>Defined BASE_URL:</strong> <?php echo defined('BASE_URL') ? htmlspecialchars(BASE_URL) : 'NOT DEFINED'; ?></p>
    </div>

    <div class="section">
        <h3>Target Files Status</h3>
        <table>
            <tr>
                <th>File</th>
                <th>Exists</th>
                <th>Readable</th>
                <th>Size (bytes)</th>
                <th>Constructed URL</th>
            </tr>
            <?php foreach ($fileStatus as $file => $status): ?>
            <tr>
                <td><?php echo htmlspecialchars($file); ?></td>
                <td class="<?php echo $status['exists'] ? 'status-ok' : 'status-error'; ?>"><?php echo $status['exists'] ? 'YES' : 'NO'; ?></td>
                <td class="<?php echo $status['readable'] ? 'status-ok' : 'status-error'; ?>"><?php echo $status['readable'] ? 'YES' : 'NO'; ?></td>
                <td><?php echo number_format($status['size']); ?></td>
                <td><?php echo htmlspecialchars($status['url']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h3>JavaScript Files Status</h3>
        <table>
            <tr>
                <th>File</th>
                <th>Exists</th>
                <th>Readable</th>
                <th>Size (bytes)</th>
            </tr>
            <?php foreach ($jsStatus as $file => $status): ?>
            <tr>
                <td><?php echo htmlspecialchars($file); ?></td>
                <td class="<?php echo $status['exists'] ? 'status-ok' : 'status-error'; ?>"><?php echo $status['exists'] ? 'YES' : 'NO'; ?></td>
                <td class="<?php echo $status['readable'] ? 'status-ok' : 'status-error'; ?>"><?php echo $status['readable'] ? 'YES' : 'NO'; ?></td>
                <td><?php echo number_format($status['size']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h3>Test Navigation Links</h3>
        <p>Click these links to test navigation. If they refresh this page instead of navigating, there's an issue:</p>
        <div class="test-links">
            <a href="<?php echo $base_url . '/calendar.php'; ?>" target="_blank">Calendar.php (New Tab)</a>
            <a href="<?php echo $base_url . '/tasks.php'; ?>" target="_blank">Tasks.php (New Tab)</a>
            <a href="<?php echo $base_url . '/chat.php'; ?>" target="_blank">Chat.php (New Tab)</a>
        </div>
        <div class="test-links">
            <a href="calendar.php">Calendar.php (Same Tab)</a>
            <a href="tasks.php">Tasks.php (Same Tab)</a>
            <a href="chat.php">Chat.php (Same Tab)</a>
        </div>
    </div>

    <div class="section">
        <h3>Session Data</h3>
        <pre><?php echo htmlspecialchars(print_r($sessionData, true)); ?></pre>
    </div>

    <div class="section">
        <h3>Potential Issues and Solutions</h3>
        <ol>
            <li><strong>Authentication Issue:</strong> If user is not authenticated, all three files will redirect to index_v2.php</li>
            <li><strong>JavaScript Interference:</strong> Check browser console for JavaScript errors that might prevent navigation</li>
            <li><strong>URL Issues:</strong> Incorrect BASE_URL calculation could cause wrong links</li>
            <li><strong>Session Issues:</strong> Expired or invalid sessions will cause redirects</li>
            <li><strong>Browser Cache:</strong> Old cached files might interfere with navigation</li>
        </ol>
    </div>

    <script>
        console.log('Navigation Debug Test loaded');
        console.log('BASE_URL from PHP:', <?php echo json_encode($base_url); ?>);
        console.log('Authentication status:', <?php echo $isAuthenticated ? 'true' : 'false'; ?>);

        // Check for JavaScript errors
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
        });

        // Test if any global JavaScript is interfering with link clicks
        document.addEventListener('click', function(e) {
            if (e.target.tagName === 'A') {
                console.log('Link clicked:', e.target.href);
                console.log('Default prevented:', e.defaultPrevented);
            }
        });
    </script>
</body>
</html>