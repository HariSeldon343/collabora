<?php
/**
 * NEXIO COLLABORA - COMPREHENSIVE SYSTEM VERIFICATION
 * Final end-to-end verification script
 * Tests all components after JavaScript/PHP fixes
 *
 * @author Claude Code
 * @date 2025-09-20
 */

session_start();
require_once 'config_v2.php';
require_once 'includes/db.php';
require_once 'includes/SimpleAuth.php';

// Configuration
$BASE_PATH = rtrim(defined('BASE_URL') ? BASE_URL : '/Nexiosolution/collabora', '/');
$TIMESTAMP = date('Y-m-d H:i:s');

// Test Results Array
$results = [
    'overall_status' => 'TESTING',
    'timestamp' => $TIMESTAMP,
    'tests_run' => 0,
    'tests_passed' => 0,
    'tests_failed' => 0,
    'critical_errors' => [],
    'warnings' => [],
    'success_messages' => [],
    'categories' => []
];

/**
 * Add test result
 */
function addTestResult($category, $test_name, $status, $message, $details = null) {
    global $results;

    $results['tests_run']++;
    if ($status === 'PASS') {
        $results['tests_passed']++;
        $results['success_messages'][] = "✅ $test_name: $message";
    } else {
        $results['tests_failed']++;
        if ($status === 'CRITICAL') {
            $results['critical_errors'][] = "🔴 $test_name: $message";
        } else {
            $results['warnings'][] = "⚠️ $test_name: $message";
        }
    }

    if (!isset($results['categories'][$category])) {
        $results['categories'][$category] = [];
    }

    $results['categories'][$category][] = [
        'test' => $test_name,
        'status' => $status,
        'message' => $message,
        'details' => $details
    ];
}

/**
 * Test database connectivity and structure
 */
function testDatabase() {
    try {
        $pdo = getDbConnection();
        addTestResult('Database', 'Connection', 'PASS', 'Database connected successfully');

        // Check critical tables
        $tables = [
            'users' => 'User management',
            'tenants' => 'Multi-tenant system',
            'user_tenant_associations' => 'Tenant relationships',
            'files' => 'File management',
            'folders' => 'Folder structure',
            'calendars' => 'Calendar system',
            'events' => 'Event management',
            'tasks' => 'Task management',
            'chat_channels' => 'Chat system',
            'chat_messages' => 'Messaging'
        ];

        foreach ($tables as $table => $description) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                addTestResult('Database', "Table: $table", 'PASS', "$description table exists");
            } else {
                addTestResult('Database', "Table: $table", 'FAIL', "$description table missing");
            }
        }

        // Check admin user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
        $stmt->execute(['asamodeo@fortibyte.it']);
        if ($stmt->rowCount() > 0) {
            addTestResult('Database', 'Admin User', 'PASS', 'Default admin user exists');
        } else {
            addTestResult('Database', 'Admin User', 'CRITICAL', 'Default admin user missing');
        }

    } catch (Exception $e) {
        addTestResult('Database', 'Connection', 'CRITICAL', 'Database connection failed: ' . $e->getMessage());
    }
}

/**
 * Test authentication system
 */
function testAuthentication() {
    try {
        $auth = new SimpleAuth();
        addTestResult('Authentication', 'SimpleAuth Class', 'PASS', 'SimpleAuth loaded successfully');

        // Test login validation
        $result = $auth->login('asamodeo@fortibyte.it', 'Ricord@1991');
        if ($result['success']) {
            addTestResult('Authentication', 'Admin Login', 'PASS', 'Admin login successful');

            // Test session
            if (isset($_SESSION['user_id'])) {
                addTestResult('Authentication', 'Session', 'PASS', 'Session established correctly');
            } else {
                addTestResult('Authentication', 'Session', 'FAIL', 'Session not established');
            }

        } else {
            addTestResult('Authentication', 'Admin Login', 'CRITICAL', 'Admin login failed: ' . $result['message']);
        }

    } catch (Exception $e) {
        addTestResult('Authentication', 'SimpleAuth Class', 'CRITICAL', 'SimpleAuth error: ' . $e->getMessage());
    }
}

/**
 * Test API endpoints
 */
function testAPIEndpoints() {
    global $BASE_PATH;

    $endpoints = [
        '/api/auth_simple.php' => 'Simple Authentication API',
        '/api/auth_v2.php' => 'Advanced Authentication API',
        '/api/files.php' => 'File Management API',
        '/api/calendars.php' => 'Calendar API',
        '/api/events.php' => 'Events API',
        '/api/tasks.php' => 'Tasks API',
        '/api/messages.php' => 'Chat Messages API',
        '/api/channels.php' => 'Chat Channels API'
    ];

    foreach ($endpoints as $endpoint => $description) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . $endpoint;

        if (file_exists($full_path)) {
            // Check for PHP syntax errors
            $output = shell_exec("php -l \"$full_path\" 2>&1");
            if (strpos($output, 'No syntax errors') !== false) {
                addTestResult('API', basename($endpoint), 'PASS', "$description - File exists and valid syntax");
            } else {
                addTestResult('API', basename($endpoint), 'FAIL', "$description - Syntax error: $output");
            }
        } else {
            addTestResult('API', basename($endpoint), 'FAIL', "$description - File not found");
        }
    }
}

