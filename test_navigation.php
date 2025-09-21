<?php
session_start();
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

// Check if user is logged in
$auth = new SimpleAuth();
$isAuthenticated = $auth->isAuthenticated();

// Get base URL for proper link generation
$base_url = rtrim(defined('BASE_URL') ? BASE_URL : '/Nexiosolution/collabora', '/');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Test - Nexio Solution</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f3f4f6;
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #111827;
            margin-bottom: 30px;
        }
        .status-box {
            background: #f9fafb;
            border-left: 4px solid #3b82f6;
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 0 4px 4px 0;
        }
        .status-box.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .status-box.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .nav-section {
            margin-bottom: 30px;
        }
        .nav-section h2 {
            color: #374151;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .nav-links {
            display: grid;
            gap: 10px;
        }
        .nav-link {
            display: inline-block;
            padding: 12px 20px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            transition: background 0.2s;
        }
        .nav-link:hover {
            background: #2563eb;
        }
        .nav-link.secondary {
            background: #6b7280;
        }
        .nav-link.secondary:hover {
            background: #4b5563;
        }
        .info-box {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .info-box code {
            background: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
        }
        .test-results {
            margin-top: 30px;
        }
        .test-item {
            padding: 10px 15px;
            background: #f9fafb;
            margin-bottom: 8px;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .test-item.success {
            background: #f0fdf4;
            color: #065f46;
        }
        .test-item.error {
            background: #fef2f2;
            color: #7f1d1d;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            background: #e5e7eb;
            color: #374151;
        }
        .badge.success {
            background: #10b981;
            color: white;
        }
        .badge.error {
            background: #ef4444;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Navigation Link Test</h1>

        <?php if ($isAuthenticated): ?>
            <div class="status-box success">
                <strong>✅ Authentication Status:</strong> Logged in as <?php echo htmlspecialchars($auth->getCurrentUser()['email']); ?>
            </div>
        <?php else: ?>
            <div class="status-box error">
                <strong>⚠️ Authentication Status:</strong> Not logged in
                <br>Please <a href="<?php echo $base_url . '/index_v2.php'; ?>">login</a> first to test navigation.
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>Base URL:</strong> <code><?php echo $base_url; ?></code><br>
            <strong>Session ID:</strong> <code><?php echo session_id(); ?></code><br>
            <strong>Request URI:</strong> <code><?php echo $_SERVER['REQUEST_URI']; ?></code>
        </div>

        <div class="nav-section">
            <h2>Direct Navigation Links (Fixed)</h2>
            <div class="nav-links">
                <a href="<?php echo $base_url . '/calendar.php'; ?>" class="nav-link">
                    📅 Go to Calendar
                </a>
                <a href="<?php echo $base_url . '/tasks.php'; ?>" class="nav-link">
                    ✅ Go to Tasks
                </a>
                <a href="<?php echo $base_url . '/chat.php'; ?>" class="nav-link">
                    💬 Go to Chat
                </a>
                <a href="<?php echo $base_url . '/index_v2.php'; ?>" class="nav-link secondary">
                    🏠 Go to Dashboard
                </a>
            </div>
        </div>

        <div class="test-results">
            <h2>File System Check</h2>
            <?php
            $files_to_check = [
                'calendar.php' => '/mnt/c/xampp/htdocs/Nexiosolution/collabora/calendar.php',
                'tasks.php' => '/mnt/c/xampp/htdocs/Nexiosolution/collabora/tasks.php',
                'chat.php' => '/mnt/c/xampp/htdocs/Nexiosolution/collabora/chat.php',
                'components/sidebar.php' => '/mnt/c/xampp/htdocs/Nexiosolution/collabora/components/sidebar.php',
                'includes/SimpleAuth.php' => '/mnt/c/xampp/htdocs/Nexiosolution/collabora/includes/SimpleAuth.php',
                'config_v2.php' => '/mnt/c/xampp/htdocs/Nexiosolution/collabora/config_v2.php'
            ];

            foreach ($files_to_check as $name => $path) {
                if (file_exists($path)) {
                    $size = filesize($path);
                    $modified = date('Y-m-d H:i:s', filemtime($path));
                    echo '<div class="test-item success">';
                    echo '<span>✅ ' . $name . ' (' . number_format($size) . ' bytes, modified: ' . $modified . ')</span>';
                    echo '<span class="badge success">EXISTS</span>';
                    echo '</div>';
                } else {
                    echo '<div class="test-item error">';
                    echo '<span>❌ ' . $name . '</span>';
                    echo '<span class="badge error">MISSING</span>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <div class="test-results">
            <h2>URL Generation Test</h2>
            <?php
            $test_urls = [
                'Calendar' => $base_url . '/calendar.php',
                'Tasks' => $base_url . '/tasks.php',
                'Chat' => $base_url . '/chat.php',
                'Dashboard' => $base_url . '/index_v2.php'
            ];

            foreach ($test_urls as $name => $url) {
                echo '<div class="test-item">';
                echo '<span><strong>' . $name . ':</strong> ' . htmlspecialchars($url) . '</span>';
                echo '</div>';
            }
            ?>
        </div>

        <div class="test-results">
            <h2>JavaScript Navigation Test</h2>
            <button onclick="testNavigation('calendar.php')" class="nav-link">Test Calendar Navigation</button>
            <button onclick="testNavigation('tasks.php')" class="nav-link">Test Tasks Navigation</button>
            <button onclick="testNavigation('chat.php')" class="nav-link">Test Chat Navigation</button>
            <div id="js-test-result" style="margin-top: 15px;"></div>
        </div>
    </div>

    <script>
        // Get base URL from PHP
        const baseUrl = '<?php echo $base_url; ?>';

        function testNavigation(page) {
            const targetUrl = baseUrl + '/' + page;
            const resultDiv = document.getElementById('js-test-result');

            resultDiv.innerHTML = `
                <div class="info-box">
                    <strong>Testing navigation to:</strong> <code>${targetUrl}</code><br>
                    <strong>Window location will change in 2 seconds...</strong>
                </div>
            `;

            // Test the navigation after a delay
            setTimeout(() => {
                window.location.href = targetUrl;
            }, 2000);
        }

        // Check if there's a redirect loop
        if (window.performance && window.performance.navigation) {
            const navType = window.performance.navigation.type;
            if (navType === 0) {
                console.log('Navigation type: Navigate (TYPE_NAVIGATE)');
            } else if (navType === 1) {
                console.log('Navigation type: Reload (TYPE_RELOAD)');
            } else if (navType === 2) {
                console.log('Navigation type: History (TYPE_BACK_FORWARD)');
            }
        }

        // Log current URL for debugging
        console.log('Current URL:', window.location.href);
        console.log('Base URL from PHP:', baseUrl);
    </script>
</body>
</html>