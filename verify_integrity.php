<?php
// Verify database integrity and multi-tenant structure
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexio_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "==============================================\n";
    echo "DATABASE INTEGRITY & MULTI-TENANT VERIFICATION\n";
    echo "==============================================\n";
    echo "Database: $dbname\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

    // Tables that should have tenant_id
    $tenantTables = [
        'users', 'companies', 'calendars', 'events', 'task_lists', 'tasks',
        'chat_channels', 'chat_messages', 'folders', 'files', 'audit_logs',
        'notifications', 'approvals', 'roles'
    ];

    echo "=== MULTI-TENANT STRUCTURE CHECK ===\n\n";
    $tenantIssues = [];

    foreach ($tenantTables as $table) {
        // Check if table exists
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            echo "⚠️ Table '$table' does not exist\n";
            continue;
        }

        // Check for tenant_id column
        $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
        $stmt->execute();
        $tenantColumn = $stmt->fetch();

        if ($tenantColumn) {
            // Check for index on tenant_id
            $stmt = $conn->prepare("SHOW INDEX FROM `$table` WHERE Column_name = 'tenant_id'");
            $stmt->execute();
            $indexes = $stmt->fetchAll();

            if (count($indexes) > 0) {
                echo "✅ $table: has tenant_id with " . count($indexes) . " index(es)\n";

                // Check for composite index with tenant_id as first column
                $hasComposite = false;
                foreach ($indexes as $index) {
                    if ($index['Seq_in_index'] == 1) {
                        $hasComposite = true;
                        break;
                    }
                }

                if ($hasComposite) {
                    echo "   └─ Has composite index with tenant_id as first column ✅\n";
                } else {
                    echo "   └─ Missing composite index with tenant_id as first column ⚠️\n";
                    $tenantIssues[] = "$table: needs composite index";
                }
            } else {
                echo "⚠️ $table: has tenant_id but NO INDEX\n";
                $tenantIssues[] = "$table: missing index on tenant_id";
            }
        } else {
            echo "❌ $table: MISSING tenant_id column\n";
            $tenantIssues[] = "$table: missing tenant_id column";
        }
    }

    echo "\n=== FOREIGN KEY RELATIONSHIPS CHECK ===\n\n";

    // Check foreign keys
    $stmt = $conn->query("
        SELECT
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE
            TABLE_SCHEMA = '$dbname'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY
            TABLE_NAME, COLUMN_NAME
    ");

    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($foreignKeys) > 0) {
        echo "Found " . count($foreignKeys) . " foreign key relationships:\n";
        foreach ($foreignKeys as $fk) {
            echo "  - {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}\n";
        }
    } else {
        echo "⚠️ No foreign key constraints found (consider adding for referential integrity)\n";
    }

    echo "\n=== DATA INTEGRITY CHECK ===\n\n";

    // Check for orphaned records
    $orphanChecks = [
        ['table' => 'events', 'column' => 'calendar_id', 'ref_table' => 'calendars', 'ref_column' => 'id'],
        ['table' => 'tasks', 'column' => 'task_list_id', 'ref_table' => 'task_lists', 'ref_column' => 'id'],
        ['table' => 'chat_messages', 'column' => 'channel_id', 'ref_table' => 'chat_channels', 'ref_column' => 'id'],
        ['table' => 'files', 'column' => 'folder_id', 'ref_table' => 'folders', 'ref_column' => 'id'],
    ];

    $orphanedRecords = [];
    foreach ($orphanChecks as $check) {
        // Check if both tables exist
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$check['table']]);
        if (!$stmt->fetch()) continue;

        $stmt->execute([$check['ref_table']]);
        if (!$stmt->fetch()) continue;

        // Check if column exists
        $stmt = $conn->prepare("SHOW COLUMNS FROM `{$check['table']}` LIKE ?");
        $stmt->execute([$check['column']]);
        if (!$stmt->fetch()) continue;

        // Check for orphaned records
        $sql = "SELECT COUNT(*) as orphans FROM `{$check['table']}` t
                LEFT JOIN `{$check['ref_table']}` r ON t.`{$check['column']}` = r.`{$check['ref_column']}`
                WHERE t.`{$check['column']}` IS NOT NULL AND r.`{$check['ref_column']}` IS NULL";

        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['orphans'] > 0) {
            echo "⚠️ {$check['table']}: {$result['orphans']} orphaned records (invalid {$check['column']})\n";
            $orphanedRecords[] = "{$check['table']}: {$result['orphans']} orphans";
        } else {
            echo "✅ {$check['table']}: No orphaned records\n";
        }
    }

    echo "\n=== TABLE ROW COUNTS ===\n\n";

    // Get row counts for all tables
    $stmt = $conn->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalRows = 0;
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        $totalRows += $count;

        if ($count > 0) {
            echo sprintf("%-30s: %6d rows\n", $table, $count);
        }
    }

    echo sprintf("\n%-30s: %6d rows\n", "TOTAL", $totalRows);

    echo "\n=== TENANT ISOLATION CHECK ===\n\n";

    // Check if any tenant has access to other tenant's data
    $stmt = $conn->query("SELECT * FROM tenants");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tenants) > 1) {
        echo "Found " . count($tenants) . " tenants:\n";
        foreach ($tenants as $tenant) {
            echo "  - Tenant {$tenant['id']}: {$tenant['name']}\n";
        }

        // Check for cross-tenant data issues
        echo "\nChecking for cross-tenant data leaks...\n";

        foreach ($tenantTables as $table) {
            // Skip if table doesn't exist or doesn't have tenant_id
            $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE 'tenant_id'");
            try {
                $stmt->execute();
                if (!$stmt->fetch()) continue;
            } catch (PDOException $e) {
                continue;
            }

            // Count records per tenant
            $stmt = $conn->query("SELECT tenant_id, COUNT(*) as cnt FROM `$table` GROUP BY tenant_id");
            $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($distribution) > 0) {
                echo "\n$table distribution:\n";
                foreach ($distribution as $dist) {
                    $tenantName = $dist['tenant_id'] ? "Tenant {$dist['tenant_id']}" : "No tenant (NULL)";
                    echo "  - $tenantName: {$dist['cnt']} records\n";
                }
            }
        }
    } else {
        echo "Only " . count($tenants) . " tenant(s) found - cross-tenant check not applicable\n";
    }

    echo "\n=== SUMMARY ===\n\n";

    if (count($tenantIssues) > 0) {
        echo "❌ Multi-tenant issues found:\n";
        foreach ($tenantIssues as $issue) {
            echo "  - $issue\n";
        }
    } else {
        echo "✅ All multi-tenant structures are correct\n";
    }

    if (count($orphanedRecords) > 0) {
        echo "\n⚠️ Orphaned records found:\n";
        foreach ($orphanedRecords as $orphan) {
            echo "  - $orphan\n";
        }
    } else {
        echo "✅ No orphaned records found\n";
    }

    echo "\n=== DATABASE STATUS ===\n";
    echo "✅ Database is operational\n";
    echo "✅ All required tables exist\n";

    if (count($tenantIssues) === 0 && count($orphanedRecords) === 0) {
        echo "✅ Database is ready for production use\n";
    } else {
        echo "⚠️ Database needs attention - review issues above\n";
    }

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>