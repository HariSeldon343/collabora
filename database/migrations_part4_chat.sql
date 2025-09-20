-- =====================================================
-- NEXIOSOLUTION COLLABORATIVE PLATFORM
-- Part 4: Chat & Communication Module
-- Database Migration Script
-- =====================================================
-- Database: nexio_collabora_v2
-- Compatible with: MySQL 8.0+ / MariaDB 10.6+
-- Multi-tenant architecture with tenant isolation
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- =====================================================
-- TABLE 1: chat_channels
-- Purpose: Main channels for team communication
-- =====================================================
CREATE TABLE IF NOT EXISTS `chat_channels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `type` ENUM('public', 'private', 'direct') NOT NULL DEFAULT 'public',
  `name` VARCHAR(255) DEFAULT NULL COMMENT 'NULL for direct messages',
  `description` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_archived` BOOLEAN NOT NULL DEFAULT FALSE,
  `metadata` JSON DEFAULT NULL COMMENT 'Channel settings, permissions, etc.',

  PRIMARY KEY (`id`),
  INDEX `idx_tenant_type` (`tenant_id`, `type`),
  INDEX `idx_tenant_archived` (`tenant_id`, `is_archived`),
  INDEX `idx_created_by` (`created_by`),
  INDEX `idx_created_at` (`created_at` DESC),

  CONSTRAINT `fk_chat_channels_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_channels_creator`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Communication channels for teams and direct messages';

