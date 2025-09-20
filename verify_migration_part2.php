<?php
/**
 * Verification script for Calendar & Task Management tables
 * Checks if all tables were created correctly
 */

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'collabora_files';

// Colors
$green = "\033[0;32m";
$red = "\033[0;31m";
$yellow = "\033[0;33m";
$blue = "\033[0;34m";
$reset = "\033[0m";

echo "\n{$blue}=================================================={$reset}\n";
echo "{$blue}Calendar & Task Management - Table Verification{$reset}\n";
echo "{$blue}=================================================={$reset}\n\n";

try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Expected tables
    $expectedTables = [
        'Calendar Module' => [
            'calendars' => 'Calendar containers',
            'events' => 'Calendar events with recurrence',
            'event_participants' => 'Event attendees',
            'event_reminders' => 'Event reminders',
            'calendar_shares' => 'Calendar sharing',
            'event_attachments' => 'Event file attachments'
        ],
        'Task Module' => [
            'task_lists' => 'Task boards/lists',
            'tasks' => 'Individual tasks',
            'task_assignments' => 'Task assignees',
            'task_comments' => 'Task comments',
            'task_time_logs' => 'Time tracking',
            'task_attachments' => 'Task file attachments'
        ]
    ];

    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $allFound = true;
    $tableCount = 0;

    foreach ($expectedTables as $module => $tables) {
        echo "{$yellow}{$module}:{$reset}\n";

        foreach ($tables as $table => $description) {
            if (in_array($table, $existingTables)) {
                // Get table info
                $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = '{$table}'");
                $columnCount = $stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
                $rowCount = $stmt->fetchColumn();

                echo "  {$green}✓ {$table}{$reset} - {$description} ({$columnCount} columns, {$rowCount} rows)\n";
                $tableCount++;
            } else {
                echo "  {$red}✗ {$table}{$reset} - NOT FOUND\n";
                $allFound = false;
            }
        }
        echo "\n";
    }

    // Check indexes
    echo "{$yellow}Index Statistics:{$reset}\n";
    $indexQuery = "
        SELECT TABLE_NAME, COUNT(*) as index_count
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
        AND TABLE_NAME IN ('calendars','events','event_participants','event_reminders',
                          'task_lists','tasks','task_assignments','task_comments')
        GROUP BY TABLE_NAME
    ";
    $stmt = $pdo->prepare($indexQuery);
    $stmt->execute([$dbName]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['TABLE_NAME']}: {$row['index_count']} indexes\n";
    }
    echo "\n";

    // Check foreign keys
    echo "{$yellow}Foreign Key Relationships:{$reset}\n";
    $fkQuery = "
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = ?
        AND REFERENCED_TABLE_NAME IS NOT NULL
        AND TABLE_NAME IN ('calendars','events','event_participants','event_reminders',
                          'task_lists','tasks','task_assignments','task_comments')
        ORDER BY TABLE_NAME, COLUMN_NAME
    ";
    $stmt = $pdo->prepare($fkQuery);
    $stmt->execute([$dbName]);

    $currentTable = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($currentTable != $row['TABLE_NAME']) {
            echo "  {$blue}{$row['TABLE_NAME']}:{$reset}\n";
            $currentTable = $row['TABLE_NAME'];
        }
        echo "    {$row['COLUMN_NAME']} → {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}\n";
    }
    echo "\n";

    // Summary
    if ($allFound) {
        echo "{$green}=================================================={$reset}\n";
        echo "{$green}✓ All {$tableCount} tables successfully created!{$reset}\n";
        echo "{$green}=================================================={$reset}\n\n";

        echo "{$yellow}Quick Test Queries:{$reset}\n";
        echo "  SELECT * FROM calendars;\n";
        echo "  SELECT * FROM events WHERE start_datetime > NOW();\n";
        echo "  SELECT * FROM tasks WHERE status != 'done';\n";
        echo "  SELECT * FROM v_active_events;\n";
        echo "  SELECT * FROM v_active_tasks;\n";
        echo "  CALL sp_get_upcoming_events(1, 1, 7);\n";
        echo "  CALL sp_get_task_statistics(1, 1);\n";
    } else {
        echo "{$red}=================================================={$reset}\n";
        echo "{$red}✗ Some tables are missing. Run the migration first.{$reset}\n";
        echo "{$red}=================================================={$reset}\n";
        echo "\nTo apply the migration, run:\n";
        echo "  mysql -u root collabora_files < database/migrations_part2.sql\n";
        echo "\nOr on Windows:\n";
        echo "  apply_migration.bat\n";
    }

} catch (PDOException $e) {
    echo "{$red}Database Error: {$e->getMessage()}{$reset}\n";
    echo "\nMake sure:\n";
    echo "1. XAMPP MySQL is running\n";
    echo "2. Database '{$dbName}' exists\n";
    echo "3. You have the correct credentials\n";
    exit(1);
}