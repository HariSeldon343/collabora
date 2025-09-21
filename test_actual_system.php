<?php
/**
 * NEXIO COLLABORA - ACTUAL SYSTEM TEST
 * Tests the components that actually exist in the system
 *
 * @author Claude Code
 * @date 2025-09-20
 */

session_start();
require_once 'config_v2.php';
require_once 'includes/db.php';
require_once 'includes/SimpleAuth.php';

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => [],
    'summary' => ['total' => 0, 'passed' => 0, 'failed' => 0]
];

function addTest($category, $test, $result, $message) {
    global $results;
    $results['tests'][] = [
        'category' => $category,
        'test' => $test,
        'result' => $result,
        'message' => $message
    ];
    $results['summary']['total']++;
    if ($result === 'PASS') {
        $results['summary']['passed']++;
    } else {
        $results['summary']['failed']++;
    }
}

echo "<h1>🔍 Nexio Collabora - System Test (Actual Files)</h1>\n";
echo "<p><strong>Testing Time:</strong> " . $results['timestamp'] . "</p>\n";

// Test 1: Database and Authentication
echo "<h2>🗄️ Database & Authentication</h2>\n";
try {
    $pdo = getDbConnection();
    addTest('Database', 'Connection', 'PASS', 'Database connected successfully');
    echo "✅ Database connection: OK<br>\n";

    $auth = new SimpleAuth();
    $loginResult = $auth->login('asamodeo@fortibyte.it', 'Ricord@1991');
    if ($loginResult['success']) {
        addTest('Auth', 'Admin Login', 'PASS', 'Admin login successful');
        echo "✅ Admin login: OK<br>\n";
    } else {
        addTest('Auth', 'Admin Login', 'FAIL', 'Admin login failed');
        echo "❌ Admin login: FAILED<br>\n";
    }
} catch (Exception $e) {
    addTest('Database', 'Connection', 'FAIL', $e->getMessage());
    echo "❌ Database: " . $e->getMessage() . "<br>\n";
}

// Test 2: Core Pages
echo "<h2>📄 Core Pages</h2>\n";
$pages = [
    'index_v2.php' => 'Main Login Page',
    'dashboard.php' => 'User Dashboard',
    'calendar.php' => 'Calendar Interface',
    'tasks.php' => 'Task Management',
    'chat.php' => 'Chat Interface'
];

foreach ($pages as $file => $description) {
    if (file_exists($file)) {
        // Basic syntax check
        $output = shell_exec("php -l \"$file\" 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            addTest('Pages', $file, 'PASS', "$description - Valid syntax");
            echo "✅ $file: Valid syntax<br>\n";
        } else {
            addTest('Pages', $file, 'FAIL', "$description - Syntax error");
            echo "❌ $file: Syntax error<br>\n";
        }
    } else {
        addTest('Pages', $file, 'FAIL', "$description - File missing");
        echo "❌ $file: File missing<br>\n";
    }
}

// Test 3: Admin Panel
echo "<h2>👑 Admin Panel</h2>\n";
$adminFiles = [
    'admin/index.php' => 'Admin Dashboard',
    'admin/users.php' => 'User Management',
    'admin/tenants.php' => 'Tenant Management'
];

foreach ($adminFiles as $file => $description) {
    if (file_exists($file)) {
        $output = shell_exec("php -l \"$file\" 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            addTest('Admin', $file, 'PASS', "$description - Valid syntax");
            echo "✅ $file: Valid syntax<br>\n";
        } else {
            addTest('Admin', $file, 'FAIL', "$description - Syntax error");
            echo "❌ $file: Syntax error<br>\n";
        }
    } else {
        addTest('Admin', $file, 'FAIL', "$description - File missing");
        echo "❌ $file: File missing<br>\n";
    }
}

// Test 4: JavaScript Files (check for export issues)
echo "<h2>🟨 JavaScript Files</h2>\n";
$jsFiles = [
    'assets/js/auth_v2.js' => 'Authentication JS',
    'assets/js/calendar.js' => 'Calendar JS',
    'assets/js/chat.js' => 'Chat JS',
    'assets/js/components.js' => 'UI Components',
    'assets/js/filemanager.js' => 'File Manager'
];

foreach ($jsFiles as $file => $description) {
    if (file_exists($file)) {
        $content = file_get_contents($file);

        // Check for problematic ES6 exports
        if (preg_match('/^export\s+/m', $content)) {
            addTest('JavaScript', $file, 'FAIL', "$description - Contains ES6 export statements");
            echo "❌ $file: Contains ES6 exports (will cause errors)<br>\n";
        } else {
            addTest('JavaScript', $file, 'PASS', "$description - No export issues");
            echo "✅ $file: No export issues<br>\n";
        }

        // Check for window assignments
        if (preg_match('/window\.\w+\s*=/', $content)) {
            echo "   ℹ️ Uses window assignment pattern (correct)<br>\n";
        }

    } else {
        addTest('JavaScript', $file, 'FAIL', "$description - File missing");
        echo "❌ $file: File missing<br>\n";
    }
}

