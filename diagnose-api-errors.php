<?php declare(strict_types=1);

/**
 * NEXIO COLLABORA - API ERROR DIAGNOSIS TOOL
 * Identifies exact database mismatches and errors
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Load configuration
require_once __DIR__ . '/config_v2.php';
require_once __DIR__ . '/includes/db.php';

// Colors for terminal output
$red = "\033[0;31m";
$green = "\033[0;32m";
$yellow = "\033[1;33m";
$blue = "\033[0;34m";
$cyan = "\033[0;36m";
$reset = "\033[0m";

echo "{$blue}=====================================\n";
echo "NEXIO COLLABORA API ERROR DIAGNOSIS\n";
echo "=====================================\n\n";

// Database connection
try {
    $db = getDbConnection();
    echo "{$green}✓{$reset} Database connected successfully\n\n";
} catch (PDOException $e) {
    echo "{$red}✗{$reset} Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Get existing tables
$stmt = $db->query("SHOW TABLES");
$existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "{$cyan}Existing tables in database:{$reset}\n";
foreach ($existingTables as $table) {
    echo "  • $table\n";
}
echo "\n";

// Define expected vs actual table mappings
$tableMapping = [
    'calendars.php' => [
        'expected' => ['calendars', 'events', 'event_participants', 'calendar_shares'],
        'errors' => []
    ],
    'events.php' => [
        'expected' => ['events', 'event_participants', 'event_reminders', 'event_attachments'],
        'errors' => []
    ],
    'task-lists.php' => [
        'expected' => ['task_lists', 'tasks', 'task_assignments'],
        'errors' => []
    ],
    'channels.php' => [
        'expected' => ['chat_channels', 'chat_channel_members'],
        'actual' => ['rooms', 'room_members'],  // Schema has different names!
        'errors' => []
    ],
    'chat-poll.php' => [
        'expected' => ['chat_messages', 'chat_channels', 'chat_presence'],
        'actual' => ['rooms'],
        'errors' => []
    ],
    'messages.php' => [
        'expected' => ['chat_messages', 'message_reactions', 'message_mentions'],
        'errors' => []
    ]
];

echo "{$yellow}DATABASE SCHEMA ANALYSIS{$reset}\n";
echo str_repeat('=', 40) . "\n\n";

// Check each endpoint's required tables
foreach ($tableMapping as $endpoint => $config) {
    echo "{$blue}Endpoint: /api/$endpoint{$reset}\n";

    $expectedTables = $config['expected'];
    $actualTables = isset($config['actual']) ? $config['actual'] : $expectedTables;

    $missingExpected = [];
    $foundActual = [];

    // Check what's expected vs what exists
    foreach ($expectedTables as $table) {
        if (!in_array($table, $existingTables)) {
            $missingExpected[] = $table;
        }
    }

    // Check if alternate names exist
    if (isset($config['actual'])) {
        foreach ($config['actual'] as $table) {
            if (in_array($table, $existingTables)) {
                $foundActual[] = $table;
            }
        }
    }

    if (!empty($missingExpected)) {
        echo "  {$red}✗ Missing expected tables:{$reset}\n";
        foreach ($missingExpected as $table) {
            echo "    - $table\n";
        }
    }

    if (!empty($foundActual)) {
        echo "  {$yellow}! Found alternate tables:{$reset}\n";
        foreach ($foundActual as $table) {
            echo "    + $table (schema uses this instead)\n";
        }
    }

    if (empty($missingExpected) && empty($foundActual)) {
        echo "  {$green}✓ All required tables exist{$reset}\n";
    }

    echo "\n";
}

// Specific table structure checks
echo "{$yellow}TABLE STRUCTURE ANALYSIS{$reset}\n";
echo str_repeat('=', 40) . "\n\n";

// Check rooms vs chat_channels structure
if (in_array('rooms', $existingTables)) {
    echo "{$blue}Table: rooms (used instead of chat_channels){$reset}\n";
    $stmt = $db->query("DESCRIBE rooms");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Columns: " . implode(', ', $columns) . "\n\n";
}

// Check room_members vs chat_channel_members
if (in_array('room_members', $existingTables)) {
    echo "{$blue}Table: room_members (used instead of chat_channel_members){$reset}\n";
    $stmt = $db->query("DESCRIBE room_members");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Columns: " . implode(', ', $columns) . "\n\n";
}

// Check if task_lists exists
if (in_array('task_lists', $existingTables)) {
    echo "{$blue}Table: task_lists{$reset}\n";
    $stmt = $db->query("DESCRIBE task_lists");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Columns: " . implode(', ', $columns) . "\n";
    echo "  {$green}✓ Table exists with correct name{$reset}\n\n";
} else {
    echo "{$red}✗ Table 'task_lists' NOT FOUND{$reset}\n";
    echo "  This table is required for /api/task-lists.php\n\n";
}

// Check calendars table
if (in_array('calendars', $existingTables)) {
    echo "{$blue}Table: calendars{$reset}\n";
    $stmt = $db->query("DESCRIBE calendars");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "  Columns: " . implode(', ', $columns) . "\n";
    echo "  {$green}✓ Table exists with correct name{$reset}\n\n";
} else {
    echo "{$red}✗ Table 'calendars' NOT FOUND{$reset}\n";
    echo "  This table is required for /api/calendars.php\n\n";
}

// Test actual SQL queries that would be run
echo "{$yellow}SQL QUERY TESTING{$reset}\n";
echo str_repeat('=', 40) . "\n\n";

// Test queries for each problematic endpoint
$testQueries = [
    'channels.php expects chat_channels' => "SELECT * FROM chat_channels WHERE tenant_id = 1 LIMIT 1",
    'channels.php alternate with rooms' => "SELECT * FROM rooms WHERE tenant_id = 1 LIMIT 1",
    'calendars.php' => "SELECT * FROM calendars WHERE tenant_id = 1 LIMIT 1",
    'task-lists.php' => "SELECT * FROM task_lists WHERE tenant_id = 1 LIMIT 1",
    'events.php' => "SELECT * FROM events WHERE tenant_id = 1 LIMIT 1"
];

foreach ($testQueries as $description => $query) {
    echo "{$blue}Test: $description{$reset}\n";
    echo "  Query: $query\n";

    try {
        $stmt = $db->prepare($query);
        $stmt->execute();
        echo "  {$green}✓ Query executed successfully{$reset}\n";
    } catch (PDOException $e) {
        echo "  {$red}✗ Query failed: " . $e->getMessage() . "{$reset}\n";
    }
    echo "\n";
}

// Generate error report
echo "{$yellow}ERROR REPORT SUMMARY{$reset}\n";
echo str_repeat('=', 40) . "\n\n";

$report = [];

// Calendar endpoints
$report['calendars.php'] = [
    'error' => '500 Internal Server Error',
    'cause' => in_array('calendars', $existingTables) ?
        'Table exists but may have missing dependencies or code errors' :
        'Missing table: calendars',
    'fix' => in_array('calendars', $existingTables) ?
        'Check CalendarManager.php for syntax errors and missing includes' :
        'Run migrations to create calendars table'
];

$report['events.php'] = [
    'error' => '500 Internal Server Error',
    'cause' => in_array('events', $existingTables) ?
        'Table exists but may have missing CalendarManager.php class' :
        'Missing table: events',
    'fix' => 'Ensure CalendarManager.php exists and is properly included'
];

$report['task-lists.php'] = [
    'error' => '500 Internal Server Error',
    'cause' => in_array('task_lists', $existingTables) ?
        'Table exists but TaskManager.php may have issues' :
        'Missing table: task_lists',
    'fix' => 'Check TaskManager.php namespace and includes'
];

$report['channels.php'] = [
    'error' => '500 Internal Server Error',
    'cause' => 'CODE EXPECTS "chat_channels" BUT DATABASE HAS "rooms"',
    'fix' => 'Either:\n    1. Update ChatManager.php to use "rooms" table\n    2. OR rename "rooms" to "chat_channels" in database'
];

$report['chat-poll.php'] = [
    'error' => '500 Internal Server Error',
    'cause' => 'CODE EXPECTS "chat_messages" and "chat_channels" BUT DATABASE HAS "rooms"',
    'fix' => 'Update ChatManager.php queries to use correct table names'
];

$report['messages.php'] = [
    'error' => '500 Internal Server Error',
    'cause' => 'Missing table: chat_messages (schema may have different name)',
    'fix' => 'Check if messages are stored in different table and update code'
];

foreach ($report as $endpoint => $info) {
    echo "{$red}ENDPOINT: /api/$endpoint{$reset}\n";
    echo "  Error: {$info['error']}\n";
    echo "  Root Cause: {$yellow}{$info['cause']}{$reset}\n";
    echo "  Fix Required: {$green}{$info['fix']}{$reset}\n\n";
}

// Generate fix SQL
echo "{$cyan}RECOMMENDED FIXES{$reset}\n";
echo str_repeat('=', 40) . "\n\n";

echo "{$yellow}Option 1: Update PHP Code (Recommended){$reset}\n";
echo "1. Update ChatManager.php to use 'rooms' instead of 'chat_channels'\n";
echo "2. Update all references from 'chat_channel_members' to 'room_members'\n";
echo "3. Check if 'chat_messages' should be 'messages' or another table\n\n";

echo "{$yellow}Option 2: Rename Database Tables{$reset}\n";
echo "```sql\n";
if (in_array('rooms', $existingTables) && !in_array('chat_channels', $existingTables)) {
    echo "-- Rename rooms to chat_channels\n";
    echo "RENAME TABLE rooms TO chat_channels;\n\n";
}
if (in_array('room_members', $existingTables) && !in_array('chat_channel_members', $existingTables)) {
    echo "-- Rename room_members to chat_channel_members\n";
    echo "RENAME TABLE room_members TO chat_channel_members;\n\n";
}
echo "```\n\n";

echo "{$yellow}Option 3: Create Missing Tables{$reset}\n";
echo "Run the Part 2 and Part 4 migrations:\n";
echo "  mysql -u root nexio_collabora_v2 < database/migrations_part2.sql\n";
echo "  mysql -u root nexio_collabora_v2 < database/migrations_part4.sql\n\n";

// Save report to file
$reportFile = __DIR__ . '/API_ERROR_REPORT.md';
$reportContent = "# NEXIO COLLABORA API ERROR REPORT\n";
$reportContent .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
$reportContent .= "## CRITICAL ISSUE IDENTIFIED\n\n";
$reportContent .= "**The main issue is a TABLE NAME MISMATCH between code and database schema:**\n\n";
$reportContent .= "| API Code Expects | Database Schema Has |\n";
$reportContent .= "|-----------------|--------------------|\n";
$reportContent .= "| chat_channels | rooms |\n";
$reportContent .= "| chat_channel_members | room_members |\n";
$reportContent .= "| chat_messages | (missing or different name) |\n\n";
$reportContent .= "## Endpoint Error Summary\n\n";

foreach ($report as $endpoint => $info) {
    $reportContent .= "### /api/$endpoint\n";
    $reportContent .= "- **Error:** {$info['error']}\n";
    $reportContent .= "- **Root Cause:** {$info['cause']}\n";
    $reportContent .= "- **Fix:** {$info['fix']}\n\n";
}

$reportContent .= "## Recommended Solution\n\n";
$reportContent .= "Update the ChatManager.php class to use the correct table names from the schema:\n";
$reportContent .= "- Replace 'chat_channels' with 'rooms'\n";
$reportContent .= "- Replace 'chat_channel_members' with 'room_members'\n";
$reportContent .= "- Verify the correct table name for messages\n";

file_put_contents($reportFile, $reportContent);
echo "{$green}Full report saved to: $reportFile{$reset}\n";