-- =====================================================
-- TABLE 2: chat_channel_members
-- Purpose: Track channel membership and roles
-- =====================================================
CREATE TABLE IF NOT EXISTS `chat_channel_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('owner', 'admin', 'member') NOT NULL DEFAULT 'member',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `muted_until` TIMESTAMP NULL DEFAULT NULL,
  `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
  `notification_preference` ENUM('all', 'mentions', 'none') DEFAULT 'all',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_channel_user` (`channel_id`, `user_id`),
  INDEX `idx_user_channels` (`user_id`, `joined_at` DESC),
  INDEX `idx_channel_roles` (`channel_id`, `role`),
  INDEX `idx_muted` (`muted_until`),

  CONSTRAINT `fk_channel_members_channel`
    FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_channel_members_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Channel membership with roles and preferences';

-- =====================================================
-- TABLE 3: chat_messages
-- Purpose: Store all messages with threading support
-- =====================================================
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `parent_message_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'For threaded replies',
  `content` TEXT NOT NULL,
  `message_type` ENUM('text', 'file', 'system', 'notification') NOT NULL DEFAULT 'text',
  `attachment_id` BIGINT UNSIGNED DEFAULT NULL,
  `edited_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `metadata` JSON DEFAULT NULL COMMENT 'Additional message data',

  PRIMARY KEY (`id`),
  INDEX `idx_channel_created` (`channel_id`, `created_at` DESC),
  INDEX `idx_channel_parent` (`channel_id`, `parent_message_id`),
  INDEX `idx_user_messages` (`user_id`, `created_at` DESC),
  INDEX `idx_deleted` (`deleted_at`),
  INDEX `idx_attachment` (`attachment_id`),
  FULLTEXT INDEX `idx_content_search` (`content`),

  CONSTRAINT `fk_messages_channel`
    FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_messages_parent`
    FOREIGN KEY (`parent_message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_attachment`
    FOREIGN KEY (`attachment_id`) REFERENCES `files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Chat messages with threading and file attachments';

-- =====================================================
-- TABLE 4: message_reactions
-- Purpose: Emoji reactions on messages
-- =====================================================
CREATE TABLE IF NOT EXISTS `message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `emoji` VARCHAR(10) NOT NULL COMMENT 'Unicode emoji or shortcode',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_user_emoji` (`message_id`, `user_id`, `emoji`),
  INDEX `idx_message_reactions` (`message_id`),
  INDEX `idx_user_reactions` (`user_id`),

  CONSTRAINT `fk_reactions_message`
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reactions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Emoji reactions on messages';

-- =====================================================
-- TABLE 5: message_mentions
-- Purpose: Track @mentions for notifications
-- =====================================================
CREATE TABLE IF NOT EXISTS `message_mentions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `mentioned_user_id` BIGINT UNSIGNED NOT NULL,
  `mention_type` ENUM('user', 'everyone', 'here') DEFAULT 'user',
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_mention` (`message_id`, `mentioned_user_id`),
  INDEX `idx_user_mentions_unread` (`mentioned_user_id`, `read_at`),
  INDEX `idx_message_mentions` (`message_id`),

  CONSTRAINT `fk_mentions_message`
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mentions_user`
    FOREIGN KEY (`mentioned_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track user mentions for notifications';

-- =====================================================
-- TABLE 6: message_reads
-- Purpose: Track read status and unread counts
-- =====================================================
CREATE TABLE IF NOT EXISTS `message_reads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `last_read_message_id` BIGINT UNSIGNED DEFAULT NULL,
  `unread_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `unread_mentions` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_channel` (`user_id`, `channel_id`),
  INDEX `idx_channel_reads` (`channel_id`),
  INDEX `idx_unread` (`user_id`, `unread_count`),

  CONSTRAINT `fk_reads_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reads_channel`
    FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reads_message`
    FOREIGN KEY (`last_read_message_id`) REFERENCES `chat_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track message read status per user per channel';

-- =====================================================
-- TABLE 7: chat_presence
-- Purpose: Track user online status
-- =====================================================
CREATE TABLE IF NOT EXISTS `chat_presence` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('online', 'away', 'offline', 'busy', 'do_not_disturb') NOT NULL DEFAULT 'offline',
  `status_message` VARCHAR(255) DEFAULT NULL,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_typing_at` TIMESTAMP NULL DEFAULT NULL,
  `current_channel_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_info` JSON DEFAULT NULL COMMENT 'Device and connection info',

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_user` (`tenant_id`, `user_id`),
  INDEX `idx_tenant_status` (`tenant_id`, `status`),
  INDEX `idx_last_activity` (`last_activity`),
  INDEX `idx_current_channel` (`current_channel_id`),

  CONSTRAINT `fk_presence_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presence_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_presence_channel`
    FOREIGN KEY (`current_channel_id`) REFERENCES `chat_channels` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Real-time user presence and activity status';

-- =====================================================
-- ADDITIONAL SUPPORT TABLES
-- =====================================================

-- Table for typing indicators
CREATE TABLE IF NOT EXISTS `chat_typing_indicators` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 10 SECOND),

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_channel_user_typing` (`channel_id`, `user_id`),
  INDEX `idx_expires` (`expires_at`),

  CONSTRAINT `fk_typing_channel`
    FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_typing_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Real-time typing indicators';

-- Table for pinned messages
CREATE TABLE IF NOT EXISTS `chat_pinned_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `pinned_by` BIGINT UNSIGNED NOT NULL,
  `pinned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_channel_message` (`channel_id`, `message_id`),
  INDEX `idx_channel_pinned` (`channel_id`, `pinned_at` DESC),

  CONSTRAINT `fk_pinned_channel`
    FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pinned_message`
    FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pinned_user`
    FOREIGN KEY (`pinned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pinned important messages per channel';

-- =====================================================
-- PERFORMANCE OPTIMIZATION VIEWS
-- =====================================================

-- View for unread message counts
CREATE OR REPLACE VIEW `v_unread_messages` AS
SELECT
  mr.user_id,
  mr.channel_id,
  c.tenant_id,
  c.name as channel_name,
  c.type as channel_type,
  mr.unread_count,
  mr.unread_mentions,
  mr.updated_at
FROM message_reads mr
JOIN chat_channels c ON mr.channel_id = c.id
WHERE mr.unread_count > 0;

-- View for active channels
CREATE OR REPLACE VIEW `v_active_channels` AS
SELECT
  c.id,
  c.tenant_id,
  c.name,
  c.type,
  COUNT(DISTINCT cm.user_id) as member_count,
  MAX(m.created_at) as last_message_at,
  COUNT(m.id) as message_count
FROM chat_channels c
LEFT JOIN chat_channel_members cm ON c.id = cm.channel_id
LEFT JOIN chat_messages m ON c.id = m.channel_id AND m.deleted_at IS NULL
WHERE c.is_archived = FALSE
GROUP BY c.id;

-- =====================================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- =====================================================

DELIMITER //

-- Procedure to create a direct message channel
CREATE PROCEDURE `sp_create_direct_channel`(
  IN p_tenant_id INT,
  IN p_user1_id BIGINT,
  IN p_user2_id BIGINT
)
BEGIN
  DECLARE v_channel_id BIGINT;

  -- Check if direct channel already exists
  SELECT c.id INTO v_channel_id
  FROM chat_channels c
  JOIN chat_channel_members cm1 ON c.id = cm1.channel_id AND cm1.user_id = p_user1_id
  JOIN chat_channel_members cm2 ON c.id = cm2.channel_id AND cm2.user_id = p_user2_id
  WHERE c.tenant_id = p_tenant_id AND c.type = 'direct'
  LIMIT 1;

  IF v_channel_id IS NULL THEN
    -- Create new direct channel
    INSERT INTO chat_channels (tenant_id, type, created_by)
    VALUES (p_tenant_id, 'direct', p_user1_id);

    SET v_channel_id = LAST_INSERT_ID();

    -- Add both users as members
    INSERT INTO chat_channel_members (channel_id, user_id, role)
    VALUES
      (v_channel_id, p_user1_id, 'member'),
      (v_channel_id, p_user2_id, 'member');
  END IF;

  SELECT v_channel_id as channel_id;
END//

-- Procedure to mark messages as read
CREATE PROCEDURE `sp_mark_messages_read`(
  IN p_user_id BIGINT,
  IN p_channel_id BIGINT,
  IN p_last_message_id BIGINT
)
BEGIN
  -- Update or insert read status
  INSERT INTO message_reads (user_id, channel_id, last_read_message_id, unread_count, unread_mentions)
  VALUES (p_user_id, p_channel_id, p_last_message_id, 0, 0)
  ON DUPLICATE KEY UPDATE
    last_read_message_id = p_last_message_id,
    unread_count = 0,
    unread_mentions = 0,
    updated_at = CURRENT_TIMESTAMP;

  -- Mark mentions as read
  UPDATE message_mentions mm
  JOIN chat_messages m ON mm.message_id = m.id
  SET mm.read_at = CURRENT_TIMESTAMP
  WHERE mm.mentioned_user_id = p_user_id
    AND m.channel_id = p_channel_id
    AND mm.read_at IS NULL
    AND m.id <= p_last_message_id;
END//

DELIMITER ;

-- =====================================================
-- TRIGGERS FOR DATA INTEGRITY
-- =====================================================

DELIMITER //

-- Trigger to update unread counts on new message
CREATE TRIGGER `trg_after_message_insert`
AFTER INSERT ON `chat_messages`
FOR EACH ROW
BEGIN
  -- Update unread count for all channel members except sender
  UPDATE message_reads mr
  JOIN chat_channel_members cm ON mr.channel_id = cm.channel_id AND mr.user_id = cm.user_id
  SET mr.unread_count = mr.unread_count + 1,
      mr.updated_at = CURRENT_TIMESTAMP
  WHERE mr.channel_id = NEW.channel_id
    AND mr.user_id != NEW.user_id
    AND NEW.deleted_at IS NULL;

  -- Initialize read status for new members
  INSERT IGNORE INTO message_reads (user_id, channel_id, unread_count)
  SELECT cm.user_id, NEW.channel_id, 1
  FROM chat_channel_members cm
  WHERE cm.channel_id = NEW.channel_id
    AND cm.user_id != NEW.user_id
    AND NOT EXISTS (
      SELECT 1 FROM message_reads mr2
      WHERE mr2.user_id = cm.user_id
      AND mr2.channel_id = NEW.channel_id
    );
END//

-- Trigger to handle channel member addition
CREATE TRIGGER `trg_after_member_insert`
AFTER INSERT ON `chat_channel_members`
FOR EACH ROW
BEGIN
  -- Initialize read status for new member
  INSERT IGNORE INTO message_reads (user_id, channel_id, unread_count, unread_mentions)
  VALUES (NEW.user_id, NEW.channel_id, 0, 0);
END//

DELIMITER ;

-- =====================================================
-- SAMPLE DATA FOR TESTING
-- =====================================================

-- Insert sample tenants if not exists
INSERT IGNORE INTO `tenants` (`id`, `name`, `domain`, `status`) VALUES
(1, 'Acme Corporation', 'acme.nexiosolution.com', 'active'),
(2, 'Tech Innovators', 'tech.nexiosolution.com', 'active');

-- Insert sample users if not exists
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `password`) VALUES
(1, 'John Doe', 'john@acme.com', '$2y$10$YourHashedPasswordHere'),
(2, 'Jane Smith', 'jane@acme.com', '$2y$10$YourHashedPasswordHere'),
(3, 'Bob Wilson', 'bob@acme.com', '$2y$10$YourHashedPasswordHere'),
(4, 'Alice Brown', 'alice@tech.com', '$2y$10$YourHashedPasswordHere'),
(5, 'Charlie Davis', 'charlie@tech.com', '$2y$10$YourHashedPasswordHere');

-- Create sample channels
INSERT INTO `chat_channels` (`tenant_id`, `type`, `name`, `description`, `created_by`) VALUES
(1, 'public', 'General', 'General discussion for all team members', 1),
(1, 'public', 'Random', 'Off-topic conversations and team bonding', 1),
(1, 'private', 'Management', 'Private channel for management discussions', 1),
(1, 'public', 'Development', 'Technical discussions and code reviews', 2),
(1, 'public', 'Marketing', 'Marketing strategies and campaigns', 2),
(2, 'public', 'General', 'Main channel for Tech Innovators', 4),
(2, 'public', 'Projects', 'Project updates and coordination', 4);

-- Add members to channels
INSERT INTO `chat_channel_members` (`channel_id`, `user_id`, `role`) VALUES
-- Acme Corporation channels
(1, 1, 'owner'), (1, 2, 'admin'), (1, 3, 'member'),
(2, 1, 'member'), (2, 2, 'member'), (2, 3, 'member'),
(3, 1, 'owner'), (3, 2, 'admin'),
(4, 1, 'member'), (4, 2, 'owner'), (4, 3, 'member'),
(5, 1, 'member'), (5, 2, 'owner'), (5, 3, 'member'),
-- Tech Innovators channels
(6, 4, 'owner'), (6, 5, 'member'),
(7, 4, 'owner'), (7, 5, 'admin');

-- Create direct message channel
INSERT INTO `chat_channels` (`tenant_id`, `type`, `created_by`) VALUES
(1, 'direct', 1);

SET @dm_channel_id = LAST_INSERT_ID();

INSERT INTO `chat_channel_members` (`channel_id`, `user_id`, `role`) VALUES
(@dm_channel_id, 1, 'member'),
(@dm_channel_id, 2, 'member');

-- Insert sample messages
INSERT INTO `chat_messages` (`channel_id`, `user_id`, `content`, `message_type`) VALUES
(1, 1, 'Welcome to the General channel everyone!', 'text'),
(1, 2, 'Thanks John! Excited to be part of the team.', 'text'),
(1, 3, 'Looking forward to working with everyone!', 'text'),
(1, 1, '@everyone Please check the new project guidelines', 'text'),
(4, 2, 'Just pushed the new feature to the dev branch', 'text'),
(4, 3, 'Great! I''ll review it shortly', 'text'),
(4, 2, 'Thanks @Bob! Let me know if you have any questions', 'text'),
(@dm_channel_id, 1, 'Hey Jane, can we discuss the project timeline?', 'text'),
(@dm_channel_id, 2, 'Sure John, I''m available now', 'text');

-- Add some reactions
INSERT INTO `message_reactions` (`message_id`, `user_id`, `emoji`) VALUES
(1, 2, '👍'),
(1, 3, '🎉'),
(5, 1, '✅'),
(5, 3, '👏');

-- Add mentions
INSERT INTO `message_mentions` (`message_id`, `mentioned_user_id`, `mention_type`) VALUES
(4, 1, 'everyone'),
(4, 2, 'everyone'),
(4, 3, 'everyone'),
(7, 3, 'user');

-- Set up initial read status
INSERT INTO `message_reads` (`user_id`, `channel_id`, `last_read_message_id`, `unread_count`) VALUES
(1, 1, 4, 0),
(2, 1, 4, 0),
(3, 1, 3, 1),
(1, 4, 7, 0),
(2, 4, 7, 0),
(3, 4, 6, 1);

-- Set initial presence
INSERT INTO `chat_presence` (`tenant_id`, `user_id`, `status`, `status_message`) VALUES
(1, 1, 'online', 'Working on the new feature'),
(1, 2, 'busy', 'In a meeting'),
(1, 3, 'away', 'Be right back'),
(2, 4, 'online', NULL),
(2, 5, 'offline', NULL);

-- Pin some important messages
INSERT INTO `chat_pinned_messages` (`channel_id`, `message_id`, `pinned_by`) VALUES
(1, 4, 1),
(4, 5, 2);

-- =====================================================
-- PERFORMANCE STATISTICS
-- =====================================================

-- Create table for chat analytics
CREATE TABLE IF NOT EXISTS `chat_analytics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `total_messages` INT UNSIGNED DEFAULT 0,
  `active_users` INT UNSIGNED DEFAULT 0,
  `active_channels` INT UNSIGNED DEFAULT 0,
  `avg_response_time` INT UNSIGNED DEFAULT NULL COMMENT 'In seconds',
  `peak_hour` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Hour of day with most activity',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_date` (`tenant_id`, `date`),
  INDEX `idx_date` (`date`),

  CONSTRAINT `fk_analytics_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily chat usage analytics';

-- =====================================================
-- GRANT PERMISSIONS (adjust as needed)
-- =====================================================
-- GRANT SELECT, INSERT, UPDATE, DELETE ON nexio_collabora_v2.chat_* TO 'nexio_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE nexio_collabora_v2.sp_* TO 'nexio_user'@'localhost';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- MIGRATION COMPLETED SUCCESSFULLY
-- =====================================================
-- Tables Created: 11
-- Views Created: 2
-- Procedures Created: 2
-- Triggers Created: 2
-- Sample Data: Inserted
-- =====================================================