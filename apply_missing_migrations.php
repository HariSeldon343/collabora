<?php
/**
 * Apply missing database migrations
 * Fixes 500 errors by creating missing tables
 */

require_once __DIR__ . '/config_v2.php';
require_once __DIR__ . '/includes/db.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "=== Applying Missing Database Migrations ===\n\n";

try {
    $db = getDbConnection();

    // Get current database name
    $dbName = $db->query("SELECT DATABASE()")->fetchColumn();
    echo "Database: $dbName\n\n";

    // Get existing tables
    $stmt = $db->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Migration files to apply
    $migrations = [
        'Part 2 - Calendar & Tasks' => __DIR__ . '/database/migrations_part2.sql',
        'Part 4 - Chat & Communication' => __DIR__ . '/database/migrations_part4_chat.sql',
    ];

    foreach ($migrations as $name => $file) {
        echo "Checking migration: $name\n";
        echo str_repeat('-', 50) . "\n";

        if (!file_exists($file)) {
            echo "ERROR: Migration file not found: $file\n\n";
            continue;
        }

        // Read the SQL file
        $sql = file_get_contents($file);

        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                // Filter out empty statements and comments
                $stmt = trim($stmt);
                return $stmt && !preg_match('/^--/', $stmt) && !preg_match('/^\/\*/', $stmt);
            }
        );

        $tablesCreated = 0;
        $errors = [];

        foreach ($statements as $statement) {
            // Skip SET statements and USE statements
            if (preg_match('/^(SET|USE)/i', $statement)) {
                try {
                    $db->exec($statement);
                } catch (PDOException $e) {
                    // Ignore errors for SET/USE statements
                }
                continue;
            }

            // Check if this is a CREATE TABLE statement
            if (preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?(\w+)`?/i', $statement, $matches)) {
                $tableName = $matches[1];

                try {
                    $db->exec($statement);

                    // Check if table was actually created
                    $checkStmt = $db->prepare("SHOW TABLES LIKE ?");
                    $checkStmt->execute([$tableName]);

                    if ($checkStmt->rowCount() > 0) {
                        if (!in_array($tableName, $existingTables)) {
                            echo "✓ Created table: $tableName\n";
                            $tablesCreated++;
                        } else {
                            echo "- Table already exists: $tableName\n";
                        }
                    }
                } catch (PDOException $e) {
                    echo "✗ Error creating table $tableName: " . $e->getMessage() . "\n";
                    $errors[] = "Table $tableName: " . $e->getMessage();
                }
            } elseif (preg_match('/^(CREATE|ALTER|INSERT)/i', $statement)) {
                // Execute other DDL statements
                try {
                    $db->exec($statement);
                } catch (PDOException $e) {
                    // Ignore duplicate key errors for INSERT statements
                    if ($e->getCode() != '23000') {
                        $errors[] = substr($statement, 0, 50) . "...: " . $e->getMessage();
                    }
                }
            }
        }

        echo "\nMigration Summary for $name:\n";
        echo "  Tables created: $tablesCreated\n";
        if (count($errors) > 0) {
            echo "  Errors: " . count($errors) . "\n";
            foreach ($errors as $error) {
                echo "    - $error\n";
            }
        }
        echo "\n";
    }

    // Re-check tables
    echo "Final Table Check:\n";
    echo str_repeat('-', 50) . "\n";

    $stmt = $db->query("SHOW TABLES");
    $finalTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $chatTables = ['chat_channels', 'chat_channel_members', 'chat_messages', 'message_reactions', 'chat_presence'];
    $calendarTables = ['calendars', 'events', 'event_participants'];
    $taskTables = ['task_lists', 'tasks', 'task_assignments'];

    echo "Chat tables:\n";
    foreach ($chatTables as $table) {
        if (in_array($table, $finalTables)) {
            echo "  ✓ $table\n";
        } else {
            echo "  ✗ $table - MISSING\n";
        }
    }

    echo "\nCalendar tables:\n";
    foreach ($calendarTables as $table) {
        if (in_array($table, $finalTables)) {
            echo "  ✓ $table\n";
        } else {
            echo "  ✗ $table - MISSING\n";
        }
    }

    echo "\nTask tables:\n";
    foreach ($taskTables as $table) {
        if (in_array($table, $finalTables)) {
            echo "  ✓ $table\n";
        } else {
            echo "  ✗ $table - MISSING\n";
        }
    }

    echo "\n✅ Migration process complete!\n";
    echo "API endpoints should now work without 500 errors.\n";

} catch (PDOException $e) {
    echo "❌ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nMake sure:\n";
    echo "1. MySQL/MariaDB is running\n";
    echo "2. Database '" . DB_NAME . "' exists\n";
    echo "3. Credentials in config_v2.php are correct\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nYou can now test the API endpoints:\n";
echo "- /api/channels.php\n";
echo "- /api/chat-poll.php\n";
echo "- /api/calendars.php\n";
echo "- /api/events.php\n";
echo "- /api/users.php\n";