<?php
// Apply final database fixes
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexio_db";

try {
    $mysqli = new mysqli($servername, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }

    echo "=== APPLYING FINAL DATABASE FIXES ===\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

    // Fix 1: Add missing index on chat_messages.tenant_id
    echo "Adding index on chat_messages.tenant_id...\n";
    $result = $mysqli->query("SHOW INDEX FROM chat_messages WHERE Column_name = 'tenant_id'");
    if ($result->num_rows == 0) {
        if ($mysqli->query("ALTER TABLE `chat_messages` ADD INDEX `idx_tenant` (`tenant_id`)")) {
            echo "✅ Added index on chat_messages.tenant_id\n";
        } else {
            echo "❌ Error: " . $mysqli->error . "\n";
        }
    } else {
        echo "✅ Index already exists on chat_messages.tenant_id\n";
    }

    // Fix 2: Add composite index on chat_messages
    echo "\nAdding composite index on chat_messages...\n";
    $result = $mysqli->query("SHOW INDEX FROM chat_messages WHERE Key_name = 'idx_tenant_channel'");
    if ($result->num_rows == 0) {
        if ($mysqli->query("ALTER TABLE `chat_messages` ADD INDEX `idx_tenant_channel` (`tenant_id`, `channel_id`)")) {
            echo "✅ Added composite index on chat_messages (tenant_id, channel_id)\n";
        } else {
            echo "❌ Error: " . $mysqli->error . "\n";
        }
    } else {
        echo "✅ Composite index already exists\n";
    }

    echo "\n=== FINAL VERIFICATION ===\n";

    // Verify all tenant_id columns have indexes
    $tables = ['users', 'companies', 'calendars', 'events', 'task_lists', 'tasks',
               'chat_channels', 'chat_messages', 'folders', 'files', 'audit_logs',
               'notifications', 'approvals', 'roles'];

    $allGood = true;
    foreach ($tables as $table) {
        $result = $mysqli->query("SHOW INDEX FROM `$table` WHERE Column_name = 'tenant_id'");
        if ($result && $result->num_rows > 0) {
            echo "✅ $table has index on tenant_id\n";
        } else {
            echo "❌ $table missing index on tenant_id\n";
            $allGood = false;
        }
    }

    if ($allGood) {
        echo "\n🎉 SUCCESS: All tables have proper indexes!\n";
        echo "✅ Database is fully optimized and production-ready!\n";
    } else {
        echo "\n⚠️ Some issues remain - please review above\n";
    }

    $mysqli->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>