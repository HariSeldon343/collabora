<?php
// Run complete migration script
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexio_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== RUNNING COMPLETE MIGRATION ===\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

    // Read the migration file
    $migrationFile = __DIR__ . '/database/complete_migration.sql';
    if (!file_exists($migrationFile)) {
        die("Migration file not found: $migrationFile\n");
    }

    $sql = file_get_contents($migrationFile);

    // Split by statement (simple approach for this migration)
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        // Skip comments and SET commands that might be problematic
        if (stripos($statement, 'SET NAMES') === 0 ||
            stripos($statement, 'SET FOREIGN_KEY_CHECKS') === 0 ||
            stripos($statement, 'SET SQL_MODE') === 0 ||
            stripos($statement, 'SET time_zone') === 0) {
            continue;
        }

        try {
            // Special handling for the dynamic ALTER TABLE statement
            if (strpos($statement, '@preparedStatement') !== false) {
                // Check if permissions table needs tenant_id
                $checkStmt = $conn->query("SHOW COLUMNS FROM permissions LIKE 'tenant_id'");
                if (!$checkStmt->fetch()) {
                    $conn->exec("ALTER TABLE `permissions` ADD COLUMN `tenant_id` INT UNSIGNED NULL AFTER `id`, ADD INDEX `idx_tenant` (`tenant_id`)");
                    echo "✅ Added tenant_id to permissions table\n";
                }
                continue;
            }

            // Execute the statement
            $conn->exec($statement);
            $successCount++;

            // Extract table name for reporting
            if (preg_match('/CREATE TABLE IF NOT EXISTS `([^`]+)`/i', $statement, $matches)) {
                echo "✅ Created table: {$matches[1]}\n";
            } elseif (preg_match('/ALTER TABLE `([^`]+)`/i', $statement, $matches)) {
                echo "✅ Altered table: {$matches[1]}\n";
            }
        } catch (PDOException $e) {
            $errorCount++;
            $errors[] = [
                'statement' => substr($statement, 0, 100) . '...',
                'error' => $e->getMessage()
            ];

            // Only show errors that aren't about existing objects
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\n=== MIGRATION SUMMARY ===\n";
    echo "Successful operations: $successCount\n";
    echo "Errors encountered: $errorCount\n";

    if ($errorCount > 0) {
        echo "\nErrors (excluding 'already exists'):\n";
        foreach ($errors as $error) {
            if (strpos($error['error'], 'already exists') === false) {
                echo "  - {$error['error']}\n";
            }
        }
    }

    // Verify tables after migration
    echo "\n=== VERIFYING TABLES AFTER MIGRATION ===\n";
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $expectedTables = [
        'users', 'tenants', 'companies', 'company_users',
        'password_resets', 'personal_access_tokens',
        'roles', 'permissions', 'model_has_roles',
        'model_has_permissions', 'role_has_permissions',
        'calendars', 'events', 'calendar_users', 'event_attendees',
        'task_lists', 'tasks', 'task_occurrences', 'task_assignees',
        'chat_channels', 'chat_messages', 'chat_channel_members',
        'chat_message_reads', 'chat_attachments',
        'folders', 'files', 'file_versions', 'file_shares',
        'audit_logs', 'notifications', 'push_subscriptions',
        'approvals', 'approval_steps', 'migrations'
    ];

    $foundCount = 0;
    $missingCount = 0;

    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            echo "✅ $table\n";
            $foundCount++;
        } else {
            echo "❌ $table (still missing)\n";
            $missingCount++;
        }
    }

    echo "\n=== FINAL STATUS ===\n";
    echo "Total expected tables: " . count($expectedTables) . "\n";
    echo "Tables found: $foundCount\n";
    echo "Tables still missing: $missingCount\n";

    if ($missingCount === 0) {
        echo "\n🎉 SUCCESS: All required tables are now present!\n";
    } else {
        echo "\n⚠️ WARNING: Some tables are still missing. Please check for errors above.\n";
    }

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>