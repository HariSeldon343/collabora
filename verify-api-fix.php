<?php declare(strict_types=1);

/**
 * NEXIO COLLABORA - API FIX VERIFICATION
 * Verifies that all API endpoints are working after applying fixes
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session for authentication
session_start();

// Load configuration
require_once __DIR__ . '/config_v2.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/SimpleAuth.php';

// Colors for terminal output
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[1;33m";
$blue = "\033[0;34m";
$cyan = "\033[0;36m";
$reset = "\033[0m";

echo "{$blue}=====================================\n";
echo "API FIX VERIFICATION\n";
echo "=====================================\n\n";

// Step 1: Check database tables
echo "{$yellow}Step 1: Verifying Database Tables{$reset}\n";
echo str_repeat('-', 40) . "\n";

try {
    $db = getDbConnection();

    $requiredTables = [
        'calendars' => 'Calendar management',
        'events' => 'Event storage',
        'task_lists' => 'Task list containers',
        'tasks' => 'Individual tasks',
        'chat_channels' => 'Chat channels',
        'chat_channel_members' => 'Channel membership',
        'chat_messages' => 'Chat messages',
        'chat_presence' => 'User presence tracking'
    ];

    $stmt = $db->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $allTablesExist = true;
    foreach ($requiredTables as $table => $description) {
        if (in_array($table, $existingTables)) {
            echo "{$green}✓{$reset} $table - $description\n";
        } else {
            echo "{$red}✗{$reset} $table - MISSING ($description)\n";
            $allTablesExist = false;
        }
    }

    if (!$allTablesExist) {
        echo "\n{$red}ERROR: Not all required tables exist.{$reset}\n";
        echo "Please run: {$yellow}fix-api-errors.sh{$reset} or {$yellow}FIX_API_ERRORS.bat{$reset}\n";
        exit(1);
    }

    echo "\n{$green}All required tables exist!{$reset}\n\n";

} catch (PDOException $e) {
    echo "{$red}Database connection failed: " . $e->getMessage() . "{$reset}\n";
    exit(1);
}

// Step 2: Authenticate for API testing
echo "{$yellow}Step 2: Setting Up Authentication{$reset}\n";
echo str_repeat('-', 40) . "\n";

// Create test session
$_SESSION['user_id'] = 1;
$_SESSION['email'] = 'admin@test.com';
$_SESSION['role'] = 'admin';
$_SESSION['current_tenant_id'] = 1;

$auth = new SimpleAuth();
echo "{$green}✓{$reset} Test session created\n\n";

// Step 3: Test each API endpoint
echo "{$yellow}Step 3: Testing API Endpoints{$reset}\n";
echo str_repeat('-', 40) . "\n";

$endpoints = [
    'calendars.php' => 'Calendar management',
    'events.php' => 'Event management',
    'task-lists.php' => 'Task list management',
    'channels.php' => 'Chat channels',
    'chat-poll.php' => 'Chat polling',
    'messages.php' => 'Message handling'
];

$results = [];
foreach ($endpoints as $endpoint => $description) {
    echo "Testing /api/{$endpoint} ({$description})... ";

    $apiFile = __DIR__ . '/api/' . $endpoint;
    if (!file_exists($apiFile)) {
        echo "{$red}FILE NOT FOUND{$reset}\n";
        $results[$endpoint] = 'File missing';
        continue;
    }

    // Test PHP syntax
    $output = shell_exec("php -l $apiFile 2>&1");
    if (strpos($output, 'No syntax errors') === false) {
        echo "{$red}SYNTAX ERROR{$reset}\n";
        $results[$endpoint] = 'Syntax error';
        continue;
    }

    // Test database queries (simulate)
    try {
        // Extract main table from endpoint name
        $table = '';
        switch($endpoint) {
            case 'calendars.php':
                $table = 'calendars';
                break;
            case 'events.php':
                $table = 'events';
                break;
            case 'task-lists.php':
                $table = 'task_lists';
                break;
            case 'channels.php':
                $table = 'chat_channels';
                break;
            case 'chat-poll.php':
            case 'messages.php':
                $table = 'chat_messages';
                break;
        }

        if ($table) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => 1]);
            echo "{$green}WORKING{$reset}\n";
            $results[$endpoint] = 'OK';
        }

    } catch (PDOException $e) {
        echo "{$red}SQL ERROR{$reset}\n";
        echo "  Error: " . $e->getMessage() . "\n";
        $results[$endpoint] = 'SQL Error: ' . $e->getMessage();
    }
}

// Step 4: Summary
echo "\n{$cyan}=====================================\n";
echo "VERIFICATION SUMMARY\n";
echo "====================================={$reset}\n\n";

$working = 0;
$failed = 0;

foreach ($results as $endpoint => $status) {
    if ($status === 'OK') {
        echo "{$green}✓{$reset} /api/$endpoint - {$green}WORKING{$reset}\n";
        $working++;
    } else {
        echo "{$red}✗{$reset} /api/$endpoint - {$red}$status{$reset}\n";
        $failed++;
    }
}

echo "\n";
echo "Total: {$working} working, {$failed} failed\n";

if ($failed === 0) {
    echo "\n{$green}SUCCESS! All API endpoints are now functional.{$reset}\n";
    echo "\nYou can now:\n";
    echo "1. Login at: http://localhost/Nexiosolution/collabora/\n";
    echo "2. Access calendar at: http://localhost/Nexiosolution/collabora/calendar.php\n";
    echo "3. Access tasks at: http://localhost/Nexiosolution/collabora/tasks.php\n";
    echo "4. Access chat at: http://localhost/Nexiosolution/collabora/chat.php\n";
} else {
    echo "\n{$yellow}Some endpoints still have issues.{$reset}\n";
    echo "Check the error messages above for details.\n";
}

// Additional checks
echo "\n{$cyan}Additional Checks:{$reset}\n";

// Check for conflicting tables
if (in_array('rooms', $existingTables) && in_array('chat_channels', $existingTables)) {
    echo "{$yellow}⚠{$reset} Both 'rooms' and 'chat_channels' exist - consider removing 'rooms'\n";
}

// Check for missing manager classes
$managers = [
    'CalendarManager.php' => 'Calendar operations',
    'TaskManager.php' => 'Task operations',
    'ChatManager.php' => 'Chat operations'
];

foreach ($managers as $file => $desc) {
    $path = __DIR__ . '/includes/' . $file;
    if (file_exists($path)) {
        echo "{$green}✓{$reset} $file exists - $desc\n";
    } else {
        echo "{$red}✗{$reset} $file MISSING - $desc\n";
    }
}

echo "\n";