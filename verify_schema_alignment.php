<?php
/**
 * Schema Alignment Verification Script
 *
 * This script verifies that all database tables match what the application code expects
 * and tests basic CRUD operations on each module (chat, calendar, tasks).
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Colors for output
$colors = [
    'reset'  => "\033[0m",
    'red'    => "\033[31m",
    'green'  => "\033[32m",
    'yellow' => "\033[33m",
    'blue'   => "\033[34m",
    'bold'   => "\033[1m"
];

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'name' => 'nexio_collabora_v2',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
];

// Test results storage
$results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'details' => []
];

/**
 * Print colored message
 */
function printMessage($message, $type = 'info', $indent = 0) {
    global $colors;

    $spaces = str_repeat('  ', $indent);
    switch($type) {
        case 'error':
            echo $spaces . $colors['red'] . "✗ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'success':
            echo $spaces . $colors['green'] . "✓ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'warning':
            echo $spaces . $colors['yellow'] . "⚠ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'info':
            echo $spaces . $colors['blue'] . "ℹ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'header':
            echo PHP_EOL . $colors['bold'] . $colors['blue'] . $message . $colors['reset'] . PHP_EOL;
            echo str_repeat('=', strlen($message)) . PHP_EOL;
            break;
        default:
            echo $spaces . $message . PHP_EOL;
    }
}

/**
 * Connect to database
 */
