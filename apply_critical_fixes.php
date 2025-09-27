<?php
/**
 * Apply critical database fixes for Nexio Collabora V2
 * Fixes missing tables and columns that are causing API errors
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include configuration
require_once 'config_v2.php';

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "Connected to database: " . DB_NAME . "\n\n";

    // 1. Create calendar_shares table
    echo "1. Creating calendar_shares table...\n";
    $sql = "CREATE TABLE IF NOT EXISTS `calendar_shares` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `tenant_id` BIGINT UNSIGNED NOT NULL COMMENT 'Tenant isolation',
      `calendar_id` BIGINT UNSIGNED NOT NULL COMMENT 'Referenced calendar',
      `shared_with_user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'User the calendar is shared with',
      `shared_with_group_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Group the calendar is shared with',
      `shared_with_email` VARCHAR(255) DEFAULT NULL COMMENT 'External email for sharing',
      `permission_level` ENUM('read', 'write', 'admin') NOT NULL DEFAULT 'read' COMMENT 'Access level',
      `can_share` BOOLEAN DEFAULT FALSE COMMENT 'Can recipient share further',
      `can_edit_events` BOOLEAN DEFAULT FALSE COMMENT 'Can edit events',
      `can_delete_events` BOOLEAN DEFAULT FALSE COMMENT 'Can delete events',
      `can_see_private` BOOLEAN DEFAULT FALSE COMMENT 'Can see private event details',
      `share_token` VARCHAR(64) DEFAULT NULL COMMENT 'Token for external sharing',
      `share_expiry` DATETIME DEFAULT NULL COMMENT 'When share expires',
      `color_override` VARCHAR(7) DEFAULT NULL COMMENT 'Custom color for shared calendar',
      `is_accepted` BOOLEAN DEFAULT TRUE COMMENT 'Share acceptance status',
      `notes` TEXT DEFAULT NULL COMMENT 'Share notes or message',
      `created_by` BIGINT UNSIGNED NOT NULL COMMENT 'User who created share',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_share_token` (`share_token`),
      INDEX `idx_tenant_calendar` (`tenant_id`, `calendar_id`),
      INDEX `idx_shared_with_user` (`shared_with_user_id`),
      INDEX `idx_shared_with_group` (`shared_with_group_id`),
      INDEX `idx_share_expiry` (`share_expiry`),
      INDEX `idx_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Calendar sharing permissions and external access'";

    $pdo->exec($sql);
    echo "   ✓ calendar_shares table created or already exists\n\n";

    // 2. Check and add deleted_at column to task_lists
    echo "2. Checking task_lists.deleted_at column...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'task_lists'
                           AND COLUMN_NAME = 'deleted_at'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding deleted_at column...\n";
        $pdo->exec("ALTER TABLE `task_lists`
                    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL
                    COMMENT 'Soft delete timestamp',
                    ADD INDEX `idx_deleted_at` (`deleted_at`)");
        echo "   ✓ deleted_at column added\n";
    } else {
        echo "   ✓ deleted_at column already exists\n";
    }
    echo "\n";

    // 3. Check and add color column to task_lists
    echo "3. Checking task_lists.color column...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'task_lists'
                           AND COLUMN_NAME = 'color'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding color column...\n";
        $pdo->exec("ALTER TABLE `task_lists`
                    ADD COLUMN `color` VARCHAR(7) DEFAULT '#6B7280'
                    COMMENT 'Hex color for UI display'
                    AFTER `description`");
        echo "   ✓ color column added\n";
    } else {
        echo "   ✓ color column already exists\n";
    }
    echo "\n";

    // 4. Add composite index for performance
    echo "4. Adding performance indexes...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.STATISTICS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'task_lists'
                           AND INDEX_NAME = 'idx_tenant_deleted'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        $pdo->exec("ALTER TABLE `task_lists` ADD INDEX `idx_tenant_deleted` (`tenant_id`, `deleted_at`)");
        echo "   ✓ Index idx_tenant_deleted added\n";
    } else {
        echo "   ✓ Index idx_tenant_deleted already exists\n";
    }
    echo "\n";

    // 5. Verify fixes
    echo "5. Verifying fixes...\n";

    // Check calendar_shares
    $stmt = $pdo->query("SELECT COUNT(*) AS column_count
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'calendar_shares'");
    $result = $stmt->fetch();
    echo "   ✓ calendar_shares table has " . $result['column_count'] . " columns\n";

    // Check task_lists columns
    $stmt = $pdo->query("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = 'task_lists'
                        AND COLUMN_NAME IN ('deleted_at', 'color')
                        ORDER BY ORDINAL_POSITION");
    $columns = $stmt->fetchAll();
    foreach ($columns as $col) {
        echo "   ✓ task_lists." . $col['COLUMN_NAME'] . " (" . $col['DATA_TYPE'] . ")\n";
    }

    echo "\n";
    echo "===========================================\n";
    echo "✅ All database fixes applied successfully!\n";
    echo "===========================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
?>