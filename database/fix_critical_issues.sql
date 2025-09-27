-- =====================================================
-- Critical Database Fixes for Nexio Collabora V2
-- Date: 2025-01-21
-- Description: Fixes missing tables and columns that are causing API errors
-- =====================================================

-- Use the correct database
USE nexio_collabora_v2;

-- =====================================================
-- 1. CREATE calendar_shares table if it doesn't exist
-- =====================================================
CREATE TABLE IF NOT EXISTS `calendar_shares` (
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
  INDEX `idx_created_by` (`created_by`),
  CONSTRAINT `fk_calendar_shares_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_shares_calendar` FOREIGN KEY (`calendar_id`)
    REFERENCES `calendars` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_shares_user` FOREIGN KEY (`shared_with_user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_calendar_shares_created_by` FOREIGN KEY (`created_by`)
    REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Calendar sharing permissions and external access';

-- =====================================================
-- 2. ADD missing columns to task_lists table
-- =====================================================

-- Check if deleted_at column exists, add if missing
SET @dbname = DATABASE();
SET @tablename = 'task_lists';
SET @columnname = 'deleted_at';
SET @preparedStatement = (
  SELECT IF(
    (
      SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = @columnname
    ) > 0,
    'SELECT "Column deleted_at already exists in task_lists" AS status;',
    'ALTER TABLE `task_lists` ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT "Soft delete timestamp", ADD INDEX `idx_deleted_at` (`deleted_at`);'
  )
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check if color column exists, add if missing
SET @columnname = 'color';
SET @preparedStatement = (
  SELECT IF(
    (
      SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = @tablename
        AND COLUMN_NAME = @columnname
    ) > 0,
    'SELECT "Column color already exists in task_lists" AS status;',
    'ALTER TABLE `task_lists` ADD COLUMN `color` VARCHAR(7) DEFAULT "#6B7280" COMMENT "Hex color for UI display" AFTER `description`;'
  )
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- 3. Verify and report table structures
-- =====================================================

-- Check calendar_shares table
SELECT
  CONCAT('calendar_shares table ',
    IF(COUNT(*) > 0, 'EXISTS', 'MISSING')
  ) AS status,
  COUNT(*) AS column_count
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'calendar_shares';

-- Check task_lists columns
SELECT
  COLUMN_NAME,
  DATA_TYPE,
  IS_NULLABLE,
  COLUMN_DEFAULT,
  COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'task_lists'
  AND COLUMN_NAME IN ('deleted_at', 'color')
ORDER BY ORDINAL_POSITION;

-- =====================================================
-- 4. Insert sample data for testing (if tables are empty)
-- =====================================================

-- Add sample calendar share if table is empty
INSERT IGNORE INTO `calendar_shares`
  (tenant_id, calendar_id, shared_with_user_id, permission_level, can_edit_events, created_by)
SELECT
  1, 1, 2, 'write', TRUE, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `calendar_shares` LIMIT 1)
  AND EXISTS (SELECT 1 FROM `calendars` WHERE id = 1)
  AND EXISTS (SELECT 1 FROM `users` WHERE id = 2);

-- =====================================================
-- 5. Create indexes for performance
-- =====================================================

-- Ensure composite index exists for multi-tenant queries
SET @indexname = 'idx_tenant_deleted';
SET @preparedStatement = (
  SELECT IF(
    (
      SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
      WHERE TABLE_SCHEMA = @dbname
        AND TABLE_NAME = 'task_lists'
        AND INDEX_NAME = @indexname
    ) > 0,
    'SELECT "Index idx_tenant_deleted already exists" AS status;',
    'ALTER TABLE `task_lists` ADD INDEX `idx_tenant_deleted` (`tenant_id`, `deleted_at`);'
  )
);
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- =====================================================
-- 6. Grant appropriate permissions
-- =====================================================

-- Grant permissions for the application user (adjust username as needed)
-- This assumes you're using 'root' for XAMPP, but adjust if different
GRANT SELECT, INSERT, UPDATE, DELETE ON `nexio_collabora_v2`.`calendar_shares` TO 'root'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `nexio_collabora_v2`.`task_lists` TO 'root'@'localhost';
FLUSH PRIVILEGES;

-- =====================================================
-- Final status report
-- =====================================================
SELECT 'Database fixes completed successfully!' AS status;

-- Show final table counts
SELECT
  'calendar_shares' AS table_name,
  COUNT(*) AS record_count
FROM calendar_shares
UNION ALL
SELECT
  'task_lists' AS table_name,
  COUNT(*) AS record_count
FROM task_lists;