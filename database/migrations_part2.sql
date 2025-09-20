-- =====================================================
-- Multi-Tenant Calendar & Task Management Schema
-- Part 2 of Nexio Solution Database Migration
-- MariaDB 10.6+ / MySQL 8.0+ Compatible
-- Created: 2025-01-19
-- Version: 2.1.0
--
-- Module: Calendar/Events & Task Management
-- Author: Database Architecture Team
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- Use the existing database
USE `collabora_files`;

-- =====================================================
-- Table: calendars
-- Calendar containers per tenant/user
-- Supports shared calendars and different calendar types
-- =====================================================
DROP TABLE IF EXISTS `calendars`;
CREATE TABLE `calendars` (
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
  KEY `idx_calendars_sync` (`sync_token`),
  CONSTRAINT `fk_calendars_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calendars_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar containers for event management';

-- =====================================================
-- Table: events
-- Calendar events with full recurrence support
-- Implements RFC 5545 (iCalendar) standards
-- =====================================================
DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `calendar_id` BIGINT UNSIGNED NOT NULL,
  `uid` VARCHAR(255) NOT NULL COMMENT 'RFC 4791 unique identifier',
  `parent_event_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'For recurring event instances',
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `location` VARCHAR(500) DEFAULT NULL,
  `location_details` JSON DEFAULT NULL COMMENT 'Structured location data (address, coordinates, room)',
  `start_datetime` DATETIME NOT NULL COMMENT 'Event start in UTC',
  `end_datetime` DATETIME NOT NULL COMMENT 'Event end in UTC',
  `all_day` BOOLEAN DEFAULT FALSE,
  `timezone` VARCHAR(50) NOT NULL DEFAULT 'UTC',
  `status` ENUM('tentative', 'confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
  `visibility` ENUM('public', 'private', 'confidential') NOT NULL DEFAULT 'public',
  `transparency` ENUM('opaque', 'transparent') NOT NULL DEFAULT 'opaque' COMMENT 'Busy/free time',
  `priority` TINYINT UNSIGNED DEFAULT 0 COMMENT '0=undefined, 1=highest, 9=lowest',
  `class` ENUM('public', 'private', 'confidential') DEFAULT 'public' COMMENT 'Access classification',

  -- Recurrence fields (RFC 5545 compliant)
  `is_recurring` BOOLEAN DEFAULT FALSE,
  `recurrence_rule` TEXT DEFAULT NULL COMMENT 'RRULE in RFC 5545 format',
  `recurrence_dates` TEXT DEFAULT NULL COMMENT 'RDATE: Additional recurrence dates',
  `exception_dates` TEXT DEFAULT NULL COMMENT 'EXDATE: Excluded dates from recurrence',
  `recurrence_id` DATETIME DEFAULT NULL COMMENT 'For modified recurring instances',
  `sequence` INT UNSIGNED DEFAULT 0 COMMENT 'Update sequence number',

  -- Meeting/Conference fields
  `meeting_url` VARCHAR(500) DEFAULT NULL,
  `meeting_provider` ENUM('zoom', 'teams', 'meet', 'webex', 'other') DEFAULT NULL,
  `meeting_details` JSON DEFAULT NULL COMMENT 'Meeting access info',

  -- Reminder/Notification settings
  `reminders` JSON DEFAULT NULL COMMENT 'Array of reminder configurations',
  `default_reminders` BOOLEAN DEFAULT TRUE COMMENT 'Use calendar default reminders',

  -- Organizational fields
  `organizer_id` BIGINT UNSIGNED DEFAULT NULL,
  `organizer_email` VARCHAR(255) DEFAULT NULL,
  `categories` JSON DEFAULT NULL COMMENT 'Event categories/tags',
  `color` VARCHAR(7) DEFAULT NULL COMMENT 'Override calendar color',
  `attachments` JSON DEFAULT NULL COMMENT 'File attachment references',

  -- CalDAV synchronization
  `caldav_uri` VARCHAR(255) DEFAULT NULL,
  `etag` VARCHAR(255) DEFAULT NULL,
  `ical_data` TEXT DEFAULT NULL COMMENT 'Original iCalendar data',

  -- Metadata
  `source` ENUM('web', 'api', 'caldav', 'import', 'sync') DEFAULT 'web',
  `external_id` VARCHAR(255) DEFAULT NULL COMMENT 'ID from external system',
  `metadata` JSON DEFAULT NULL COMMENT 'Additional event metadata',

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
  KEY `idx_events_recurrence_id` (`recurrence_id`),
  KEY `idx_events_etag` (`etag`),
  FULLTEXT KEY `ft_events_search` (`title`, `description`, `location`),
  CONSTRAINT `fk_events_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_events_calendar` FOREIGN KEY (`calendar_id`) REFERENCES `calendars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_events_parent` FOREIGN KEY (`parent_event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_events_organizer` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar events with recurrence support';

-- =====================================================
-- Table: event_participants
-- Event attendees and their responses
-- =====================================================
DROP TABLE IF EXISTS `event_participants`;
CREATE TABLE `event_participants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Internal user reference',
  `email` VARCHAR(255) NOT NULL COMMENT 'Participant email (internal or external)',
  `name` VARCHAR(255) DEFAULT NULL,
  `type` ENUM('required', 'optional', 'resource') NOT NULL DEFAULT 'required',
  `role` ENUM('chair', 'attendee', 'opt-participant', 'non-participant') DEFAULT 'attendee',
  `status` ENUM('needs-action', 'accepted', 'declined', 'tentative', 'delegated') NOT NULL DEFAULT 'needs-action',
  `response_datetime` TIMESTAMP NULL DEFAULT NULL,
  `comment` TEXT DEFAULT NULL COMMENT 'Response comment from participant',
  `delegated_to` VARCHAR(255) DEFAULT NULL COMMENT 'Email of delegate',
  `delegated_from` VARCHAR(255) DEFAULT NULL COMMENT 'Original invitee if delegated',
  `is_organizer` BOOLEAN DEFAULT FALSE,
  `can_edit` BOOLEAN DEFAULT FALSE COMMENT 'Can modify event',
  `can_invite_others` BOOLEAN DEFAULT FALSE,
  `can_see_others` BOOLEAN DEFAULT TRUE COMMENT 'Can see other participants',
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
  KEY `idx_participants_email` (`email`),
  CONSTRAINT `fk_participants_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_participants_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_participants_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Event participants and their RSVP status';

-- =====================================================
-- Table: event_reminders
-- Granular reminder configurations for events
-- =====================================================
DROP TABLE IF EXISTS `event_reminders`;
CREATE TABLE `event_reminders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'User-specific reminder',
  `type` ENUM('popup', 'email', 'push', 'sms') NOT NULL DEFAULT 'popup',
  `minutes_before` INT UNSIGNED NOT NULL COMMENT 'Minutes before event to trigger',
  `is_sent` BOOLEAN DEFAULT FALSE,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `scheduled_for` TIMESTAMP NULL DEFAULT NULL COMMENT 'Calculated send time',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reminders_event` (`event_id`),
  KEY `idx_reminders_scheduled` (`scheduled_for`, `is_sent`),
  KEY `idx_reminders_user` (`user_id`),
  CONSTRAINT `fk_reminders_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminders_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual event reminders';

-- =====================================================
-- Table: task_lists
-- Task boards/lists for organizing tasks
-- =====================================================
DROP TABLE IF EXISTS `task_lists`;
CREATE TABLE `task_lists` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `project_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Optional project association',
  `owner_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(7) DEFAULT '#6B7280',
  `icon` VARCHAR(50) DEFAULT 'clipboard-list',
  `type` ENUM('personal', 'team', 'project', 'sprint', 'backlog') NOT NULL DEFAULT 'personal',
  `visibility` ENUM('private', 'team', 'tenant') NOT NULL DEFAULT 'private',
  `is_default` BOOLEAN DEFAULT FALSE,
  `is_archived` BOOLEAN DEFAULT FALSE,
  `settings` JSON DEFAULT NULL COMMENT 'List-specific settings and preferences',
  `workflow_states` JSON DEFAULT NULL COMMENT 'Custom workflow states for this list',
  `automation_rules` JSON DEFAULT NULL COMMENT 'Automation configurations',
  `sort_order` INT UNSIGNED DEFAULT 0,
  `task_count` INT UNSIGNED DEFAULT 0 COMMENT 'Cached task count',
  `completed_task_count` INT UNSIGNED DEFAULT 0 COMMENT 'Cached completed count',
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
  KEY `idx_task_lists_deleted` (`deleted_at`),
  CONSTRAINT `fk_task_lists_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_lists_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task boards and lists';

-- =====================================================
-- Table: tasks
-- Individual tasks with comprehensive tracking
-- =====================================================
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `task_list_id` BIGINT UNSIGNED NOT NULL,
  `parent_task_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'For subtasks',
  `title` VARCHAR(500) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('todo', 'in_progress', 'review', 'done', 'cancelled', 'on_hold') NOT NULL DEFAULT 'todo',
  `priority` ENUM('critical', 'high', 'medium', 'low', 'none') NOT NULL DEFAULT 'medium',
  `type` ENUM('task', 'bug', 'feature', 'improvement', 'epic', 'story') DEFAULT 'task',

  -- Scheduling fields
  `start_date` DATE DEFAULT NULL,
  `due_date` DATE DEFAULT NULL,
  `due_time` TIME DEFAULT NULL COMMENT 'Optional specific time',
  `estimated_hours` DECIMAL(6,2) DEFAULT NULL,
  `actual_hours` DECIMAL(6,2) DEFAULT NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,

  -- Assignment fields
  `assignee_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Primary assignee',
  `reporter_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Task creator/reporter',

  -- Progress tracking
  `progress_percent` TINYINT UNSIGNED DEFAULT 0 COMMENT '0-100 completion percentage',
  `checklist_items` JSON DEFAULT NULL COMMENT 'Subtask checklist items',
  `checklist_completed` INT UNSIGNED DEFAULT 0,
  `checklist_total` INT UNSIGNED DEFAULT 0,

  -- Categorization
  `tags` JSON DEFAULT NULL COMMENT 'Task tags/labels',
  `categories` JSON DEFAULT NULL,
  `epic_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Epic task reference',
  `sprint_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Sprint association',
  `milestone_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Milestone reference',

  -- Dependencies
  `blocked_by` JSON DEFAULT NULL COMMENT 'Array of blocking task IDs',
  `blocks` JSON DEFAULT NULL COMMENT 'Array of blocked task IDs',
  `related_tasks` JSON DEFAULT NULL COMMENT 'Related task references',

  -- External references
  `external_id` VARCHAR(255) DEFAULT NULL,
  `external_url` VARCHAR(500) DEFAULT NULL,
  `attachments` JSON DEFAULT NULL COMMENT 'File attachment references',

  -- Tracking fields
  `is_recurring` BOOLEAN DEFAULT FALSE,
  `recurrence_pattern` JSON DEFAULT NULL COMMENT 'Recurrence configuration',
  `next_occurrence` DATE DEFAULT NULL,
  `is_template` BOOLEAN DEFAULT FALSE COMMENT 'Task template flag',
  `template_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Source template reference',

  -- Position and ordering
  `position` INT UNSIGNED DEFAULT 0 COMMENT 'Position in list',
  `sort_order` DECIMAL(20,10) DEFAULT 0 COMMENT 'Precise ordering',

  -- Metadata
  `metadata` JSON DEFAULT NULL,
  `custom_fields` JSON DEFAULT NULL COMMENT 'Tenant-specific custom fields',

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
  KEY `idx_tasks_epic` (`epic_id`),
  KEY `idx_tasks_completed` (`completed_at`),
  KEY `idx_tasks_deleted` (`deleted_at`),
  KEY `idx_tasks_position` (`task_list_id`, `position`),
  FULLTEXT KEY `ft_tasks_search` (`title`, `description`),
  CONSTRAINT `fk_tasks_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_list` FOREIGN KEY (`task_list_id`) REFERENCES `task_lists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_epic` FOREIGN KEY (`epic_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task items with comprehensive tracking';

-- =====================================================
-- Table: task_assignments
-- Multiple assignees per task
-- =====================================================
DROP TABLE IF EXISTS `task_assignments`;
CREATE TABLE `task_assignments` (
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
  KEY `idx_assignments_role` (`role`),
  CONSTRAINT `fk_assignments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task assignments to multiple users';

-- =====================================================
-- Table: task_comments
-- Comments and activity on tasks
-- =====================================================
DROP TABLE IF EXISTS `task_comments`;
CREATE TABLE `task_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `parent_comment_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'For threaded comments',
  `type` ENUM('comment', 'status_change', 'assignment', 'attachment', 'system') NOT NULL DEFAULT 'comment',
  `content` TEXT NOT NULL,
  `is_internal` BOOLEAN DEFAULT FALSE COMMENT 'Internal note flag',
  `mentions` JSON DEFAULT NULL COMMENT 'Mentioned user IDs',
  `attachments` JSON DEFAULT NULL COMMENT 'Attached file references',
  `reactions` JSON DEFAULT NULL COMMENT 'Emoji reactions with user counts',
  `metadata` JSON DEFAULT NULL COMMENT 'Additional activity data',
  `is_edited` BOOLEAN DEFAULT FALSE,
  `edited_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comments_tenant_task` (`tenant_id`, `task_id`),
  KEY `idx_comments_user` (`user_id`),
  KEY `idx_comments_parent` (`parent_comment_id`),
  KEY `idx_comments_type` (`type`),
  KEY `idx_comments_created` (`created_at`),
  KEY `idx_comments_deleted` (`deleted_at`),
  FULLTEXT KEY `ft_comments_content` (`content`),
  CONSTRAINT `fk_comments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_comment_id`) REFERENCES `task_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task comments and activity log';

-- =====================================================
-- Table: task_time_logs
-- Time tracking for tasks
-- =====================================================
DROP TABLE IF EXISTS `task_time_logs`;
CREATE TABLE `task_time_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `started_at` TIMESTAMP NOT NULL,
  `ended_at` TIMESTAMP NULL DEFAULT NULL,
  `duration_minutes` INT UNSIGNED DEFAULT NULL COMMENT 'Calculated duration',
  `description` TEXT DEFAULT NULL,
  `is_billable` BOOLEAN DEFAULT FALSE,
  `hourly_rate` DECIMAL(10,2) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_time_logs_tenant_task` (`tenant_id`, `task_id`),
  KEY `idx_time_logs_user_date` (`user_id`, `started_at`),
  KEY `idx_time_logs_billable` (`is_billable`),
  CONSTRAINT `fk_time_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_time_logs_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_time_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Time tracking entries for tasks';

-- =====================================================
-- Table: calendar_shares
-- Sharing configuration for calendars
-- =====================================================
DROP TABLE IF EXISTS `calendar_shares`;
CREATE TABLE `calendar_shares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `calendar_id` BIGINT UNSIGNED NOT NULL,
  `shared_with_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `shared_with_email` VARCHAR(255) DEFAULT NULL COMMENT 'For external shares',
  `permission_level` ENUM('read', 'write', 'admin') NOT NULL DEFAULT 'read',
  `can_share` BOOLEAN DEFAULT FALSE,
  `can_edit_events` BOOLEAN DEFAULT FALSE,
  `can_delete_events` BOOLEAN DEFAULT FALSE,
  `share_token` VARCHAR(64) DEFAULT NULL COMMENT 'For public/external access',
  `expires_at` TIMESTAMP NULL DEFAULT NULL,
  `accepted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_calendar_shares` (`calendar_id`, `shared_with_user_id`),
  UNIQUE KEY `uk_calendar_shares_token` (`share_token`),
  KEY `idx_shares_tenant_calendar` (`tenant_id`, `calendar_id`),
  KEY `idx_shares_user` (`shared_with_user_id`),
  KEY `idx_shares_permission` (`permission_level`),
  CONSTRAINT `fk_calendar_shares_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calendar_shares_calendar` FOREIGN KEY (`calendar_id`) REFERENCES `calendars` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calendar_shares_user` FOREIGN KEY (`shared_with_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calendar_shares_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar sharing permissions';

-- =====================================================
-- Table: event_attachments
-- File attachments for events
-- =====================================================
DROP TABLE IF EXISTS `event_attachments`;
CREATE TABLE `event_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `attached_by` BIGINT UNSIGNED NOT NULL,
  `attached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_event_attachments` (`event_id`, `file_id`),
  KEY `idx_attachments_tenant_event` (`tenant_id`, `event_id`),
  CONSTRAINT `fk_event_attachments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_attachments_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_attachments_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_event_attachments_user` FOREIGN KEY (`attached_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Event file attachments';

-- =====================================================
-- Table: task_attachments
-- File attachments for tasks
-- =====================================================
DROP TABLE IF EXISTS `task_attachments`;
CREATE TABLE `task_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `file_id` BIGINT UNSIGNED NOT NULL,
  `attached_by` BIGINT UNSIGNED NOT NULL,
  `attached_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_attachments` (`task_id`, `file_id`),
  KEY `idx_task_attachments_tenant_task` (`tenant_id`, `task_id`),
  CONSTRAINT `fk_task_attachments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_attachments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_attachments_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_attachments_user` FOREIGN KEY (`attached_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Task file attachments';

-- =====================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =====================================================

-- Composite indexes for common query patterns
CREATE INDEX idx_events_tenant_date_range ON events(tenant_id, start_datetime, end_datetime);
CREATE INDEX idx_events_calendar_date_range ON events(calendar_id, start_datetime, end_datetime);
CREATE INDEX idx_tasks_tenant_status_due ON tasks(tenant_id, status, due_date);
CREATE INDEX idx_tasks_assignee_status_priority ON tasks(assignee_id, status, priority);

-- =====================================================
-- SAMPLE SEED DATA
-- =====================================================

-- Insert sample calendars for existing users/tenants
INSERT INTO `calendars` (`tenant_id`, `user_id`, `name`, `description`, `color`, `type`, `visibility`, `is_default`, `timezone`)
VALUES
(1, 1, 'Personal Calendar', 'My personal calendar', '#3B82F6', 'personal', 'private', TRUE, 'America/New_York'),
(1, 1, 'Team Meetings', 'Shared team calendar', '#10B981', 'team', 'tenant', FALSE, 'America/New_York'),
(1, 1, 'Project Deadlines', 'Important project milestones', '#EF4444', 'project', 'tenant', FALSE, 'America/New_York');

-- Insert sample task lists
INSERT INTO `task_lists` (`tenant_id`, `owner_id`, `name`, `description`, `color`, `type`, `visibility`, `is_default`)
VALUES
(1, 1, 'My Tasks', 'Personal task list', '#6B7280', 'personal', 'private', TRUE),
(1, 1, 'Project Alpha', 'Tasks for Project Alpha', '#8B5CF6', 'project', 'team', FALSE),
(1, 1, 'Sprint 2025-Q1', 'Current sprint tasks', '#F59E0B', 'sprint', 'team', FALSE);

-- Insert sample events with recurrence
INSERT INTO `events` (`tenant_id`, `calendar_id`, `uid`, `title`, `description`, `start_datetime`, `end_datetime`, `timezone`, `is_recurring`, `recurrence_rule`, `organizer_id`)
VALUES
(1, 1, 'event-001-2025', 'Weekly Team Standup', 'Regular team sync meeting', '2025-01-20 09:00:00', '2025-01-20 09:30:00', 'America/New_York', TRUE, 'FREQ=WEEKLY;BYDAY=MO,WE,FR;UNTIL=20251231T000000Z', 1),
(1, 2, 'event-002-2025', 'Q1 Planning Meeting', 'Quarterly planning session', '2025-01-22 14:00:00', '2025-01-22 16:00:00', 'America/New_York', FALSE, NULL, 1),
(1, 1, 'event-003-2025', 'Monthly All Hands', 'Company-wide update meeting', '2025-02-01 15:00:00', '2025-02-01 16:00:00', 'America/New_York', TRUE, 'FREQ=MONTHLY;BYMONTHDAY=1;COUNT=12', 1);

-- Insert sample tasks with various statuses
INSERT INTO `tasks` (`tenant_id`, `task_list_id`, `title`, `description`, `status`, `priority`, `type`, `due_date`, `assignee_id`, `reporter_id`)
VALUES
(1, 1, 'Complete database migration', 'Implement calendar and task tables', 'in_progress', 'high', 'task', '2025-01-25', 1, 1),
(1, 2, 'Design calendar UI mockups', 'Create wireframes for calendar views', 'todo', 'medium', 'task', '2025-01-28', 1, 1),
(1, 2, 'Implement CalDAV sync', 'Add CalDAV protocol support', 'todo', 'high', 'feature', '2025-02-15', NULL, 1),
(1, 3, 'Fix timezone conversion bug', 'Events showing wrong time in different zones', 'todo', 'critical', 'bug', '2025-01-21', NULL, 1),
(1, 1, 'Review task management API', 'Code review for task endpoints', 'review', 'medium', 'task', '2025-01-23', 1, 1);

-- Insert sample event participants
INSERT INTO `event_participants` (`tenant_id`, `event_id`, `user_id`, `email`, `name`, `type`, `status`, `is_organizer`)
VALUES
(1, 1, 1, 'asamodeo@fortibyte.it', 'Admin User', 'required', 'accepted', TRUE),
(1, 2, 1, 'asamodeo@fortibyte.it', 'Admin User', 'required', 'accepted', TRUE);

-- Insert sample task comments
INSERT INTO `task_comments` (`tenant_id`, `task_id`, `user_id`, `type`, `content`)
VALUES
(1, 1, 1, 'comment', 'Started working on the migration script. Tables structure is ready.'),
(1, 1, 1, 'status_change', 'Status changed from todo to in_progress'),
(1, 4, 1, 'comment', 'This is affecting all users in EST timezone. High priority fix needed.');

-- =====================================================
-- STORED PROCEDURES FOR COMMON OPERATIONS
-- =====================================================

DELIMITER $$

-- Procedure to get upcoming events for a user
DROP PROCEDURE IF EXISTS `sp_get_upcoming_events`$$
CREATE PROCEDURE `sp_get_upcoming_events`(
    IN p_tenant_id BIGINT,
    IN p_user_id BIGINT,
    IN p_days_ahead INT
)
BEGIN
    SELECT
        e.*,
        c.name as calendar_name,
        c.color as calendar_color
    FROM events e
    INNER JOIN calendars c ON e.calendar_id = c.id
    WHERE e.tenant_id = p_tenant_id
        AND c.user_id = p_user_id
        AND e.start_datetime BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL p_days_ahead DAY)
        AND e.deleted_at IS NULL
        AND e.status != 'cancelled'
    ORDER BY e.start_datetime ASC;
END$$

-- Procedure to get task statistics for a user
DROP PROCEDURE IF EXISTS `sp_get_task_statistics`$$
CREATE PROCEDURE `sp_get_task_statistics`(
    IN p_tenant_id BIGINT,
    IN p_user_id BIGINT
)
BEGIN
    SELECT
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
        SUM(CASE WHEN due_date < CURDATE() AND status NOT IN ('done', 'cancelled') THEN 1 ELSE 0 END) as overdue_tasks
    FROM tasks
    WHERE tenant_id = p_tenant_id
        AND assignee_id = p_user_id
        AND deleted_at IS NULL;
END$$

DELIMITER ;

-- =====================================================
-- TRIGGERS FOR DATA INTEGRITY
-- =====================================================

DELIMITER $$

-- Trigger to update task_count in task_lists
DROP TRIGGER IF EXISTS `trg_update_task_list_count`$$
CREATE TRIGGER `trg_update_task_list_count`
AFTER INSERT ON `tasks`
FOR EACH ROW
BEGIN
    UPDATE task_lists
    SET task_count = task_count + 1,
        completed_task_count = completed_task_count + IF(NEW.status = 'done', 1, 0)
    WHERE id = NEW.task_list_id;
END$$

-- Trigger to handle task completion timestamp
DROP TRIGGER IF EXISTS `trg_task_completion`$$
CREATE TRIGGER `trg_task_completion`
BEFORE UPDATE ON `tasks`
FOR EACH ROW
BEGIN
    IF NEW.status = 'done' AND OLD.status != 'done' THEN
        SET NEW.completed_at = NOW();
    ELSEIF NEW.status != 'done' AND OLD.status = 'done' THEN
        SET NEW.completed_at = NULL;
    END IF;
END$$

DELIMITER ;

-- =====================================================
-- VIEWS FOR COMMON QUERIES
-- =====================================================

-- View for active events (not cancelled, not deleted)
CREATE OR REPLACE VIEW `v_active_events` AS
SELECT
    e.*,
    c.name as calendar_name,
    c.color as calendar_color,
    c.type as calendar_type,
    u.first_name as organizer_first_name,
    u.last_name as organizer_last_name,
    u.email as organizer_email
FROM events e
INNER JOIN calendars c ON e.calendar_id = c.id
LEFT JOIN users u ON e.organizer_id = u.id
WHERE e.deleted_at IS NULL
    AND e.status != 'cancelled'
    AND c.deleted_at IS NULL;

-- View for active tasks with assignment info
CREATE OR REPLACE VIEW `v_active_tasks` AS
SELECT
    t.*,
    tl.name as list_name,
    tl.color as list_color,
    assignee.first_name as assignee_first_name,
    assignee.last_name as assignee_last_name,
    reporter.first_name as reporter_first_name,
    reporter.last_name as reporter_last_name
FROM tasks t
INNER JOIN task_lists tl ON t.task_list_id = tl.id
LEFT JOIN users assignee ON t.assignee_id = assignee.id
LEFT JOIN users reporter ON t.reporter_id = reporter.id
WHERE t.deleted_at IS NULL
    AND tl.deleted_at IS NULL
    AND tl.is_archived = FALSE;

-- =====================================================
-- GRANT PERMISSIONS (adjust as needed)
-- =====================================================
-- Note: These grants assume you have appropriate user accounts set up
-- GRANT SELECT, INSERT, UPDATE, DELETE ON collabora_files.* TO 'webapp'@'localhost';
-- GRANT EXECUTE ON PROCEDURE collabora_files.sp_get_upcoming_events TO 'webapp'@'localhost';
-- GRANT EXECUTE ON PROCEDURE collabora_files.sp_get_task_statistics TO 'webapp'@'localhost';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- END OF MIGRATION
-- =====================================================