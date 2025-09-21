<?php
// Database verification script
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexio_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== DATABASE STRUCTURE VERIFICATION ===\n";
    echo "Database: $dbname\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

    // Get all tables
    echo "=== EXISTING TABLES ===\n";
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "- $table\n";
    }
    echo "Total tables: " . count($tables) . "\n\n";

    // Check for migrations table
    echo "=== MIGRATION STATUS ===\n";
    if (in_array('migrations', $tables)) {
        $stmt = $conn->query("SELECT * FROM migrations ORDER BY batch, id");
        $migrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Applied migrations:\n";
        foreach ($migrations as $mig) {
            echo "  - Batch {$mig['batch']}: {$mig['migration']}\n";
        }
    } else {
        echo "No migrations table found\n";
    }
    echo "\n";

    // Define expected tables
    $expectedTables = [
        // User and auth tables
        'users', 'tenants', 'companies', 'company_users',
        'password_resets', 'personal_access_tokens',

        // Permission tables
        'roles', 'permissions', 'model_has_roles',
        'model_has_permissions', 'role_has_permissions',

        // Calendar tables
        'calendars', 'events', 'calendar_users', 'event_attendees',

        // Task tables
        'task_lists', 'tasks', 'task_occurrences', 'task_assignees',

        // Chat tables
        'chat_channels', 'chat_messages', 'chat_channel_members',
        'chat_message_reads', 'chat_attachments',

        // File management tables
        'folders', 'files', 'file_versions', 'file_shares',

        // Other tables
        'audit_logs', 'notifications', 'push_subscriptions',
        'approvals', 'approval_steps'
    ];

    echo "=== TABLE CHECK ===\n";
    $missingTables = [];
    foreach ($expectedTables as $table) {
        if (!in_array($table, $tables)) {
            $missingTables[] = $table;
            echo "❌ MISSING: $table\n";
        } else {
            echo "✅ EXISTS: $table\n";
        }
    }

    echo "\n=== MULTI-TENANT STRUCTURE CHECK ===\n";
    $tablesNeedingTenantId = array_diff($expectedTables, ['tenants', 'migrations', 'password_resets', 'personal_access_tokens']);

    foreach ($tables as $table) {
        if (in_array($table, $tablesNeedingTenantId)) {
            // Check for tenant_id column
            $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
            $stmt->execute();
            $tenantColumn = $stmt->fetch();

            if ($tenantColumn) {
                // Check for index on tenant_id
                $stmt = $conn->prepare("SHOW INDEX FROM `$table` WHERE Column_name = 'tenant_id'");
                $stmt->execute();
                $index = $stmt->fetchAll();

                if (count($index) > 0) {
                    echo "✅ $table: has tenant_id with index\n";
                } else {
                    echo "⚠️ $table: has tenant_id but NO INDEX\n";
                }
            } else {
                echo "❌ $table: MISSING tenant_id column\n";
            }
        }
    }

    echo "\n=== SUMMARY ===\n";
    echo "Total expected tables: " . count($expectedTables) . "\n";
    echo "Tables found: " . count(array_intersect($expectedTables, $tables)) . "\n";
    echo "Tables missing: " . count($missingTables) . "\n";

    if (count($missingTables) > 0) {
        echo "\nMissing tables that need to be created:\n";
        foreach ($missingTables as $table) {
            echo "  - $table\n";
        }
    }

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
?>