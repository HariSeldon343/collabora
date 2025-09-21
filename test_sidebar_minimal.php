<?php
/**
 * Minimal Sidebar Test
 * Test the sidebar navigation links without any complex JavaScript
 */

session_start();
require_once 'config_v2.php';

// Simple authentication check
if (!isset($_SESSION['user_v2']) && !isset($_SESSION['user_name'])) {
    echo "<p style='color: red;'>Warning: No user session found. Navigation may redirect to login.</p>";
}

// Calculate base URL exactly like sidebar.php does
$base_url = rtrim(defined('BASE_URL') ? BASE_URL : (defined('APP_URL') ? APP_URL : '/Nexiosolution/collabora'), '/');
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minimal Sidebar Test - Nexio Solution</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .nav-item { margin: 10px 0; }
        .nav-link {
            display: inline-block;
            padding: 10px 15px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-right: 10px;
        }
        .nav-link:hover { background: #005a87; }
        .nav-link.active { background: #28a745; }
        .info { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .debug { background: #e9ecef; padding: 10px; border-radius: 3px; margin: 10px 0; font-family: monospace; }
    </style>
</head>
<body>
    <h1>Minimal Sidebar Navigation Test</h1>

    <div class="info">
        <h3>Test Information</h3>
        <p><strong>Current Page:</strong> <?php echo htmlspecialchars($currentPage); ?></p>
        <p><strong>Base URL:</strong> <?php echo htmlspecialchars($base_url); ?></p>
        <p><strong>User Session:</strong> <?php echo isset($_SESSION['user_v2']) || isset($_SESSION['user_name']) ? 'Found' : 'Not Found'; ?></p>
    </div>

    <h3>Navigation Links (Same as Sidebar)</h3>
    <div class="nav-item">
        <a href="<?php echo $base_url . '/index_v2.php'; ?>" class="nav-link <?php echo $currentPage === 'index_v2.php' ? 'active' : ''; ?>">
            Dashboard
        </a>
        <div class="debug">URL: <?php echo htmlspecialchars($base_url . '/index_v2.php'); ?></div>
    </div>

    <div class="nav-item">
        <a href="<?php echo $base_url . '/calendar.php'; ?>" class="nav-link <?php echo $currentPage === 'calendar.php' ? 'active' : ''; ?>">
            Calendario
        </a>
        <div class="debug">URL: <?php echo htmlspecialchars($base_url . '/calendar.php'); ?></div>
    </div>

    <div class="nav-item">
        <a href="<?php echo $base_url . '/tasks.php'; ?>" class="nav-link <?php echo $currentPage === 'tasks.php' ? 'active' : ''; ?>">
            Attività
        </a>
        <div class="debug">URL: <?php echo htmlspecialchars($base_url . '/tasks.php'); ?></div>
    </div>

    <div class="nav-item">
        <a href="<?php echo $base_url . '/chat.php'; ?>" class="nav-link <?php echo $currentPage === 'chat.php' ? 'active' : ''; ?>">
            Chat
        </a>
        <div class="debug">URL: <?php echo htmlspecialchars($base_url . '/chat.php'); ?></div>
    </div>

    <h3>Test Results</h3>
    <p>Click the navigation links above. If they work correctly, you should navigate to the target pages. If they refresh this page, there's an issue with:</p>
    <ul>
        <li>Authentication (user not logged in, causing redirects)</li>
        <li>Session configuration</li>
        <li>URL construction</li>
        <li>JavaScript interference (though this page has minimal JS)</li>
    </ul>

    <h3>Direct File Access Test</h3>
    <p>Try accessing files directly:</p>
    <div class="nav-item">
        <a href="calendar.php" class="nav-link">calendar.php (relative)</a>
        <a href="tasks.php" class="nav-link">tasks.php (relative)</a>
        <a href="chat.php" class="nav-link">chat.php (relative)</a>
    </div>

    <script>
        console.log('Minimal sidebar test loaded');
        console.log('Base URL:', <?php echo json_encode($base_url); ?>);

        // Log all link clicks to see what's happening
        document.addEventListener('click', function(e) {
            if (e.target.tagName === 'A') {
                console.log('Link clicked:', {
                    href: e.target.href,
                    text: e.target.textContent.trim(),
                    defaultPrevented: e.defaultPrevented
                });
            }
        });

        // Log any JavaScript errors
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
        });
    </script>
</body>
</html>