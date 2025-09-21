<?php
/**
 * Comprehensive End-to-End Validation Script
 * Tests all system components for the final validation
 *
 * COMPREHENSIVE TEST COVERAGE:
 * - Authentication System
 * - UI Consistency
 * - API Functionality
 * - JavaScript Errors
 * - Responsive Design
 * - Multi-tenant Isolation
 * - Database Connectivity
 */

// Initialize session and error reporting
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'config_v2.php';
require_once 'includes/db.php';
require_once 'includes/SimpleAuth.php';

// HTML Header
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexio Collabora - End-to-End Validation</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .section { margin: 20px 0; padding: 15px; border-left: 4px solid #2563eb; background: #f8fafc; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 15px 0; }
        .test-item { padding: 12px; border-radius: 8px; background: white; border: 1px solid #e5e7eb; }
        .pass { background: #dcfce7; border-color: #22c55e; color: #15803d; }
        .fail { background: #fef2f2; border-color: #ef4444; color: #dc2626; }
        .warning { background: #fef3c7; border-color: #f59e0b; color: #92400e; }
        .info { background: #e0f2fe; border-color: #0ea5e9; color: #0369a1; }
        .summary { margin: 20px 0; padding: 20px; background: #f9fafb; border-radius: 8px; border: 2px solid #e5e7eb; }
        .test-url { font-family: monospace; font-size: 12px; color: #6b7280; }
        .credentials { background: #fffbeb; padding: 15px; border-radius: 8px; border: 1px solid #f59e0b; margin: 15px 0; }
        .manual-tests { background: #f0fdf4; padding: 15px; border-radius: 8px; border: 1px solid #22c55e; margin: 15px 0; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        .action-btn { display: inline-block; padding: 8px 16px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; margin: 5px; }
        .action-btn:hover { background: #1d4ed8; }
        .status-indicator { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 8px; }
        .status-pass { background: #22c55e; }
        .status-fail { background: #ef4444; }
        .status-warning { background: #f59e0b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Nexio Collabora - End-to-End Validation</h1>
            <p>Comprehensive system validation for final delivery</p>
            <div class="credentials">
                <strong>🔐 Test Credentials:</strong><br>
                Email: <code>asamodeo@fortibyte.it</code><br>
                Password: <code>Ricord@1991</code>
            </div>
        </div>

<?php

// Test counter
$totalTests = 0;
$passedTests = 0;
$failedTests = 0;
$warnings = 0;

// Helper function to run a test
function runTest($testName, $testFunction) {
    global $totalTests, $passedTests, $failedTests, $warnings;
    $totalTests++;

    try {
        $result = $testFunction();
        if ($result['status'] === 'pass') {
            $passedTests++;
            echo "<div class='test-item pass'>";
            echo "<span class='status-indicator status-pass'></span>";
            echo "<strong>✅ {$testName}</strong><br>";
            echo $result['message'];
        } elseif ($result['status'] === 'warning') {
            $warnings++;
            echo "<div class='test-item warning'>";
            echo "<span class='status-indicator status-warning'></span>";
            echo "<strong>⚠️ {$testName}</strong><br>";
            echo $result['message'];
        } else {
            $failedTests++;
            echo "<div class='test-item fail'>";
            echo "<span class='status-indicator status-fail'></span>";
            echo "<strong>❌ {$testName}</strong><br>";
            echo $result['message'];
        }
        echo "</div>";
    } catch (Exception $e) {
        $failedTests++;
        echo "<div class='test-item fail'>";
        echo "<span class='status-indicator status-fail'></span>";
        echo "<strong>❌ {$testName}</strong><br>";
        echo "Exception: " . $e->getMessage();
        echo "</div>";
    }
}

// ========== 1. DATABASE CONNECTIVITY TESTS ==========
echo "<div class='section'>";
echo "<h2>🗄️ Database Connectivity Tests</h2>";
echo "<div class='test-grid'>";

runTest("Database Connection", function() {
    try {
        $db = getDbConnection();
        return ['status' => 'pass', 'message' => 'Database connection successful'];
    } catch (Exception $e) {
        return ['status' => 'fail', 'message' => 'Database connection failed: ' . $e->getMessage()];
    }
});

runTest("Admin User Exists", function() {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare("SELECT id, email, role FROM users WHERE email = ?");
        $stmt->execute(['asamodeo@fortibyte.it']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            return ['status' => 'pass', 'message' => "Admin user found: {$user['email']} (Role: {$user['role']})"];
        } else {
            return ['status' => 'fail', 'message' => 'Admin user not found in database'];
        }
    } catch (Exception $e) {
        return ['status' => 'fail', 'message' => 'Error checking admin user: ' . $e->getMessage()];
    }
});

runTest("Multi-tenant Data", function() {
    try {
        $db = getDbConnection();
        $stmt = $db->query("SELECT COUNT(*) as count FROM tenants");
        $tenantCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($tenantCount > 0) {
            return ['status' => 'pass', 'message' => "Found {$tenantCount} tenants in system"];
        } else {
            return ['status' => 'warning', 'message' => 'No tenants found - system may not be fully configured'];
        }
    } catch (Exception $e) {
        return ['status' => 'fail', 'message' => 'Error checking tenants: ' . $e->getMessage()];
    }
});

echo "</div></div>";

// ========== 2. AUTHENTICATION SYSTEM TESTS ==========
echo "<div class='section'>";
echo "<h2>🔐 Authentication System Tests</h2>";
echo "<div class='test-grid'>";

runTest("SimpleAuth Class", function() {
    try {
        $auth = new SimpleAuth();
        return ['status' => 'pass', 'message' => 'SimpleAuth class instantiated successfully'];
    } catch (Exception $e) {
        return ['status' => 'fail', 'message' => 'SimpleAuth class error: ' . $e->getMessage()];
    }
});

runTest("Login API Endpoint", function() {
    $baseUrl = defined('BASE_URL') ? BASE_URL : 'http://localhost/Nexiosolution/collabora';
    $apiUrl = $baseUrl . '/api/auth_simple.php';

    $data = json_encode([
        'action' => 'login',
        'email' => 'asamodeo@fortibyte.it',
        'password' => 'Ricord@1991'
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $data,
            'timeout' => 10
        ]
    ]);

    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        return ['status' => 'fail', 'message' => "API endpoint unreachable: {$apiUrl}"];
    }

    $result = json_decode($response, true);
    if ($result && isset($result['success']) && $result['success']) {
        return ['status' => 'pass', 'message' => 'Login API working correctly'];
    } else {
        return ['status' => 'fail', 'message' => 'Login API returned error: ' . ($result['message'] ?? 'Unknown error')];
    }
});

echo "</div></div>";

// ========== 3. UI CONSISTENCY TESTS ==========
echo "<div class='section'>";
echo "<h2>🎨 UI Consistency Tests</h2>";
echo "<div class='test-grid'>";

runTest("Dashboard Page Structure", function() {
    $file = __DIR__ . '/dashboard.php';
    if (!file_exists($file)) {
        return ['status' => 'fail', 'message' => 'dashboard.php not found'];
    }

    $content = file_get_contents($file);

    // Check for consistent theme
    if (strpos($content, '#111827') !== false || strpos($content, 'anthracite') !== false) {
        return ['status' => 'pass', 'message' => 'Dashboard uses anthracite theme (#111827)'];
    } else {
        return ['status' => 'warning', 'message' => 'Dashboard theme may not be consistent - check manually'];
    }
});

runTest("Calendar Page UI Structure", function() {
    $file = __DIR__ . '/calendar.php';
    if (!file_exists($file)) {
        return ['status' => 'fail', 'message' => 'calendar.php not found'];
    }

    $content = file_get_contents($file);

    // Check for app-layout structure
    if (strpos($content, 'app-layout') !== false && strpos($content, 'main-wrapper') !== false) {
        return ['status' => 'pass', 'message' => 'Calendar page has consistent layout structure'];
    } else {
        return ['status' => 'fail', 'message' => 'Calendar page missing consistent layout structure'];
    }
});

runTest("Tasks Page UI Structure", function() {
    $file = __DIR__ . '/tasks.php';
    if (!file_exists($file)) {
        return ['status' => 'fail', 'message' => 'tasks.php not found'];
    }

    $content = file_get_contents($file);

    // Check for app-layout structure
    if (strpos($content, 'app-layout') !== false && strpos($content, 'main-wrapper') !== false) {
        return ['status' => 'pass', 'message' => 'Tasks page has consistent layout structure'];
    } else {
        return ['status' => 'fail', 'message' => 'Tasks page missing consistent layout structure'];
    }
});

runTest("Chat Page UI Structure", function() {
    $file = __DIR__ . '/chat.php';
    if (!file_exists($file)) {
        return ['status' => 'fail', 'message' => 'chat.php not found'];
    }

    $content = file_get_contents($file);

    // Check for app-layout structure
    if (strpos($content, 'app-layout') !== false && strpos($content, 'main-wrapper') !== false) {
        return ['status' => 'pass', 'message' => 'Chat page has consistent layout structure'];
    } else {
        return ['status' => 'fail', 'message' => 'Chat page missing consistent layout structure'];
    }
});

echo "</div></div>";

// ========== 4. JAVASCRIPT VALIDATION TESTS ==========
echo "<div class='section'>";
echo "<h2>🟨 JavaScript Validation Tests</h2>";
echo "<div class='test-grid'>";

runTest("JavaScript Export Issues", function() {
    $jsFiles = [
        'assets/js/auth_v2.js',
        'assets/js/calendar.js',
        'assets/js/tasks.js',
        'assets/js/chat.js',
        'assets/js/components.js',
        'assets/js/filemanager.js',
        'assets/js/post-login-config.js',
        'assets/js/post-login-handler.js'
    ];

    $exportFound = false;
    $filesWithExports = [];

    foreach ($jsFiles as $file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);
            if (preg_match('/\bexport\s+/', $content)) {
                $exportFound = true;
                $filesWithExports[] = $file;
            }
        }
    }

    if (!$exportFound) {
        return ['status' => 'pass', 'message' => 'No ES6 export statements found - all files use window assignment'];
    } else {
        return ['status' => 'fail', 'message' => 'ES6 exports found in: ' . implode(', ', $filesWithExports)];
    }
});

runTest("Chart.js Local Loading", function() {
    $chartFile = __DIR__ . '/assets/js/vendor/chart.min.js';
    if (file_exists($chartFile)) {
        return ['status' => 'pass', 'message' => 'Chart.js loaded locally - no CSP issues'];
    } else {
        return ['status' => 'warning', 'message' => 'Chart.js not found locally - may be using CDN'];
    }
});

echo "</div></div>";

// ========== 5. API ENDPOINT TESTS ==========
echo "<div class='section'>";
echo "<h2>🔌 API Endpoint Tests</h2>";
echo "<div class='test-grid'>";

$apiEndpoints = [
    'auth_simple.php' => 'Simple Authentication',
    'auth_v2.php' => 'Authentication V2',
    'calendars.php' => 'Calendar Management',
    'events.php' => 'Event Management',
    'tasks.php' => 'Task Management',
    'task-lists.php' => 'Task Lists',
    'messages.php' => 'Chat Messages',
    'channels.php' => 'Chat Channels',
    'files.php' => 'File Management',
    'users.php' => 'User Management'
];

foreach ($apiEndpoints as $endpoint => $description) {
    runTest("API: {$description}", function() use ($endpoint) {
        $file = __DIR__ . '/api/' . $endpoint;
        if (!file_exists($file)) {
            return ['status' => 'fail', 'message' => "File not found: api/{$endpoint}"];
        }

        // Check PHP syntax
        $output = shell_exec("php -l \"{$file}\" 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            return ['status' => 'pass', 'message' => "API endpoint has valid PHP syntax"];
        } else {
            return ['status' => 'fail', 'message' => "PHP syntax error in api/{$endpoint}"];
        }
    });
}

echo "</div></div>";

// ========== 6. URL CONCATENATION TESTS ==========
echo "<div class='section'>";
echo "<h2>🔗 URL Concatenation Tests</h2>";
echo "<div class='test-grid'>";

runTest("Sidebar URL Patterns", function() {
    $file = __DIR__ . '/components/sidebar.php';
    if (!file_exists($file)) {
        return ['status' => 'fail', 'message' => 'Sidebar component not found'];
    }

    $content = file_get_contents($file);

    // Check for problematic patterns
    if (preg_match('/echo\s+BASE_URL\s*;\s*\?>\s*\//', $content)) {
        return ['status' => 'fail', 'message' => 'Found problematic URL concatenation pattern'];
    }

    // Check for correct pattern
    if (preg_match('/\$base_url\s*\.\s*[\'"]\//', $content)) {
        return ['status' => 'pass', 'message' => 'Sidebar uses correct URL concatenation pattern'];
    }

    return ['status' => 'warning', 'message' => 'URL pattern unclear - manual verification needed'];
});

runTest("Admin Pages URL Patterns", function() {
    $adminFiles = ['admin/index.php', 'dashboard.php'];
    $correctPattern = true;
    $problemFiles = [];

    foreach ($adminFiles as $file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            $content = file_get_contents($fullPath);

            // Check for problematic patterns
            if (preg_match('/echo\s+BASE_URL\s*;\s*\?>\s*\//', $content)) {
                $correctPattern = false;
                $problemFiles[] = $file;
            }
        }
    }

    if ($correctPattern) {
        return ['status' => 'pass', 'message' => 'Admin pages use correct URL concatenation'];
    } else {
        return ['status' => 'fail', 'message' => 'Problematic URL patterns found in: ' . implode(', ', $problemFiles)];
    }
});

echo "</div></div>";

// ========== 7. FILE STRUCTURE VALIDATION ==========
echo "<div class='section'>";
echo "<h2>📁 File Structure Validation</h2>";
echo "<div class='test-grid'>";

$requiredFiles = [
    'index_v2.php' => 'Main entry point',
    'dashboard.php' => 'Dashboard page',
    'calendar.php' => 'Calendar page',
    'tasks.php' => 'Tasks page',
    'chat.php' => 'Chat page',
    'config_v2.php' => 'Configuration file',
    'components/sidebar.php' => 'Sidebar component',
    'components/header.php' => 'Header component',
    'includes/SimpleAuth.php' => 'Authentication class',
    'includes/db.php' => 'Database connection',
    'assets/css/styles.css' => 'Main stylesheet'
];

foreach ($requiredFiles as $file => $description) {
    runTest("File: {$description}", function() use ($file) {
        $fullPath = __DIR__ . '/' . $file;
        if (file_exists($fullPath)) {
            $fileSize = filesize($fullPath);
            return ['status' => 'pass', 'message' => "File exists ({$fileSize} bytes)"];
        } else {
            return ['status' => 'fail', 'message' => "Required file missing: {$file}"];
        }
    });
}

echo "</div></div>";

// ========== SUMMARY ==========
echo "<div class='summary'>";
echo "<h2>📊 Test Summary</h2>";
$successRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100, 1) : 0;

if ($successRate >= 95) {
    $statusClass = 'pass';
    $statusIcon = '🎉';
    $statusText = 'EXCELLENT';
} elseif ($successRate >= 85) {
    $statusClass = 'warning';
    $statusIcon = '⚠️';
    $statusText = 'GOOD';
} else {
    $statusClass = 'fail';
    $statusIcon = '❌';
    $statusText = 'NEEDS WORK';
}

echo "<div class='test-item {$statusClass}'>";
echo "<h3>{$statusIcon} System Status: {$statusText}</h3>";
echo "<p><strong>Tests Executed:</strong> {$totalTests}</p>";
echo "<p><strong>Passed:</strong> {$passedTests}</p>";
echo "<p><strong>Failed:</strong> {$failedTests}</p>";
echo "<p><strong>Warnings:</strong> {$warnings}</p>";
echo "<p><strong>Success Rate:</strong> {$successRate}%</p>";
echo "</div>";

if ($failedTests > 0) {
    echo "<div class='test-item fail'>";
    echo "<h4>⚡ Action Required</h4>";
    echo "<p>Some tests failed. Please review the failed tests above and address the issues before proceeding.</p>";
    echo "</div>";
}

echo "</div>";

?>

        <div class="manual-tests">
            <h2>👤 Manual Validation Checklist</h2>
            <p><strong>After reviewing the automated tests above, perform these manual checks:</strong></p>

            <div style="margin: 15px 0;">
                <h3>🔑 1. Authentication Test</h3>
                <a href="index_v2.php" class="action-btn" target="_blank">Test Login</a>
                <div class="test-url">
                    URL: <?php echo (defined('BASE_URL') ? BASE_URL : 'http://localhost/Nexiosolution/collabora'); ?>/index_v2.php<br>
                    Credentials: asamodeo@fortibyte.it / Ricord@1991
                </div>
            </div>

            <div style="margin: 15px 0;">
                <h3>🏠 2. Navigation Test</h3>
                <a href="dashboard.php" class="action-btn" target="_blank">Dashboard</a>
                <a href="calendar.php" class="action-btn" target="_blank">Calendar</a>
                <a href="tasks.php" class="action-btn" target="_blank">Tasks</a>
                <a href="chat.php" class="action-btn" target="_blank">Chat</a>
                <a href="admin/index.php" class="action-btn" target="_blank">Admin Panel</a>
            </div>

            <div style="margin: 15px 0;">
                <h3>🔍 3. Browser Console Check</h3>
                <p>✅ Open each page and check Developer Tools (F12) → Console tab</p>
                <p>✅ Verify no JavaScript errors or failed API calls</p>
                <p>✅ Check Network tab for 404/500 errors</p>
            </div>

            <div style="margin: 15px 0;">
                <h3>📱 4. Responsive Design Test</h3>
                <p>✅ Test mobile viewport (< 768px width)</p>
                <p>✅ Verify sidebar collapses correctly</p>
                <p>✅ Check all pages are usable on mobile</p>
            </div>

            <div style="margin: 15px 0;">
                <h3>⚡ 5. Basic Functionality Test</h3>
                <p>✅ Create a test calendar event</p>
                <p>✅ Create a test task</p>
                <p>✅ Send a test chat message</p>
                <p>✅ Upload a test file (if applicable)</p>
            </div>
        </div>

        <div class="info">
            <h3>📝 Next Steps</h3>
            <p>1. Address any failed automated tests above</p>
            <p>2. Complete the manual validation checklist</p>
            <p>3. Document any issues found</p>
            <p>4. Update system documentation</p>
            <p>5. Confirm all objectives are met</p>
        </div>

    </div>
</body>
</html>