-- =====================================================
-- Complete Database Migration for Nexio Collabora
-- MariaDB 10.6+ / MySQL 8.0+ Compatible
-- Created: 2025-09-20
-- Version: 1.0.0
--
-- This script adds ALL missing tables with proper multi-tenant structure
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

USE nexio_db;

-- =====================================================
-- SECTION 1: AUTHENTICATION & COMPANY TABLES
-- =====================================================

-- Companies table
CREATE TABLE IF NOT EXISTS `companies` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company users association
CREATE TABLE IF NOT EXISTS `company_users` (
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `role` ENUM('owner', 'admin', 'member', 'viewer') DEFAULT 'member',
    `department` VARCHAR(100),
    `position` VARCHAR(100),
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`company_id`, `user_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS `password_resets` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`email`),
    INDEX `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Personal access tokens (for API access)
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 2: RBAC TABLES (Role-Based Access Control)
-- =====================================================

-- Model has roles (polymorphic association)
CREATE TABLE IF NOT EXISTS `model_has_roles` (
    `role_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`role_id`, `model_type`, `model_id`),
    INDEX `idx_model` (`model_type`, `model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Model has permissions
CREATE TABLE IF NOT EXISTS `model_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `model_type` VARCHAR(255) NOT NULL,
    `model_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `model_type`, `model_id`),
    INDEX `idx_model` (`model_type`, `model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role has permissions
CREATE TABLE IF NOT EXISTS `role_has_permissions` (
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`permission_id`, `role_id`),
    INDEX `idx_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 3: CALENDAR TABLES
-- =====================================================

-- Calendar users (sharing)
CREATE TABLE IF NOT EXISTS `calendar_users` (
    `calendar_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `permission` ENUM('owner', 'write', 'read') DEFAULT 'read',
    `color_override` VARCHAR(7),
    `is_hidden` BOOLEAN DEFAULT FALSE,
    `notification_enabled` BOOLEAN DEFAULT TRUE,
    `shared_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`calendar_id`, `user_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event attendees
CREATE TABLE IF NOT EXISTS `event_attendees` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 4: TASK MANAGEMENT TABLES
-- =====================================================

-- Task lists (boards/projects)
CREATE TABLE IF NOT EXISTS `task_lists` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task occurrences (for recurring tasks)
CREATE TABLE IF NOT EXISTS `task_occurrences` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task assignees
CREATE TABLE IF NOT EXISTS `task_assignees` (
    `task_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `assigned_by` INT UNSIGNED,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `accepted_at` TIMESTAMP NULL,
    PRIMARY KEY (`task_id`, `user_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 5: CHAT TABLES
-- =====================================================

-- Chat channels
CREATE TABLE IF NOT EXISTS `chat_channels` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Channel members
CREATE TABLE IF NOT EXISTS `chat_channel_members` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Message read status
CREATE TABLE IF NOT EXISTS `chat_message_reads` (
    `message_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `read_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    INDEX `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chat attachments
CREATE TABLE IF NOT EXISTS `chat_attachments` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 6: FILE MANAGEMENT TABLES
-- =====================================================

-- File versions
CREATE TABLE IF NOT EXISTS `file_versions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- File shares
CREATE TABLE IF NOT EXISTS `file_shares` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 7: NOTIFICATION & WORKFLOW TABLES
-- =====================================================

-- Push subscriptions (for web push notifications)
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approvals workflow
CREATE TABLE IF NOT EXISTS `approvals` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Approval steps
CREATE TABLE IF NOT EXISTS `approval_steps` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTION 8: CREATE MIGRATIONS TABLE
-- =====================================================

CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert migration record
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('complete_migration', 1);

-- =====================================================
-- SECTION 9: ADD MISSING TENANT_ID TO PERMISSIONS
-- =====================================================

-- Add tenant_id to permissions table if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'permissions';
SET @columnname = 'tenant_id';
SET @preparedStatement = (
    SELECT IF(
        (
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @dbname
            AND TABLE_NAME = @tablename
            AND COLUMN_NAME = @columnname
        ) > 0,
        "SELECT 'Column already exists';",
        CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` INT UNSIGNED NULL AFTER `id`, ADD INDEX `idx_tenant` (`tenant_id`);"  )
    )
);

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- SECTION 10: VERIFY AND REPORT
-- =====================================================

-- Enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Report created tables
SELECT 'Migration completed successfully!' as Status;
SELECT COUNT(*) as TotalTables FROM information_schema.tables WHERE table_schema = DATABASE();