<?php
/**
 * Fix Chat Database Issues
 * Adds missing columns for chat functionality
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

    // 1. Check and add avatar column to users table
    echo "1. Checking users.avatar column...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'
                           AND COLUMN_NAME = 'avatar'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding avatar column to users table...\n";
        $pdo->exec("ALTER TABLE `users`
                    ADD COLUMN `avatar` VARCHAR(255) DEFAULT NULL
                    COMMENT 'User avatar URL or path'
                    AFTER `role`");
        echo "   ✓ avatar column added\n";
    } else {
        echo "   ✓ avatar column already exists\n";
    }
    echo "\n";

    // 2. Check and add last_activity column to users table
    echo "2. Checking users.last_activity column...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'
                           AND COLUMN_NAME = 'last_activity'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding last_activity column to users table...\n";
        $pdo->exec("ALTER TABLE `users`
                    ADD COLUMN `last_activity` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    COMMENT 'Last user activity timestamp'
                    AFTER `last_login_at`");
        echo "   ✓ last_activity column added\n";
    } else {
        echo "   ✓ last_activity column already exists\n";
    }
    echo "\n";

    // 3. Check and add last_activity_at column to users table
    echo "3. Checking users.last_activity_at column...\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users'
                           AND COLUMN_NAME = 'last_activity_at'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding last_activity_at column to users table...\n";
        $pdo->exec("ALTER TABLE `users`
                    ADD COLUMN `last_activity_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    COMMENT 'Last user activity timestamp'");
        echo "   ✓ last_activity_at column added\n";
    } else {
        echo "   ✓ last_activity_at column already exists\n";
    }
    echo "\n";

    // 4. Check and create chat_presence table
    echo "4. Creating/verifying chat_presence table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `chat_presence` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `tenant_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `status` ENUM('online', 'away', 'offline', 'busy', 'do_not_disturb') DEFAULT 'offline',
        `status_message` VARCHAR(255) DEFAULT NULL,
        `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `current_channel_id` BIGINT UNSIGNED DEFAULT NULL,
        `device_info` JSON DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_tenant_user` (`tenant_id`, `user_id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_last_activity` (`last_activity`),
        INDEX `idx_channel` (`current_channel_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ chat_presence table created or verified\n\n";

    // 5. Check and create message_reads table
    echo "5. Creating/verifying message_reads table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `message_reads` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `channel_id` BIGINT UNSIGNED NOT NULL,
        `last_read_message_id` BIGINT UNSIGNED DEFAULT NULL,
        `unread_count` INT DEFAULT 0,
        `unread_mentions` INT DEFAULT 0,
        `last_read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_user_channel` (`user_id`, `channel_id`),
        INDEX `idx_last_read` (`last_read_message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ message_reads table created or verified\n\n";

    // 6. Check and create chat_typing_indicators table
    echo "6. Creating/verifying chat_typing_indicators table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `chat_typing_indicators` (
        `channel_id` BIGINT UNSIGNED NOT NULL,
        `user_id` BIGINT UNSIGNED NOT NULL,
        `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `expires_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`channel_id`, `user_id`),
        INDEX `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "   ✓ chat_typing_indicators table created or verified\n\n";

    // 7. Verify chat_channels table columns
    echo "7. Verifying chat_channels table structure...\n";

    // Add metadata column if missing
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'chat_channels'
                           AND COLUMN_NAME = 'metadata'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding metadata column to chat_channels...\n";
        $pdo->exec("ALTER TABLE `chat_channels`
                    ADD COLUMN `metadata` JSON DEFAULT NULL
                    COMMENT 'Additional channel metadata'");
        echo "   ✓ metadata column added\n";
    } else {
        echo "   ✓ metadata column already exists\n";
    }

    // Add is_archived column if missing
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'chat_channels'
                           AND COLUMN_NAME = 'is_archived'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding is_archived column to chat_channels...\n";
        $pdo->exec("ALTER TABLE `chat_channels`
                    ADD COLUMN `is_archived` BOOLEAN DEFAULT FALSE
                    COMMENT 'Whether channel is archived'");
        echo "   ✓ is_archived column added\n";
    } else {
        echo "   ✓ is_archived column already exists\n";
    }

    // 8. Verify chat_messages table columns
    echo "\n8. Verifying chat_messages table structure...\n";

    // Add deleted_at column if missing
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'chat_messages'
                           AND COLUMN_NAME = 'deleted_at'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding deleted_at column to chat_messages...\n";
        $pdo->exec("ALTER TABLE `chat_messages`
                    ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL
                    COMMENT 'Soft delete timestamp',
                    ADD INDEX `idx_deleted_at` (`deleted_at`)");
        echo "   ✓ deleted_at column added\n";
    } else {
        echo "   ✓ deleted_at column already exists\n";
    }

    // 9. Verify chat_channel_members table columns
    echo "\n9. Verifying chat_channel_members table structure...\n";

    // Add notification_preference column if missing
    $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'chat_channel_members'
                           AND COLUMN_NAME = 'notification_preference'");
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        echo "   Adding notification_preference column to chat_channel_members...\n";
        $pdo->exec("ALTER TABLE `chat_channel_members`
                    ADD COLUMN `notification_preference` ENUM('all', 'mentions', 'none') DEFAULT 'all'
                    COMMENT 'Notification settings for this channel'");
        echo "   ✓ notification_preference column added\n";
    } else {
        echo "   ✓ notification_preference column already exists\n";
    }

    // 10. Insert test data for presence if tables are empty
    echo "\n10. Adding sample presence data if needed...\n";

    // Check if we have any users
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch();

    if ($user) {
        // Check if presence record exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM chat_presence WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        if ($stmt->fetchColumn() == 0) {
            // Add presence record for first user
            $stmt = $pdo->prepare("INSERT INTO chat_presence
                                   (tenant_id, user_id, status)
                                   VALUES (1, ?, 'online')");
            $stmt->execute([$user['id']]);
            echo "   ✓ Sample presence data added\n";
        } else {
            echo "   ✓ Presence data already exists\n";
        }
    }

    echo "\n";
    echo "===========================================\n";
    echo "✅ All chat database fixes applied successfully!\n";
    echo "===========================================\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    exit(1);
}
?>