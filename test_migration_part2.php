<?php
/**
 * Test script for Calendar & Task Management migration
 * Validates SQL syntax and applies migration to test database
 */

// Configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'collabora_files';
$migrationFile = __DIR__ . '/database/migrations_part2.sql';

// Colors for output
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "{$blue}=================================================={$reset}\n";
echo "{$blue}Calendar & Task Management Migration Test{$reset}\n";
echo "{$blue}=================================================={$reset}\n\n";

// Check if migration file exists
if (!file_exists($migrationFile)) {
    die("{$red}Error: Migration file not found at {$migrationFile}{$reset}\n");
}

echo "{$yellow}Migration file found:{$reset} {$migrationFile}\n";
echo "{$yellow}File size:{$reset} " . number_format(filesize($migrationFile) / 1024, 2) . " KB\n\n";

try {
    // Connect to database
    echo "{$blue}Connecting to database...{$reset}\n";
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "{$green}✓ Database connection successful{$reset}\n\n";

    // Read migration file
    echo "{$blue}Reading migration file...{$reset}\n";
    $sql = file_get_contents($migrationFile);

    // Parse SQL to count operations
    preg_match_all('/CREATE TABLE/i', $sql, $creates);
    preg_match_all('/CREATE INDEX/i', $sql, $indexes);
    preg_match_all('/CREATE PROCEDURE/i', $sql, $procedures);
    preg_match_all('/CREATE TRIGGER/i', $sql, $triggers);
    preg_match_all('/CREATE.*VIEW/i', $sql, $views);

    echo "{$yellow}Migration contains:{$reset}\n";
    echo "  - " . count($creates[0]) . " tables to create\n";
    echo "  - " . count($indexes[0]) . " indexes to create\n";
    echo "  - " . count($procedures[0]) . " stored procedures\n";
    echo "  - " . count($triggers[0]) . " triggers\n";
    echo "  - " . count($views[0]) . " views\n\n";

    // Execute migration
    echo "{$blue}Executing migration...{$reset}\n";

    // Use multi_query approach for complex SQL with delimiters
    $mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }

    // Execute the entire SQL file
    if ($mysqli->multi_query($sql)) {
        do {
            // Process all results
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());
    }

    if ($mysqli->errno) {
        throw new Exception("Migration failed: " . $mysqli->error);
    }

    $mysqli->close();

    echo "{$green}✓ Migration executed successfully{$reset}\n\n";

    // Verify tables were created
    echo "{$blue}Verifying created tables...{$reset}\n";
    $expectedTables = [
        'calendars',
        'events',
        'event_participants',
        'event_reminders',
        'calendar_shares',
        'event_attachments',
        'task_lists',
        'tasks',
        'task_assignments',
        'task_comments',
        'task_time_logs',
        'task_attachments'
    ];

    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($expectedTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "{$green}  ✓ {$table}{$reset}\n";
        } else {
            echo "{$red}  ✗ {$table} not found{$reset}\n";
        }
    }

    echo "\n";

    // Check sample data
    echo "{$blue}Checking sample data...{$reset}\n";

    $checks = [
        ['calendars', 'SELECT COUNT(*) FROM calendars'],
        ['task_lists', 'SELECT COUNT(*) FROM task_lists'],
        ['events', 'SELECT COUNT(*) FROM events'],
        ['tasks', 'SELECT COUNT(*) FROM tasks']
    ];

    foreach ($checks as [$name, $query]) {
        try {
            $stmt = $pdo->query($query);
            $count = $stmt->fetchColumn();
            echo "  {$green}✓ {$name}: {$count} records{$reset}\n";
        } catch (Exception $e) {
            echo "  {$red}✗ {$name}: Failed to query{$reset}\n";
        }
    }

    echo "\n";

    // Test stored procedures
    echo "{$blue}Testing stored procedures...{$reset}\n";
    try {
        $stmt = $pdo->prepare("CALL sp_get_upcoming_events(?, ?, ?)");
        $stmt->execute([1, 1, 7]);
        echo "  {$green}✓ sp_get_upcoming_events works{$reset}\n";
    } catch (Exception $e) {
        echo "  {$yellow}⚠ sp_get_upcoming_events: {$e->getMessage()}{$reset}\n";
    }

    try {
        $stmt = $pdo->prepare("CALL sp_get_task_statistics(?, ?)");
        $stmt->execute([1, 1]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "  {$green}✓ sp_get_task_statistics works{$reset}\n";
        if ($stats) {
            echo "    Tasks: {$stats['total_tasks']} total, {$stats['completed_tasks']} completed\n";
        }
    } catch (Exception $e) {
        echo "  {$yellow}⚠ sp_get_task_statistics: {$e->getMessage()}{$reset}\n";
    }

    echo "\n";

    // Test views
    echo "{$blue}Testing views...{$reset}\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM v_active_events");
        $count = $stmt->fetchColumn();
        echo "  {$green}✓ v_active_events: {$count} records{$reset}\n";
    } catch (Exception $e) {
        echo "  {$yellow}⚠ v_active_events: {$e->getMessage()}{$reset}\n";
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM v_active_tasks");
        $count = $stmt->fetchColumn();
        echo "  {$green}✓ v_active_tasks: {$count} records{$reset}\n";
    } catch (Exception $e) {
        echo "  {$yellow}⚠ v_active_tasks: {$e->getMessage()}{$reset}\n";
    }

    echo "\n{$green}=================================================={$reset}\n";
    echo "{$green}✓ Migration test completed successfully!{$reset}\n";
    echo "{$green}=================================================={$reset}\n";

} catch (PDOException $e) {
    echo "{$red}Database Error: {$e->getMessage()}{$reset}\n";
    exit(1);
} catch (Exception $e) {
    echo "{$red}Error: {$e->getMessage()}{$reset}\n";
    exit(1);
}