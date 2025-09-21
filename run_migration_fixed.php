<?php
// Run complete migration script - Fixed Version
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nexio_db";

try {
    // Use mysqli for better multi-statement support
    $mysqli = new mysqli($servername, $username, $password, $dbname);
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }

    echo "=== RUNNING COMPLETE MIGRATION ===\n";
    echo "Database: $dbname\n";
    echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

    // Tables to create
    $tables = [
        // Companies table
        "companies" => "CREATE TABLE IF NOT EXISTS `companies` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `website` VARCHAR(255),
            `email` VARCHAR(255),
            `phone` VARCHAR(50),
            `address` TEXT,
            `city` VARCHAR(100),
            `state` VARCHAR(100),
            `country` VARCHAR(100),
            `postal_code` VARCHAR(20),
            `logo_url` VARCHAR(500),
            `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_tenant_status` (`tenant_id`, `status`),
            INDEX `idx_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Company users
        "company_users" => "CREATE TABLE IF NOT EXISTS `company_users` (
            `company_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `role` ENUM('owner', 'admin', 'member', 'viewer') DEFAULT 'member',
            `department` VARCHAR(100),
            `position` VARCHAR(100),
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`company_id`, `user_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_role` (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Password resets
        "password_resets" => "CREATE TABLE IF NOT EXISTS `password_resets` (
            `email` VARCHAR(255) NOT NULL,
            `token` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`email`),
            INDEX `idx_token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Personal access tokens
        "personal_access_tokens" => "CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tokenable_type` VARCHAR(255) NOT NULL,
            `tokenable_id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `abilities` TEXT,
            `last_used_at` TIMESTAMP NULL,
            `expires_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_token` (`token`),
            INDEX `idx_tokenable` (`tokenable_type`, `tokenable_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // RBAC tables
        "model_has_roles" => "CREATE TABLE IF NOT EXISTS `model_has_roles` (
            `role_id` BIGINT UNSIGNED NOT NULL,
            `model_type` VARCHAR(255) NOT NULL,
            `model_id` BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (`role_id`, `model_type`, `model_id`),
            INDEX `idx_model` (`model_type`, `model_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "model_has_permissions" => "CREATE TABLE IF NOT EXISTS `model_has_permissions` (
            `permission_id` BIGINT UNSIGNED NOT NULL,
            `model_type` VARCHAR(255) NOT NULL,
            `model_id` BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (`permission_id`, `model_type`, `model_id`),
            INDEX `idx_model` (`model_type`, `model_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "role_has_permissions" => "CREATE TABLE IF NOT EXISTS `role_has_permissions` (
            `permission_id` BIGINT UNSIGNED NOT NULL,
            `role_id` BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY (`permission_id`, `role_id`),
            INDEX `idx_role` (`role_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Calendar tables
        "calendar_users" => "CREATE TABLE IF NOT EXISTS `calendar_users` (
            `calendar_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `permission` ENUM('owner', 'write', 'read') DEFAULT 'read',
            `color_override` VARCHAR(7),
            `is_hidden` BOOLEAN DEFAULT FALSE,
            `notification_enabled` BOOLEAN DEFAULT TRUE,
            `shared_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`calendar_id`, `user_id`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "event_attendees" => "CREATE TABLE IF NOT EXISTS `event_attendees` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED,
            `email` VARCHAR(255),
            `name` VARCHAR(255),
            `response_status` ENUM('accepted', 'declined', 'tentative', 'needs-action') DEFAULT 'needs-action',
            `role` ENUM('required', 'optional', 'chair') DEFAULT 'required',
            `comment` TEXT,
            `responded_at` TIMESTAMP NULL,
            `reminder_sent` BOOLEAN DEFAULT FALSE,
            PRIMARY KEY (`id`),
            INDEX `idx_event` (`event_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_status` (`response_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Task tables
        "task_lists" => "CREATE TABLE IF NOT EXISTS `task_lists` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `color` VARCHAR(7) DEFAULT '#4F46E5',
            `icon` VARCHAR(50),
            `position` INT DEFAULT 0,
            `view_type` ENUM('kanban', 'list', 'calendar', 'gantt') DEFAULT 'kanban',
            `is_archived` BOOLEAN DEFAULT FALSE,
            `settings` JSON,
            `created_by` INT UNSIGNED,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_tenant` (`tenant_id`),
            INDEX `idx_position` (`position`),
            INDEX `idx_archived` (`is_archived`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "task_occurrences" => "CREATE TABLE IF NOT EXISTS `task_occurrences` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `task_id` INT UNSIGNED NOT NULL,
            `occurrence_date` DATE NOT NULL,
            `status` ENUM('pending', 'completed', 'skipped') DEFAULT 'pending',
            `completed_at` TIMESTAMP NULL,
            `completed_by` INT UNSIGNED,
            `notes` TEXT,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_task_date` (`task_id`, `occurrence_date`),
            INDEX `idx_date` (`occurrence_date`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "task_assignees" => "CREATE TABLE IF NOT EXISTS `task_assignees` (
            `task_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `assigned_by` INT UNSIGNED,
            `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `accepted_at` TIMESTAMP NULL,
            PRIMARY KEY (`task_id`, `user_id`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Chat tables
        "chat_channels" => "CREATE TABLE IF NOT EXISTS `chat_channels` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `type` ENUM('public', 'private', 'direct') DEFAULT 'public',
            `description` TEXT,
            `topic` VARCHAR(500),
            `avatar_url` VARCHAR(500),
            `created_by` INT UNSIGNED,
            `is_archived` BOOLEAN DEFAULT FALSE,
            `is_read_only` BOOLEAN DEFAULT FALSE,
            `metadata` JSON,
            `last_activity_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_tenant_type` (`tenant_id`, `type`),
            INDEX `idx_archived` (`is_archived`),
            INDEX `idx_last_activity` (`last_activity_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "chat_messages" => "CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `channel_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `content` TEXT,
            `message_type` ENUM('text', 'system', 'file', 'image', 'notification') DEFAULT 'text',
            `parent_message_id` BIGINT UNSIGNED NULL,
            `attachment_id` INT UNSIGNED NULL,
            `metadata` JSON,
            `is_edited` BOOLEAN DEFAULT FALSE,
            `edited_at` TIMESTAMP NULL,
            `is_deleted` BOOLEAN DEFAULT FALSE,
            `deleted_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_channel_created` (`channel_id`, `created_at`),
            INDEX `idx_parent` (`parent_message_id`),
            INDEX `idx_user` (`user_id`),
            FULLTEXT `ft_content` (`content`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "chat_channel_members" => "CREATE TABLE IF NOT EXISTS `chat_channel_members` (
            `channel_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `role` ENUM('owner', 'admin', 'moderator', 'member') DEFAULT 'member',
            `notification_preference` ENUM('all', 'mentions', 'none') DEFAULT 'all',
            `muted_until` TIMESTAMP NULL,
            `last_read_message_id` BIGINT UNSIGNED,
            `unread_count` INT DEFAULT 0,
            `mention_count` INT DEFAULT 0,
            `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `left_at` TIMESTAMP NULL,
            PRIMARY KEY (`channel_id`, `user_id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_unread` (`unread_count`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "chat_message_reads" => "CREATE TABLE IF NOT EXISTS `chat_message_reads` (
            `message_id` BIGINT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`message_id`, `user_id`),
            INDEX `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "chat_attachments" => "CREATE TABLE IF NOT EXISTS `chat_attachments` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `message_id` BIGINT UNSIGNED NOT NULL,
            `file_id` INT UNSIGNED,
            `file_name` VARCHAR(255),
            `file_type` VARCHAR(100),
            `file_size` BIGINT,
            `file_url` VARCHAR(500),
            `thumbnail_url` VARCHAR(500),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_message` (`message_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // File management tables
        "file_versions" => "CREATE TABLE IF NOT EXISTS `file_versions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `file_id` INT UNSIGNED NOT NULL,
            `version_number` INT NOT NULL,
            `file_path` VARCHAR(500) NOT NULL,
            `file_size` BIGINT NOT NULL,
            `hash` VARCHAR(64),
            `comment` TEXT,
            `created_by` INT UNSIGNED,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_file_version` (`file_id`, `version_number`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "file_shares" => "CREATE TABLE IF NOT EXISTS `file_shares` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `file_id` INT UNSIGNED,
            `folder_id` INT UNSIGNED,
            `shared_by` INT UNSIGNED NOT NULL,
            `shared_with_user_id` INT UNSIGNED,
            `shared_with_email` VARCHAR(255),
            `permission` ENUM('view', 'download', 'edit', 'delete') DEFAULT 'view',
            `share_token` VARCHAR(64),
            `password` VARCHAR(255),
            `expires_at` TIMESTAMP NULL,
            `accessed_count` INT DEFAULT 0,
            `last_accessed_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_token` (`share_token`),
            INDEX `idx_file` (`file_id`),
            INDEX `idx_folder` (`folder_id`),
            INDEX `idx_shared_by` (`shared_by`),
            INDEX `idx_shared_with` (`shared_with_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Notification tables
        "push_subscriptions" => "CREATE TABLE IF NOT EXISTS `push_subscriptions` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `endpoint` TEXT NOT NULL,
            `public_key` VARCHAR(255),
            `auth_token` VARCHAR(255),
            `content_encoding` VARCHAR(50),
            `device_type` VARCHAR(50),
            `device_name` VARCHAR(100),
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_user` (`user_id`),
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Workflow tables
        "approvals" => "CREATE TABLE IF NOT EXISTS `approvals` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tenant_id` INT UNSIGNED NOT NULL,
            `entity_type` VARCHAR(100) NOT NULL,
            `entity_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT,
            `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
            `requested_by` INT UNSIGNED NOT NULL,
            `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `completed_at` TIMESTAMP NULL,
            `due_date` TIMESTAMP NULL,
            `metadata` JSON,
            PRIMARY KEY (`id`),
            INDEX `idx_tenant_status` (`tenant_id`, `status`),
            INDEX `idx_entity` (`entity_type`, `entity_id`),
            INDEX `idx_requested_by` (`requested_by`),
            INDEX `idx_due_date` (`due_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "approval_steps" => "CREATE TABLE IF NOT EXISTS `approval_steps` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `approval_id` INT UNSIGNED NOT NULL,
            `step_number` INT NOT NULL,
            `approver_id` INT UNSIGNED NOT NULL,
            `status` ENUM('pending', 'approved', 'rejected', 'skipped') DEFAULT 'pending',
            `comments` TEXT,
            `responded_at` TIMESTAMP NULL,
            `is_required` BOOLEAN DEFAULT TRUE,
            `can_edit` BOOLEAN DEFAULT FALSE,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_approval_step` (`approval_id`, `step_number`),
            INDEX `idx_approver` (`approver_id`),
            INDEX `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Migrations table
        "migrations" => "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    // Execute each table creation
    $successCount = 0;
    $failCount = 0;

    foreach ($tables as $tableName => $createSql) {
        if ($mysqli->query($createSql)) {
            echo "✅ Created/verified table: $tableName\n";
            $successCount++;
        } else {
            echo "❌ Error creating table $tableName: " . $mysqli->error . "\n";
            $failCount++;
        }
    }

    // Add tenant_id to permissions table if missing
    echo "\n=== CHECKING PERMISSIONS TABLE ===\n";
    $result = $mysqli->query("SHOW COLUMNS FROM permissions LIKE 'tenant_id'");
    if ($result->num_rows == 0) {
        if ($mysqli->query("ALTER TABLE `permissions` ADD COLUMN `tenant_id` INT UNSIGNED NULL AFTER `id`, ADD INDEX `idx_tenant` (`tenant_id`)")) {
            echo "✅ Added tenant_id column to permissions table\n";
        } else {
            echo "❌ Error adding tenant_id to permissions: " . $mysqli->error . "\n";
        }
    } else {
        echo "✅ permissions table already has tenant_id column\n";
    }

    // Insert migration record
    $mysqli->query("INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES ('complete_migration', 1)");

    echo "\n=== MIGRATION SUMMARY ===\n";
    echo "Tables created/verified: $successCount\n";
    echo "Errors: $failCount\n";

    // Final verification
    echo "\n=== FINAL VERIFICATION ===\n";
    $result = $mysqli->query("SHOW TABLES");
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

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
    $missingTables = [];

    foreach ($expectedTables as $table) {
        if (in_array($table, $tables)) {
            $foundCount++;
        } else {
            $missingCount++;
            $missingTables[] = $table;
        }
    }

    echo "Total expected tables: " . count($expectedTables) . "\n";
    echo "Tables found: $foundCount\n";
    echo "Tables missing: $missingCount\n";

    if ($missingCount > 0) {
        echo "\nMissing tables:\n";
        foreach ($missingTables as $table) {
            echo "  - $table\n";
        }
    }

    if ($missingCount === 0) {
        echo "\n🎉 SUCCESS: All required tables are now present!\n";
    } else {
        echo "\n⚠️ WARNING: Some tables are still missing.\n";
    }

    $mysqli->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>