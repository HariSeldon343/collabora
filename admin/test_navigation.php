<?php
/**
 * Test Navigation from Admin Directory
 * This file is in the /admin/ subdirectory to test navigation from this context
 */

// Start session
session_start();

// Set up test session if not logged in
if (!isset($_SESSION['user_v2'])) {
    $_SESSION['user_v2'] = [
        'id' => 1,
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'role' => 'admin'
    ];
    $_SESSION['user_role'] = 'admin';
    $_SESSION['user_name'] = 'Test Admin';
    $_SESSION['current_tenant_id'] = 1;
    $_SESSION['tenants'] = [
        ['id' => 1, 'name' => 'Test Tenant', 'code' => 'TEST']
    ];
}

// Include config from parent directory
require_once '../config_v2.php';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Test from Admin Directory</title>
    <link rel="stylesheet" href="../assets/css/auth_v2.css">
    <style>
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .test-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            margin-top: 0;
            color: #111827;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }
        .code-block {
            background: #f3f4f6;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
            white-space: pre-wrap;
        }
        .success {
            color: #059669;
            font-weight: bold;
        }
        .error {
            color: #dc2626;
            font-weight: bold;
        }
        .warning {
            color: #f59e0b;
            font-weight: bold;
        }
        .link-list {
            list-style: none;
            padding: 0;
        }
        .link-list li {
            margin: 10px 0;
            padding: 10px;
            background: #f9fafb;
            border-radius: 4px;
        }
        .link-list a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }
        .link-list a:hover {
            text-decoration: underline;
        }
        .link-url {
            color: #6b7280;
            font-size: 0.875rem;
            display: block;
            margin-top: 5px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Include the actual sidebar -->
        <?php include '../components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="test-container">
                <h1>Navigation Test from Admin Directory</h1>
                <p>This page is located at: <code>/admin/test_navigation.php</code></p>

                <!-- Test 1: Environment Check -->
                <div class="test-section">
                    <h2>1. Environment Information</h2>
                    <div class="code-block"><?php
                        echo "Current Script: " . $_SERVER['PHP_SELF'] . "\n";
                        echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
                        echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
                        echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
                        echo "BASE_URL constant: " . BASE_URL . "\n";
                    ?></div>
                </div>

                <!-- Test 2: Base URL Calculation -->
                <div class="test-section">
                    <h2>2. Base URL Calculation</h2>
                    <div class="code-block"><?php
                        // Show how base_url is calculated (from sidebar)
                        echo "BASE_URL is defined: " . (defined('BASE_URL') ? 'Yes' : 'No') . "\n";
                        echo "BASE_URL value: " . BASE_URL . "\n";
                        echo "Contains http://: " . (strpos(BASE_URL, 'http://') === 0 ? 'Yes' : 'No') . "\n";
                        echo "Contains https://: " . (strpos(BASE_URL, 'https://') === 0 ? 'Yes' : 'No') . "\n";
                        echo "\n\$base_url calculated as: " . $base_url . "\n";
                    ?></div>
                </div>

                <!-- Test 3: Generated Links -->
                <div class="test-section">
                    <h2>3. Navigation Links Generated</h2>
                    <p>These are the actual links that the sidebar generates:</p>
                    <ul class="link-list">
                        <?php
                        $test_links = [
                            'Dashboard' => $base_url . '/index_v2.php',
                            'Calendar' => $base_url . '/calendar.php',
                            'Tasks' => $base_url . '/tasks.php',
                            'Chat' => $base_url . '/chat.php',
                            'Files' => $base_url . '/files.php',
                            'Admin Dashboard' => $base_url . '/admin/index.php',
                            'User Management' => $base_url . '/admin/users.php',
                        ];

                        foreach ($test_links as $name => $url):
                        ?>
                        <li>
                            <a href="<?php echo $url; ?>"><?php echo $name; ?></a>
                            <span class="link-url"><?php echo htmlspecialchars($url); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Test 4: File Existence -->
                <div class="test-section">
                    <h2>4. Target Files Check</h2>
                    <ul>
                        <?php
                        $files_to_check = [
                            '../calendar.php' => 'Calendar page',
                            '../tasks.php' => 'Tasks page',
                            '../chat.php' => 'Chat page',
                            '../files.php' => 'Files page',
                            '../index_v2.php' => 'Dashboard',
                            'index.php' => 'Admin Dashboard',
                            'users.php' => 'User Management',
                        ];

                        foreach ($files_to_check as $file => $description):
                            $exists = file_exists(__DIR__ . '/' . $file);
                        ?>
                        <li>
                            <?php if ($exists): ?>
                                <span class="success">✓</span>
                            <?php else: ?>
                                <span class="error">✗</span>
                            <?php endif; ?>
                            <?php echo $description; ?> (<?php echo $file; ?>)
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Test 5: JavaScript Navigation Test -->
                <div class="test-section">
                    <h2>5. JavaScript Navigation Test</h2>
                    <p>Click the buttons below to test navigation via JavaScript:</p>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <button onclick="testNavigation('<?php echo $base_url . '/calendar.php'; ?>')">Go to Calendar</button>
                        <button onclick="testNavigation('<?php echo $base_url . '/tasks.php'; ?>')">Go to Tasks</button>
                        <button onclick="testNavigation('<?php echo $base_url . '/chat.php'; ?>')">Go to Chat</button>
                    </div>
                    <div id="nav-result" style="margin-top: 10px;"></div>
                </div>

                <!-- Test 6: Diagnosis -->
                <div class="test-section">
                    <h2>6. Diagnosis</h2>
                    <?php
                    $issues = [];

                    // Check if BASE_URL is properly formed
                    if (strpos(BASE_URL, 'http') !== 0) {
                        $issues[] = "BASE_URL doesn't include protocol (http/https)";
                    }

                    // Check if base_url is absolute
                    if (strpos($base_url, 'http') !== 0 && strpos($base_url, '/') !== 0) {
                        $issues[] = "Generated URLs are not absolute";
                    }

                    // Check file existence
                    if (!file_exists(__DIR__ . '/../calendar.php')) {
                        $issues[] = "calendar.php file not found";
                    }
                    if (!file_exists(__DIR__ . '/../tasks.php')) {
                        $issues[] = "tasks.php file not found";
                    }
                    if (!file_exists(__DIR__ . '/../chat.php')) {
                        $issues[] = "chat.php file not found";
                    }

                    if (empty($issues)):
                    ?>
                        <p class="success">✓ All checks passed! Navigation should work correctly.</p>
                        <p>The sidebar is generating absolute URLs that will work from any directory.</p>
                    <?php else: ?>
                        <p class="error">Issues detected:</p>
                        <ul>
                            <?php foreach ($issues as $issue): ?>
                            <li><?php echo $issue; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Test 7: Manual Test Instructions -->
                <div class="test-section">
                    <h2>7. Manual Testing Instructions</h2>
                    <ol>
                        <li>Check the sidebar on the left - it should be visible and styled correctly</li>
                        <li>Click on "Calendario" in the sidebar - it should navigate to the Calendar page</li>
                        <li>Click on "Attività" in the sidebar - it should navigate to the Tasks page</li>
                        <li>Click on "Chat" in the sidebar - it should navigate to the Chat page</li>
                        <li>All navigation should work without 404 errors</li>
                    </ol>
                    <p><strong>Expected Result:</strong> All links should navigate correctly to their respective pages.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    function testNavigation(url) {
        const resultDiv = document.getElementById('nav-result');
        resultDiv.innerHTML = `<p>Testing navigation to: <code>${url}</code></p>`;

        // Test if URL is reachable
        fetch(url, { method: 'HEAD' })
            .then(response => {
                if (response.ok) {
                    resultDiv.innerHTML += `<p class="success">✓ URL is reachable (Status: ${response.status})</p>`;
                    resultDiv.innerHTML += `<p>Click <a href="${url}">here</a> to navigate to the page.</p>`;
                } else {
                    resultDiv.innerHTML += `<p class="error">✗ URL returned error (Status: ${response.status})</p>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML += `<p class="error">✗ Failed to reach URL: ${error.message}</p>`;
            });
    }

    // Log navigation info to console
    console.log('Navigation Test Information:');
    console.log('BASE_URL:', '<?php echo BASE_URL; ?>');
    console.log('Calculated base_url:', '<?php echo $base_url; ?>');
    console.log('Current location:', window.location.href);
    console.log('Navigation links:', <?php echo json_encode($test_links); ?>);
    </script>
</body>
</html>