/**
 * Test main pages
 */
function testMainPages() {
    global $BASE_PATH;

    $pages = [
        '/index_v2.php' => 'Main Entry Point',
        '/dashboard.php' => 'User Dashboard',
        '/home_v2.php' => 'Home Page',
        '/calendar.php' => 'Calendar Interface',
        '/tasks.php' => 'Task Management',
        '/chat.php' => 'Chat Interface',
        '/admin/index.php' => 'Admin Dashboard',
        '/admin/users.php' => 'User Management'
    ];

    foreach ($pages as $page => $description) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . $page;

        if (file_exists($full_path)) {
            // Check for basic PHP syntax
            $output = shell_exec("php -l \"$full_path\" 2>&1");
            if (strpos($output, 'No syntax errors') !== false) {
                addTestResult('Pages', basename($page, '.php'), 'PASS', "$description - Valid syntax");
            } else {
                addTestResult('Pages', basename($page, '.php'), 'FAIL', "$description - Syntax error");
            }
        } else {
            addTestResult('Pages', basename($page, '.php'), 'FAIL', "$description - File not found");
        }
    }
}

/**
 * Test JavaScript files for export issues
 */
function testJavaScriptFiles() {
    global $BASE_PATH;

    $js_files = [
        '/assets/js/auth_v2.js' => 'Authentication JS',
        '/assets/js/post-login-config.js' => 'Post-Login Config',
        '/assets/js/post-login-handler.js' => 'Post-Login Handler',
        '/assets/js/filemanager.js' => 'File Manager',
        '/assets/js/components.js' => 'UI Components',
        '/assets/js/calendar.js' => 'Calendar JS',
        '/assets/js/tasks.js' => 'Tasks JS',
        '/assets/js/chat.js' => 'Chat JS'
    ];

    foreach ($js_files as $file => $description) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . $file;

        if (file_exists($full_path)) {
            $content = file_get_contents($full_path);

            // Check for ES6 export statements that cause errors
            if (preg_match('/^export\s+/m', $content)) {
                addTestResult('JavaScript', basename($file, '.js'), 'FAIL', "$description - Contains ES6 export statements");
            } else {
                addTestResult('JavaScript', basename($file, '.js'), 'PASS', "$description - No ES6 export issues");
            }

            // Check for window assignments (correct pattern)
            if (preg_match('/window\.\w+\s*=/', $content)) {
                addTestResult('JavaScript', basename($file, '.js') . '_window', 'PASS', "$description - Uses window assignment pattern");
            }

        } else {
            addTestResult('JavaScript', basename($file, '.js'), 'WARN', "$description - File not found (optional)");
        }
    }
}

/**
 * Test CSS and assets
 */
function testAssets() {
    global $BASE_PATH;

    $assets = [
        '/assets/css/main.css' => 'Main Stylesheet',
        '/assets/css/auth.css' => 'Authentication Styles',
        '/assets/css/calendar.css' => 'Calendar Styles',
        '/assets/css/tasks.css' => 'Tasks Styles',
        '/assets/css/chat.css' => 'Chat Styles',
        '/assets/js/vendor/chart.min.js' => 'Chart.js Library (Local)'
    ];

    foreach ($assets as $asset => $description) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . $asset;

        if (file_exists($full_path)) {
            addTestResult('Assets', basename($asset), 'PASS', "$description - Available");
        } else {
            addTestResult('Assets', basename($asset), 'WARN', "$description - Missing (may be optional)");
        }
    }
}

/**
 * Test configuration files
 */
function testConfiguration() {
    global $BASE_PATH;

    // Test config_v2.php
    if (defined('BASE_URL')) {
        addTestResult('Configuration', 'BASE_URL', 'PASS', 'BASE_URL defined: ' . BASE_URL);
    } else {
        addTestResult('Configuration', 'BASE_URL', 'FAIL', 'BASE_URL not defined');
    }

    if (defined('DB_HOST')) {
        addTestResult('Configuration', 'Database Config', 'PASS', 'Database configuration present');
    } else {
        addTestResult('Configuration', 'Database Config', 'FAIL', 'Database configuration missing');
    }

    // Test autoloader
    $autoloader_path = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . '/includes/autoload.php';
    if (file_exists($autoloader_path)) {
        addTestResult('Configuration', 'Autoloader', 'PASS', 'PSR-4 autoloader available');
    } else {
        addTestResult('Configuration', 'Autoloader', 'FAIL', 'Autoloader missing');
    }
}

/**
 * Test URL patterns and concatenation
 */
