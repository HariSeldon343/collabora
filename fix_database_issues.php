<?php
/**
 * Database Fix Script for Nexiosolution Collabora
 * Fixes unbuffered query issues and creates missing tables
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Include configuration
require_once __DIR__ . '/config_v2.php';

echo "\n" . str_repeat("=", 60) . "\n";
echo "NEXIOSOLUTION COLLABORA - DATABASE FIX\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Create connection with buffered queries enabled
    echo "1. Connecting to database...\n";
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "   ✓ Connected successfully\n\n";

    // Check existing tables
    echo "2. Checking existing tables...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Found " . count($existingTables) . " tables\n";

    // Define required tables
    $requiredTables = [
        // Chat tables
        'chat_channels' => "CREATE TABLE IF NOT EXISTS `chat_channels` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` int(11) NOT NULL,
            `name` varchar(100) NOT NULL,
            `description` text,
            `type` enum('public','private','direct') DEFAULT 'public',
            `created_by` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tenant` (`tenant_id`),
            KEY `idx_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'chat_channel_members' => "CREATE TABLE IF NOT EXISTS `chat_channel_members` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `channel_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `role` enum('owner','admin','member') DEFAULT 'member',
            `joined_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_read_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_channel_user` (`channel_id`,`user_id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'chat_messages' => "CREATE TABLE IF NOT EXISTS `chat_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `channel_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `message` text NOT NULL,
            `type` enum('text','file','system') DEFAULT 'text',
            `metadata` json DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_channel` (`channel_id`),
            KEY `idx_user` (`user_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Calendar tables
        'calendars' => "CREATE TABLE IF NOT EXISTS `calendars` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `name` varchar(100) NOT NULL,
            `color` varchar(7) DEFAULT '#2563eb',
            `description` text,
            `is_default` tinyint(1) DEFAULT 0,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tenant_user` (`tenant_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'events' => "CREATE TABLE IF NOT EXISTS `events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `calendar_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `description` text,
            `location` varchar(255) DEFAULT NULL,
            `start_datetime` datetime NOT NULL,
            `end_datetime` datetime NOT NULL,
            `all_day` tinyint(1) DEFAULT 0,
            `recurrence_rule` varchar(255) DEFAULT NULL,
            `color` varchar(7) DEFAULT NULL,
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_calendar` (`calendar_id`),
            KEY `idx_dates` (`start_datetime`, `end_datetime`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Task tables
        'task_lists' => "CREATE TABLE IF NOT EXISTS `task_lists` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` int(11) NOT NULL,
            `name` varchar(100) NOT NULL,
            `description` text,
            `color` varchar(7) DEFAULT '#10b981',
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tenant` (`tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        'tasks' => "CREATE TABLE IF NOT EXISTS `tasks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `task_list_id` int(11) NOT NULL,
            `title` varchar(255) NOT NULL,
            `description` text,
            `status` enum('todo','in_progress','done','archived') DEFAULT 'todo',
            `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
            `due_date` date DEFAULT NULL,
            `assigned_to` int(11) DEFAULT NULL,
            `created_by` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `completed_at` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_list` (`task_list_id`),
            KEY `idx_status` (`status`),
            KEY `idx_assigned` (`assigned_to`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Session table
        'user_sessions' => "CREATE TABLE IF NOT EXISTS `user_sessions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `session_id` varchar(128) NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` text,
            `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_session` (`session_id`),
            KEY `idx_user` (`user_id`),
            KEY `idx_activity` (`last_activity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    // Create missing tables
    echo "\n3. Creating missing tables...\n";
    $created = 0;
    foreach ($requiredTables as $tableName => $createSQL) {
        if (!in_array($tableName, $existingTables)) {
            echo "   Creating table: $tableName...";
            try {
                $pdo->exec($createSQL);
                echo " ✓\n";
                $created++;
            } catch (PDOException $e) {
                echo " ✗ (" . $e->getMessage() . ")\n";
            }
        }
    }

    if ($created > 0) {
        echo "   Created $created new tables\n";
    } else {
        echo "   All required tables already exist\n";
    }

    // Additional indexes for performance
    echo "\n4. Adding performance indexes...\n";
    $indexes = [
        "ALTER TABLE `chat_messages` ADD INDEX IF NOT EXISTS `idx_channel_created` (`channel_id`, `created_at`)",
        "ALTER TABLE `events` ADD INDEX IF NOT EXISTS `idx_calendar_dates` (`calendar_id`, `start_datetime`, `end_datetime`)",
        "ALTER TABLE `tasks` ADD INDEX IF NOT EXISTS `idx_list_status` (`task_list_id`, `status`)"
    ];

    foreach ($indexes as $indexSQL) {
        try {
            $pdo->exec($indexSQL);
            echo "   ✓ Index added/verified\n";
        } catch (PDOException $e) {
            // Ignore if index already exists
            if (strpos($e->getMessage(), 'Duplicate key') === false) {
                echo "   ✗ " . $e->getMessage() . "\n";
            }
        }
    }

    // Verify admin user exists
    echo "\n5. Verifying admin user...\n";
    $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE email = ?");
    $stmt->execute(['asamodeo@fortibyte.it']);
    $admin = $stmt->fetch();

    if ($admin) {
        echo "   ✓ Admin user exists (ID: {$admin['id']}, Role: {$admin['role']})\n";
    } else {
        echo "   ✗ Admin user not found - creating...\n";

        // Create admin user
        $hashedPassword = password_hash('Ricord@1991', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, nome, cognome, role, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            'asamodeo@fortibyte.it',
            $hashedPassword,
            'Admin',
            'User',
            'admin'
        ]);
        echo "   ✓ Admin user created\n";
    }

    // Summary
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "DATABASE FIX COMPLETED SUCCESSFULLY\n";
    echo str_repeat("=", 60) . "\n\n";

    echo "Summary:\n";
    echo "- Tables checked: " . count($requiredTables) . "\n";
    echo "- Tables created: $created\n";
    echo "- Admin user: ✓\n";
    echo "- Database is ready for use\n\n";

    echo "Next steps:\n";
    echo "1. Test login at: http://localhost/Nexiosolution/collabora/\n";
    echo "2. Use credentials: asamodeo@fortibyte.it / Ricord@1991\n";
    echo "3. Check all modules are working\n";

} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}