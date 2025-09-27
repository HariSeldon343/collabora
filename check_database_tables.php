<?php
/**
 * Check if all required database tables exist
 */

require_once __DIR__ . '/config_v2.php';
require_once __DIR__ . '/includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Checking Database Tables ===\n\n";

try {
    $db = getDbConnection();

    // Get current database name
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "Database: $dbName\n\n";

    // Required tables for chat functionality
    $requiredTables = [
        // Core tables
        'users',
        'tenants',
        'user_tenant_associations',

        // Chat tables
        'chat_channels',
        'chat_channel_members',
        'chat_messages',
        'message_reactions',
        'message_mentions',
        'message_reads',
        'chat_presence',
        'chat_typing_indicators',
        'chat_pinned_messages',
        'chat_analytics',

        // Calendar tables
        'calendars',
        'events',
        'event_participants',
        'event_reminders',
        'calendar_shares',
        'event_attachments',

        // Task tables
        'task_lists',
        'tasks',
        'task_assignments',
        'task_comments',
        'task_time_logs',
        'task_attachments',

        // File tables
        'files',
        'folders'
    ];

    // Get existing tables
    $stmt = $db->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Checking required tables:\n";
    echo str_repeat('-', 50) . "\n";

    $missingTables = [];

    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "✓ $table - EXISTS\n";
        } else {
            echo "✗ $table - MISSING\n";
            $missingTables[] = $table;
        }
    }

    echo "\n";
    echo "Summary:\n";
    echo str_repeat('-', 50) . "\n";
    echo "Total required tables: " . count($requiredTables) . "\n";
    echo "Existing tables: " . (count($requiredTables) - count($missingTables)) . "\n";
    echo "Missing tables: " . count($missingTables) . "\n";

    if (count($missingTables) > 0) {
        echo "\nMissing tables that need to be created:\n";
        foreach ($missingTables as $table) {
            echo "  - $table\n";
        }

        echo "\n⚠️ WARNING: Missing tables will cause 500 errors in the corresponding API endpoints!\n";
        echo "Run the migration scripts to create these tables.\n";
    } else {
        echo "\n✓ All required tables exist!\n";
    }

    // Check for extra tables
    $extraTables = array_diff($existingTables, $requiredTables);
    if (count($extraTables) > 0) {
        echo "\nAdditional tables found (not required but present):\n";
        foreach ($extraTables as $table) {
            echo "  - $table\n";
        }
    }

} catch (PDOException $e) {
    echo "ERROR: Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nMake sure:\n";
    echo "1. MySQL/MariaDB is running\n";
    echo "2. Database '" . DB_NAME . "' exists\n";
    echo "3. Credentials in config_v2.php are correct\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\nTest complete.\n";