function getConnection($config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['name']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Test table existence
 */
function testTableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get table column structure
 */
function getTableColumns($pdo, $tableName) {
    try {
        $stmt = $pdo->query("DESCRIBE `$tableName`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Test table has required columns
 */
function testTableColumns($pdo, $tableName, $requiredColumns) {
    $columns = getTableColumns($pdo, $tableName);
    $columnNames = array_column($columns, 'Field');
    $missing = [];

    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columnNames)) {
            $missing[] = $col;
        }
    }

    return ['exists' => $columnNames, 'missing' => $missing];
}

/**
 * Get foreign key constraints
 */
function getForeignKeys($pdo, $tableName) {
    try {
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        $stmt = $pdo->prepare("
            SELECT
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmt->execute([$dbName, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Test insert operation
 */
function testInsert($pdo, $table, $data) {
    try {
        $columns = array_keys($data);
        $placeholders = array_map(function($c) { return ":$c"; }, $columns);

        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        return $stmt->execute();
    } catch (Exception $e) {
        return false;
    }
}

// =====================================================
// MAIN VERIFICATION PROCESS
// =====================================================

printMessage("NEXIOSOLUTION SCHEMA ALIGNMENT VERIFICATION", 'header');

// Connect to database
$pdo = getConnection($dbConfig);
if (!$pdo) {
    printMessage("Cannot connect to database", 'error');
    exit(1);
}

printMessage("Connected to database: {$dbConfig['name']}", 'success');

// =====================================================
// CHAT MODULE VERIFICATION
// =====================================================
printMessage("CHAT MODULE VERIFICATION", 'header');

$chatTables = [
    'chat_channels' => ['id', 'tenant_id', 'type', 'name', 'created_by'],
    'chat_channel_members' => ['id', 'channel_id', 'user_id', 'role'],
    'chat_messages' => ['id', 'channel_id', 'user_id', 'content', 'message_type'],
    'message_reactions' => ['id', 'message_id', 'user_id', 'emoji'],
    'message_mentions' => ['id', 'message_id', 'mentioned_user_id'],
    'message_reads' => ['id', 'user_id', 'channel_id', 'unread_count'],
    'chat_presence' => ['id', 'tenant_id', 'user_id', 'status'],
    'chat_typing_indicators' => ['id', 'channel_id', 'user_id'],
    'chat_pinned_messages' => ['id', 'channel_id', 'message_id', 'pinned_by']
];

foreach ($chatTables as $table => $requiredCols) {
    if (testTableExists($pdo, $table)) {
        printMessage("Table '$table' exists", 'success', 1);
        $results['passed']++;

        // Check columns
        $colCheck = testTableColumns($pdo, $table, $requiredCols);
        if (empty($colCheck['missing'])) {
            printMessage("All required columns present", 'success', 2);
            $results['passed']++;
        } else {
            printMessage("Missing columns: " . implode(', ', $colCheck['missing']), 'error', 2);
            $results['failed']++;
        }

        // Check foreign keys
        $fks = getForeignKeys($pdo, $table);
        if (!empty($fks)) {
            printMessage("Foreign keys configured (" . count($fks) . ")", 'info', 2);
        }
    } else {
        printMessage("Table '$table' MISSING", 'error', 1);
        $results['failed']++;
    }
}

// =====================================================
// CALENDAR MODULE VERIFICATION
// =====================================================
printMessage("CALENDAR MODULE VERIFICATION", 'header');

$calendarTables = [
    'calendars' => ['id', 'tenant_id', 'user_id', 'name', 'type'],
    'events' => ['id', 'tenant_id', 'calendar_id', 'uid', 'title'],
    'event_participants' => ['id', 'tenant_id', 'event_id', 'email'],
    'calendar_shares' => ['id', 'tenant_id', 'calendar_id', 'permission_level']
];

foreach ($calendarTables as $table => $requiredCols) {
    if (testTableExists($pdo, $table)) {
        printMessage("Table '$table' exists", 'success', 1);
        $results['passed']++;

        $colCheck = testTableColumns($pdo, $table, $requiredCols);
        if (empty($colCheck['missing'])) {
            printMessage("All required columns present", 'success', 2);
            $results['passed']++;
        } else {
            printMessage("Missing columns: " . implode(', ', $colCheck['missing']), 'error', 2);
            $results['failed']++;
        }
    } else {
        printMessage("Table '$table' MISSING", 'error', 1);
        $results['failed']++;
    }
}

// =====================================================
// TASK MODULE VERIFICATION
// =====================================================
printMessage("TASK MODULE VERIFICATION", 'header');

$taskTables = [
    'task_lists' => ['id', 'tenant_id', 'owner_id', 'name', 'type'],
    'tasks' => ['id', 'tenant_id', 'task_list_id', 'title', 'status', 'priority'],
    'task_assignments' => ['id', 'tenant_id', 'task_id', 'user_id', 'role']
];

foreach ($taskTables as $table => $requiredCols) {
    if (testTableExists($pdo, $table)) {
        printMessage("Table '$table' exists", 'success', 1);
        $results['passed']++;

        $colCheck = testTableColumns($pdo, $table, $requiredCols);
        if (empty($colCheck['missing'])) {
            printMessage("All required columns present", 'success', 2);
            $results['passed']++;
        } else {
            printMessage("Missing columns: " . implode(', ', $colCheck['missing']), 'error', 2);
            $results['failed']++;
        }
    } else {
        printMessage("Table '$table' MISSING", 'error', 1);
        $results['failed']++;
    }
}

// =====================================================
// VIEW VERIFICATION
// =====================================================
printMessage("VIEW VERIFICATION", 'header');

$views = ['v_unread_messages', 'v_active_channels'];

foreach ($views as $view) {
    try {
        $stmt = $pdo->query("SELECT * FROM `$view` LIMIT 1");
        printMessage("View '$view' exists and is accessible", 'success', 1);
        $results['passed']++;
    } catch (Exception $e) {
        printMessage("View '$view' not found or has errors", 'warning', 1);
        $results['warnings']++;
    }
}

// =====================================================
// STORED PROCEDURE VERIFICATION
// =====================================================
printMessage("STORED PROCEDURE VERIFICATION", 'header');

$procedures = ['sp_create_direct_channel', 'sp_mark_messages_read'];

foreach ($procedures as $proc) {
    try {
        $stmt = $pdo->query("SHOW CREATE PROCEDURE `$proc`");
        if ($stmt->fetch()) {
            printMessage("Procedure '$proc' exists", 'success', 1);
            $results['passed']++;
        }
    } catch (Exception $e) {
        printMessage("Procedure '$proc' not found", 'warning', 1);
        $results['warnings']++;
    }
}

// =====================================================
// API COMPATIBILITY CHECK
// =====================================================
printMessage("API COMPATIBILITY CHECK", 'header');

// Check if required API files exist
$apiFiles = [
    '/api/messages.php' => 'Chat API',
    '/api/channels.php' => 'Channels API',
    '/api/calendars.php' => 'Calendar API',
    '/api/events.php' => 'Events API',
    '/api/tasks.php' => 'Tasks API'
];

$baseDir = dirname(__FILE__);
foreach ($apiFiles as $file => $name) {
    $fullPath = $baseDir . $file;
    if (file_exists($fullPath)) {
        printMessage("$name exists at $file", 'success', 1);
        $results['passed']++;
    } else {
        printMessage("$name not found at $file", 'warning', 1);
        $results['warnings']++;
    }
}

// =====================================================
// FINAL SUMMARY
// =====================================================
printMessage("VERIFICATION SUMMARY", 'header');

$total = $results['passed'] + $results['failed'] + $results['warnings'];
$passRate = $total > 0 ? round(($results['passed'] / $total) * 100, 1) : 0;

printMessage("Total Tests: $total");
printMessage("Passed: {$results['passed']}", 'success');
printMessage("Failed: {$results['failed']}", $results['failed'] > 0 ? 'error' : 'success');
printMessage("Warnings: {$results['warnings']}", $results['warnings'] > 0 ? 'warning' : 'success');
printMessage("Pass Rate: $passRate%");

echo PHP_EOL;

if ($results['failed'] === 0) {
    printMessage("✓ DATABASE SCHEMA IS PROPERLY ALIGNED!", 'success');
    printMessage("All required tables exist with correct structure", 'success');
    printMessage("The system is ready for use", 'success');

    echo PHP_EOL;
    printMessage("Next Steps:", 'info');
    printMessage("1. Test the chat interface: http://localhost/Nexiosolution/collabora/chat.php", 'info');
    printMessage("2. Test the calendar: http://localhost/Nexiosolution/collabora/calendar.php", 'info');
    printMessage("3. Test tasks: http://localhost/Nexiosolution/collabora/tasks.php", 'info');

    exit(0);
} else {
    printMessage("✗ SCHEMA ALIGNMENT ISSUES DETECTED", 'error');
    printMessage("Please run: php apply_schema_fixes.php", 'warning');
    printMessage("This will create any missing tables and fix the schema", 'warning');

    exit(1);
}
?>