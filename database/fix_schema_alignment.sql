-- =====================================================
-- SCHEMA ALIGNMENT FIX FOR NEXIOSOLUTION COLLABORA
-- Purpose: Ensure all required tables exist with correct structure
-- Compatible with: MySQL 8.0+ / MariaDB 10.6+
-- Created: 2025-01-21
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- Use the correct database
USE `nexio_collabora_v2`;

-- =====================================================
-- ENSURE CHAT TABLES EXIST (Expected by code)
-- =====================================================

-- Table: chat_channels (main channel container)
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
  INDEX `idx_created_at` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Communication channels for teams and direct messages';

-- Table: chat_channel_members (membership tracking)
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
  INDEX `idx_muted` (`muted_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Channel membership with roles and preferences';

-- Table: chat_messages (actual messages)
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
  FULLTEXT INDEX `idx_content_search` (`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Chat messages with threading and file attachments';

-- =====================================================
-- ENSURE CALENDAR/TASK TABLES EXIST
-- =====================================================

-- Table: calendars (if not exists)
CREATE TABLE IF NOT EXISTS `calendars` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL COMMENT 'Tenant isolation',
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'Calendar owner',
  `name` VARCHAR(255) NOT NULL COMMENT 'Calendar display name',
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#3B82F6' COMMENT 'Hex color for UI display',
  `icon` VARCHAR(50) DEFAULT 'calendar' COMMENT 'Icon identifier',
  `type` ENUM('personal', 'team', 'project', 'resource', 'holiday') NOT NULL DEFAULT 'personal',
  `visibility` ENUM('private', 'tenant', 'public') NOT NULL DEFAULT 'private' COMMENT 'Calendar visibility scope',
  `is_default` BOOLEAN DEFAULT FALSE COMMENT 'User default calendar flag',
  `is_shared` BOOLEAN DEFAULT FALSE,
  `share_settings` JSON DEFAULT NULL COMMENT 'Sharing permissions and rules',
  `timezone` VARCHAR(50) NOT NULL DEFAULT 'UTC' COMMENT 'Calendar default timezone',
  `caldav_uri` VARCHAR(255) DEFAULT NULL COMMENT 'CalDAV unique identifier',
  `sync_token` VARCHAR(255) DEFAULT NULL COMMENT 'For CalDAV synchronization',
  `etag` VARCHAR(255) DEFAULT NULL COMMENT 'CalDAV ETag for change detection',
  `settings` JSON DEFAULT NULL COMMENT 'Calendar-specific settings',
  `sort_order` INT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_calendars_caldav` (`caldav_uri`),
  KEY `idx_calendars_tenant_user` (`tenant_id`, `user_id`),
  KEY `idx_calendars_type_visibility` (`type`, `visibility`),
  KEY `idx_calendars_default` (`user_id`, `is_default`),
  KEY `idx_calendars_deleted` (`deleted_at`),
  KEY `idx_calendars_sync` (`sync_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar containers for event management';

-- Table: events (if not exists)
CREATE TABLE IF NOT EXISTS `events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `calendar_id` BIGINT UNSIGNED NOT NULL,
  `uid` VARCHAR(255) NOT NULL COMMENT 'RFC 4791 unique identifier',
  `parent_event_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'For recurring event instances',
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(500) DEFAULT NULL,
  `location_details` JSON DEFAULT NULL COMMENT 'Structured location data',
  `start_datetime` DATETIME NOT NULL COMMENT 'Event start in UTC',
  `end_datetime` DATETIME NOT NULL COMMENT 'Event end in UTC',
  `all_day` BOOLEAN DEFAULT FALSE,
  `timezone` VARCHAR(50) NOT NULL DEFAULT 'UTC',
  `status` ENUM('tentative', 'confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
  `visibility` ENUM('public', 'private', 'confidential') NOT NULL DEFAULT 'public',
  `transparency` ENUM('opaque', 'transparent') NOT NULL DEFAULT 'opaque' COMMENT 'Busy/free time',
  `priority` TINYINT UNSIGNED DEFAULT 0 COMMENT '0=undefined, 1=highest, 9=lowest',
  `class` ENUM('public', 'private', 'confidential') DEFAULT 'public',
  `is_recurring` BOOLEAN DEFAULT FALSE,
  `recurrence_rule` TEXT DEFAULT NULL COMMENT 'RRULE in RFC 5545 format',
  `recurrence_dates` TEXT DEFAULT NULL COMMENT 'RDATE',
  `exception_dates` TEXT DEFAULT NULL COMMENT 'EXDATE',
  `recurrence_id` DATETIME DEFAULT NULL,
  `sequence` INT UNSIGNED DEFAULT 0,
  `meeting_url` VARCHAR(500) DEFAULT NULL,
  `meeting_provider` ENUM('zoom', 'teams', 'meet', 'webex', 'other') DEFAULT NULL,
  `meeting_details` JSON DEFAULT NULL,
  `reminders` JSON DEFAULT NULL,
  `default_reminders` BOOLEAN DEFAULT TRUE,
  `organizer_id` BIGINT UNSIGNED DEFAULT NULL,
  `organizer_email` VARCHAR(255) DEFAULT NULL,
  `categories` JSON DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT NULL,
  `attachments` JSON DEFAULT NULL,
  `caldav_uri` VARCHAR(255) DEFAULT NULL,
  `etag` VARCHAR(255) DEFAULT NULL,
  `ical_data` TEXT DEFAULT NULL,
  `source` ENUM('web', 'api', 'caldav', 'import', 'sync') DEFAULT 'web',
  `external_id` VARCHAR(255) DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_events_uid` (`uid`),
  UNIQUE KEY `uk_events_caldav` (`caldav_uri`),
  KEY `idx_events_tenant_calendar` (`tenant_id`, `calendar_id`),
  KEY `idx_events_datetime` (`start_datetime`, `end_datetime`),
  KEY `idx_events_recurring` (`is_recurring`, `parent_event_id`),
  KEY `idx_events_organizer` (`organizer_id`),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_deleted` (`deleted_at`),
  FULLTEXT KEY `ft_events_search` (`title`, `description`, `location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar events with recurrence support';

-- Table: event_participants (if not exists)
CREATE TABLE IF NOT EXISTS `event_participants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `name` VARCHAR(255) DEFAULT NULL,
  `type` ENUM('required', 'optional', 'resource') NOT NULL DEFAULT 'required',
  `role` ENUM('chair', 'attendee', 'opt-participant', 'non-participant') DEFAULT 'attendee',
  `status` ENUM('needs-action', 'accepted', 'declined', 'tentative', 'delegated') NOT NULL DEFAULT 'needs-action',
  `response_datetime` TIMESTAMP NULL DEFAULT NULL,
  `comment` TEXT DEFAULT NULL,
  `delegated_to` VARCHAR(255) DEFAULT NULL,
  `delegated_from` VARCHAR(255) DEFAULT NULL,
  `is_organizer` BOOLEAN DEFAULT FALSE,
  `can_edit` BOOLEAN DEFAULT FALSE,
  `can_invite_others` BOOLEAN DEFAULT FALSE,
  `can_see_others` BOOLEAN DEFAULT TRUE,
  `notification_sent` BOOLEAN DEFAULT FALSE,
  `notification_sent_at` TIMESTAMP NULL DEFAULT NULL,
  `reminder_sent` BOOLEAN DEFAULT FALSE,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_participants_event_email` (`event_id`, `email`),
  KEY `idx_participants_tenant_event` (`tenant_id`, `event_id`),
  KEY `idx_participants_user` (`user_id`),
  KEY `idx_participants_status` (`status`),
  KEY `idx_participants_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Event participants and their RSVP status';

-- Table: task_lists (if not exists)
CREATE TABLE IF NOT EXISTS `task_lists` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED DEFAULT NULL,
  `owner_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#6B7280',
  `icon` VARCHAR(50) DEFAULT 'clipboard-list',
  `type` ENUM('personal', 'team', 'project', 'sprint', 'backlog') NOT NULL DEFAULT 'personal',
  `visibility` ENUM('private', 'team', 'tenant') NOT NULL DEFAULT 'private',
  `is_default` BOOLEAN DEFAULT FALSE,
  `is_archived` BOOLEAN DEFAULT FALSE,
  `settings` JSON DEFAULT NULL,
  `workflow_states` JSON DEFAULT NULL,
  `automation_rules` JSON DEFAULT NULL,
  `sort_order` INT UNSIGNED DEFAULT 0,
  `task_count` INT UNSIGNED DEFAULT 0,
  `completed_task_count` INT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_lists_tenant_owner` (`tenant_id`, `owner_id`),
  KEY `idx_task_lists_project` (`project_id`),
  KEY `idx_task_lists_type` (`type`),
  KEY `idx_task_lists_archived` (`is_archived`),
  KEY `idx_task_lists_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task boards and lists';

-- Table: tasks (if not exists)
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `task_list_id` BIGINT UNSIGNED NOT NULL,
  `parent_task_id` BIGINT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('todo', 'in_progress', 'review', 'done', 'cancelled', 'on_hold') NOT NULL DEFAULT 'todo',
  `priority` ENUM('critical', 'high', 'medium', 'low', 'none') NOT NULL DEFAULT 'medium',
  `type` ENUM('task', 'bug', 'feature', 'improvement', 'epic', 'story') DEFAULT 'task',
  `start_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `due_time` TIME DEFAULT NULL,
  `estimated_hours` DECIMAL(6,2) DEFAULT NULL,
  `actual_hours` DECIMAL(6,2) DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `assignee_id` BIGINT UNSIGNED DEFAULT NULL,
  `reporter_id` BIGINT UNSIGNED DEFAULT NULL,
  `progress_percent` TINYINT UNSIGNED DEFAULT 0,
  `checklist_items` JSON DEFAULT NULL,
  `checklist_completed` INT UNSIGNED DEFAULT 0,
  `checklist_total` INT UNSIGNED DEFAULT 0,
  `tags` JSON DEFAULT NULL,
  `categories` JSON DEFAULT NULL,
  `epic_id` BIGINT UNSIGNED DEFAULT NULL,
  `sprint_id` BIGINT UNSIGNED DEFAULT NULL,
  `milestone_id` BIGINT UNSIGNED DEFAULT NULL,
  `blocked_by` JSON DEFAULT NULL,
  `blocks` JSON DEFAULT NULL,
  `related_tasks` JSON DEFAULT NULL,
  `external_id` VARCHAR(255) DEFAULT NULL,
  `external_url` VARCHAR(500) DEFAULT NULL,
  `attachments` JSON DEFAULT NULL,
  `is_recurring` BOOLEAN DEFAULT FALSE,
  `recurrence_pattern` JSON DEFAULT NULL,
  `next_occurrence` DATE DEFAULT NULL,
  `is_template` BOOLEAN DEFAULT FALSE,
  `template_id` BIGINT UNSIGNED DEFAULT NULL,
  `position` INT UNSIGNED DEFAULT 0,
  `sort_order` DECIMAL(20,10) DEFAULT 0,
  `metadata` JSON DEFAULT NULL,
  `custom_fields` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_tenant_list` (`tenant_id`, `task_list_id`),
  KEY `idx_tasks_status_priority` (`status`, `priority`),
  KEY `idx_tasks_assignee` (`assignee_id`),
  KEY `idx_tasks_due_date` (`due_date`),
  KEY `idx_tasks_parent` (`parent_task_id`),
  KEY `idx_tasks_completed` (`completed_at`),
  KEY `idx_tasks_deleted` (`deleted_at`),
  KEY `idx_tasks_position` (`task_list_id`, `position`),
  FULLTEXT KEY `ft_tasks_search` (`title`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task items with comprehensive tracking';

-- Table: task_assignments (if not exists)
CREATE TABLE IF NOT EXISTS `task_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('owner', 'assignee', 'reviewer', 'watcher', 'approver') NOT NULL DEFAULT 'assignee',
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` BIGINT UNSIGNED DEFAULT NULL,
  `started_at` TIMESTAMP NULL DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `time_spent_minutes` INT UNSIGNED DEFAULT 0,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_assignments` (`task_id`, `user_id`, `role`),
  KEY `idx_assignments_tenant_task` (`tenant_id`, `task_id`),
  KEY `idx_assignments_user` (`user_id`),
  KEY `idx_assignments_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task assignments to multiple users';

-- Table: calendar_shares (if not exists)
CREATE TABLE IF NOT EXISTS `calendar_shares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `calendar_id` BIGINT UNSIGNED NOT NULL,
  `shared_with_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `shared_with_email` VARCHAR(255) DEFAULT NULL,
  `permission_level` ENUM('read', 'write', 'admin') NOT NULL DEFAULT 'read',
  `can_share` BOOLEAN DEFAULT FALSE,
  `can_edit_events` BOOLEAN DEFAULT FALSE,
  `can_delete_events` BOOLEAN DEFAULT FALSE,
  `share_token` VARCHAR(64) DEFAULT NULL,
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `accepted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_calendar_shares` (`calendar_id`, `shared_with_user_id`),
  UNIQUE KEY `uk_calendar_shares_token` (`share_token`),
  KEY `idx_shares_tenant_calendar` (`tenant_id`, `calendar_id`),
  KEY `idx_shares_user` (`shared_with_user_id`),
  KEY `idx_shares_permission` (`permission_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar sharing permissions';

-- =====================================================
-- ADDITIONAL CHAT SUPPORT TABLES
-- =====================================================

-- Table: message_reactions (if not exists)
CREATE TABLE IF NOT EXISTS `message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `emoji` VARCHAR(10) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_message_user_emoji` (`message_id`, `user_id`, `emoji`),
  INDEX `idx_message_reactions` (`message_id`),
  INDEX `idx_user_reactions` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Emoji reactions on messages';

-- Table: message_mentions (if not exists)
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
  INDEX `idx_message_mentions` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track user mentions for notifications';

-- Table: message_reads (if not exists)
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
  INDEX `idx_unread` (`user_id`, `unread_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Track message read status per user per channel';

-- Table: chat_presence (if not exists)
CREATE TABLE IF NOT EXISTS `chat_presence` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('online', 'away', 'offline', 'busy', 'do_not_disturb') NOT NULL DEFAULT 'offline',
  `status_message` VARCHAR(255) DEFAULT NULL,
  `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_typing_at` TIMESTAMP NULL DEFAULT NULL,
  `current_channel_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_info` JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_tenant_user` (`tenant_id`, `user_id`),
  INDEX `idx_tenant_status` (`tenant_id`, `status`),
  INDEX `idx_last_activity` (`last_activity`),
  INDEX `idx_current_channel` (`current_channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Real-time user presence and activity status';

-- Table: chat_typing_indicators (if not exists)
CREATE TABLE IF NOT EXISTS `chat_typing_indicators` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `started_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP NOT NULL DEFAULT (CURRENT_TIMESTAMP + INTERVAL 10 SECOND),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_channel_user_typing` (`channel_id`, `user_id`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Real-time typing indicators';

-- Table: chat_pinned_messages (if not exists)
CREATE TABLE IF NOT EXISTS `chat_pinned_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `pinned_by` BIGINT UNSIGNED NOT NULL,
  `pinned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_channel_message` (`channel_id`, `message_id`),
  INDEX `idx_channel_pinned` (`channel_id`, `pinned_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pinned important messages per channel';

-- =====================================================
-- ADD FOREIGN KEY CONSTRAINTS (Only if tables exist)
-- =====================================================

-- Add foreign keys for chat_channels if not exists
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'nexio_collabora_v2'
                  AND TABLE_NAME = 'chat_channels'
                  AND CONSTRAINT_NAME = 'fk_chat_channels_tenant');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE chat_channels ADD CONSTRAINT fk_chat_channels_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
    'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'nexio_collabora_v2'
                  AND TABLE_NAME = 'chat_channels'
                  AND CONSTRAINT_NAME = 'fk_chat_channels_creator');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE chat_channels ADD CONSTRAINT fk_chat_channels_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT',
    'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign keys for chat_channel_members if not exists
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'nexio_collabora_v2'
                  AND TABLE_NAME = 'chat_channel_members'
                  AND CONSTRAINT_NAME = 'fk_channel_members_channel');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE chat_channel_members ADD CONSTRAINT fk_channel_members_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE',
    'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'nexio_collabora_v2'
                  AND TABLE_NAME = 'chat_channel_members'
                  AND CONSTRAINT_NAME = 'fk_channel_members_user');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE chat_channel_members ADD CONSTRAINT fk_channel_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign keys for chat_messages if not exists
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'nexio_collabora_v2'
                  AND TABLE_NAME = 'chat_messages'
                  AND CONSTRAINT_NAME = 'fk_messages_channel');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE chat_messages ADD CONSTRAINT fk_messages_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE',
    'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = 'nexio_collabora_v2'
                  AND TABLE_NAME = 'chat_messages'
                  AND CONSTRAINT_NAME = 'fk_messages_user');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE chat_messages ADD CONSTRAINT fk_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT',
    'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- CREATE VIEWS FOR REPORTING
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
-- STORED PROCEDURES FOR CHAT OPERATIONS
-- =====================================================

DELIMITER //

-- Procedure to create a direct message channel
DROP PROCEDURE IF EXISTS `sp_create_direct_channel`//
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
DROP PROCEDURE IF EXISTS `sp_mark_messages_read`//
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

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- MIGRATION SUMMARY
-- =====================================================
-- This script ensures all required tables exist:
-- 1. Chat tables: chat_channels, chat_channel_members, chat_messages, etc.
-- 2. Calendar tables: calendars, events, event_participants
-- 3. Task tables: task_lists, tasks, task_assignments
-- 4. Support tables: message_reactions, message_mentions, etc.
--
-- All tables use CREATE TABLE IF NOT EXISTS to be idempotent
-- Foreign keys are added conditionally to avoid errors
-- =====================================================