// Test 5: API Endpoints (check what actually exists)
echo "<h2>🔌 API Endpoints</h2>\n";
$apiFiles = glob('api/*.php');
foreach ($apiFiles as $file) {
    $basename = basename($file);
    $output = shell_exec("php -l \"$file\" 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        addTest('API', $basename, 'PASS', "API endpoint - Valid syntax");
        echo "✅ $basename: Valid syntax<br>\n";
    } else {
        addTest('API', $basename, 'FAIL', "API endpoint - Syntax error");
        echo "❌ $basename: Syntax error<br>\n";
    }
}

// Test 6: URL Concatenation Patterns
echo "<h2>🔗 URL Patterns</h2>\n";
$filesToCheck = ['admin/index.php', 'dashboard.php', 'components/sidebar.php'];
foreach ($filesToCheck as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);

        // Check for problematic patterns
        if (preg_match('/echo\s+BASE_URL\s*;\s*\?>\s*\//', $content)) {
            addTest('URL', $file, 'FAIL', 'Uses problematic BASE_URL concatenation');
            echo "❌ $file: Problematic URL pattern<br>\n";
        } else {
            addTest('URL', $file, 'PASS', 'Uses correct URL concatenation');
            echo "✅ $file: Correct URL pattern<br>\n";
        }
    }
}

// Test 7: Critical Tables
echo "<h2>🏗️ Database Tables</h2>\n";
try {
    $pdo = getDbConnection();
    $criticalTables = [
        'users' => 'User accounts',
        'tenants' => 'Multi-tenant system',
        'calendars' => 'Calendar system',
        'events' => 'Events',
        'tasks' => 'Task management',
        'chat_channels' => 'Chat channels',
        'chat_messages' => 'Chat messages'
    ];

    foreach ($criticalTables as $table => $description) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            addTest('Tables', $table, 'PASS', "$description table exists");
            echo "✅ $table: Table exists<br>\n";
        } else {
            addTest('Tables', $table, 'FAIL', "$description table missing");
            echo "❌ $table: Table missing<br>\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Cannot check tables: " . $e->getMessage() . "<br>\n";
}

// Test 8: Manual Page Access Test
echo "<h2>🌐 Manual Access Test</h2>\n";
echo "<p><strong>Test these URLs manually:</strong></p>\n";
echo "<ul>\n";
echo "<li><a href='" . BASE_URL . "/index_v2.php' target='_blank'>" . BASE_URL . "/index_v2.php</a> - Login page</li>\n";
echo "<li><a href='" . BASE_URL . "/dashboard.php' target='_blank'>" . BASE_URL . "/dashboard.php</a> - Dashboard (requires login)</li>\n";
echo "<li><a href='" . BASE_URL . "/calendar.php' target='_blank'>" . BASE_URL . "/calendar.php</a> - Calendar (requires login)</li>\n";
echo "<li><a href='" . BASE_URL . "/tasks.php' target='_blank'>" . BASE_URL . "/tasks.php</a> - Tasks (requires login)</li>\n";
echo "<li><a href='" . BASE_URL . "/chat.php' target='_blank'>" . BASE_URL . "/chat.php</a> - Chat (requires login)</li>\n";
echo "<li><a href='" . BASE_URL . "/admin/index.php' target='_blank'>" . BASE_URL . "/admin/index.php</a> - Admin panel (requires admin login)</li>\n";
echo "</ul>\n";

// Summary
echo "<h2>📊 Test Summary</h2>\n";
$passRate = $results['summary']['total'] > 0 ?
    round(($results['summary']['passed'] / $results['summary']['total']) * 100, 1) : 0;

echo "<p><strong>Tests Run:</strong> " . $results['summary']['total'] . "</p>\n";
echo "<p><strong>Passed:</strong> " . $results['summary']['passed'] . "</p>\n";
echo "<p><strong>Failed:</strong> " . $results['summary']['failed'] . "</p>\n";
echo "<p><strong>Pass Rate:</strong> $passRate%</p>\n";

if ($passRate >= 80) {
    echo "<p style='color: green; font-size: 18px;'><strong>✅ SYSTEM STATUS: GOOD</strong></p>\n";
} elseif ($passRate >= 60) {
    echo "<p style='color: orange; font-size: 18px;'><strong>⚠️ SYSTEM STATUS: NEEDS ATTENTION</strong></p>\n";
} else {
    echo "<p style='color: red; font-size: 18px;'><strong>❌ SYSTEM STATUS: CRITICAL ISSUES</strong></p>\n";
}

// Next Steps
echo "<h2>🎯 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li><strong>Login Test:</strong> Visit " . BASE_URL . "/index_v2.php and login with admin@fortibyte.it / Ricord@1991</li>\n";
echo "<li><strong>Navigation Test:</strong> After login, navigate to Dashboard, Calendar, Tasks, and Chat</li>\n";
echo "<li><strong>Admin Test:</strong> Access admin panel and check user management</li>\n";
echo "<li><strong>Console Check:</strong> Open browser Developer Tools and check for JavaScript errors</li>\n";
echo "<li><strong>Network Check:</strong> Check Network tab for 404 errors on assets</li>\n";
echo "</ol>\n";

echo "<p><em>Generated: " . $results['timestamp'] . "</em></p>\n";
?>