function testURLPatterns() {
    global $BASE_PATH;

    // Test files that use URL concatenation
    $files_to_check = [
        '/components/sidebar.php',
        '/admin/index.php',
        '/dashboard.php',
        '/home_v2.php'
    ];

    foreach ($files_to_check as $file) {
        $full_path = $_SERVER['DOCUMENT_ROOT'] . $BASE_PATH . $file;

        if (file_exists($full_path)) {
            $content = file_get_contents($full_path);

            // Check for problematic patterns
            if (preg_match('/echo\s+BASE_URL\s*;\s*\?>\s*\//', $content)) {
                addTestResult('URL Patterns', basename($file), 'FAIL', 'Uses problematic BASE_URL concatenation');
            } else {
                addTestResult('URL Patterns', basename($file), 'PASS', 'Uses correct URL concatenation');
            }
        }
    }
}

/**
 * Generate comprehensive report
 */
function generateReport() {
    global $results;

    $results['overall_status'] = ($results['tests_failed'] == 0) ? 'PASS' :
                                (count($results['critical_errors']) > 0 ? 'CRITICAL' : 'WARN');

    $html = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Nexio Collabora - Final System Verification</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .status-pass { color: #059669; font-weight: bold; }
        .status-warn { color: #d97706; font-weight: bold; }
        .status-critical { color: #dc2626; font-weight: bold; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .summary-card { background: #f8fafc; padding: 20px; border-radius: 6px; text-align: center; }
        .category { margin: 20px 0; }
        .category h3 { background: #111827; color: white; padding: 10px; margin: 0; border-radius: 6px 6px 0 0; }
        .test-results { background: #f9fafb; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 6px 6px; padding: 15px; }
        .test-item { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .test-item:last-child { border-bottom: none; }
        .test-status { font-weight: bold; margin-right: 10px; }
        .messages { margin: 20px 0; }
        .message { padding: 10px; margin: 5px 0; border-radius: 4px; }
        .error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .warning { background: #fffbeb; border: 1px solid #fed7aa; color: #92400e; }
        .success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .timestamp { text-align: center; color: #6b7280; margin-top: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔍 Nexio Collabora - Final System Verification</h1>
            <h2 class='status-" . strtolower($results['overall_status']) . "'>
                Overall Status: " . $results['overall_status'] . "
            </h2>
        </div>

        <div class='summary'>
            <div class='summary-card'>
                <h3>Tests Run</h3>
                <div style='font-size: 24px; font-weight: bold;'>" . $results['tests_run'] . "</div>
            </div>
            <div class='summary-card'>
                <h3>Passed</h3>
                <div style='font-size: 24px; font-weight: bold; color: #059669;'>" . $results['tests_passed'] . "</div>
            </div>
            <div class='summary-card'>
                <h3>Failed</h3>
                <div style='font-size: 24px; font-weight: bold; color: #dc2626;'>" . $results['tests_failed'] . "</div>
            </div>
            <div class='summary-card'>
                <h3>Success Rate</h3>
                <div style='font-size: 24px; font-weight: bold;'>" .
                ($results['tests_run'] > 0 ? round(($results['tests_passed'] / $results['tests_run']) * 100, 1) : 0) . "%</div>
            </div>
        </div>";

    // Critical Errors
    if (!empty($results['critical_errors'])) {
        $html .= "<div class='messages'>
            <h3>🔴 Critical Errors (Must Fix)</h3>";
        foreach ($results['critical_errors'] as $error) {
            $html .= "<div class='message error'>$error</div>";
        }
        $html .= "</div>";
    }

    // Warnings
    if (!empty($results['warnings'])) {
        $html .= "<div class='messages'>
            <h3>⚠️ Warnings (Should Review)</h3>";
        foreach ($results['warnings'] as $warning) {
            $html .= "<div class='message warning'>$warning</div>";
        }
        $html .= "</div>";
    }

    // Success Messages
    if (!empty($results['success_messages'])) {
        $html .= "<div class='messages'>
            <h3>✅ Successful Tests</h3>";
        foreach ($results['success_messages'] as $success) {
            $html .= "<div class='message success'>$success</div>";
        }
        $html .= "</div>";
    }

    // Detailed Results by Category
    $html .= "<h2>📋 Detailed Test Results</h2>";
    foreach ($results['categories'] as $category => $tests) {
        $html .= "<div class='category'>
            <h3>$category</h3>
            <div class='test-results'>";

        foreach ($tests as $test) {
            $status_class = 'status-' . strtolower($test['status']);
            $html .= "<div class='test-item'>
                <span class='test-status $status_class'>[" . $test['status'] . "]</span>
                <strong>" . $test['test'] . ":</strong> " . $test['message'];
            if ($test['details']) {
                $html .= "<br><small style='color: #6b7280;'>Details: " . $test['details'] . "</small>";
            }
            $html .= "</div>";
        }

        $html .= "</div></div>";
    }

    $html .= "<div class='timestamp'>Generated on: " . $results['timestamp'] . "</div>
    </div>
</body>
</html>";

    return $html;
}

// Run all tests
testDatabase();
testAuthentication();
testAPIEndpoints();
testMainPages();
testJavaScriptFiles();
testAssets();
testConfiguration();
testURLPatterns();

// Output results
echo generateReport();
?>