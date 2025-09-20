-- =====================================================
-- Part 3: Tenant-less Login Flow Enhancement Migration
-- MariaDB 10.6+ / MySQL 8.0+ Compatible
-- Created: 2025-01-20
-- Version: 3.0.0
--
-- Purpose: Enhance schema for seamless tenant-less authentication
-- Author: Database Architecture Team
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';

-- Use the existing database
USE `collabora_files`;

-- =====================================================
-- SCHEMA VERIFICATION & ENHANCEMENTS
-- =====================================================

-- Verify and enhance users table
-- The last_active_tenant_id already exists, but let's ensure proper indexing
ALTER TABLE `users`
  MODIFY COLUMN `last_active_tenant_id` BIGINT UNSIGNED DEFAULT NULL
    COMMENT 'Last accessed tenant for quick switch and default selection';

-- Add index if not exists for faster tenant switching
-- Note: This might already exist from schema_v2, but safe to re-create
DROP INDEX IF EXISTS `idx_users_last_active_tenant` ON `users`;
CREATE INDEX `idx_users_last_active_tenant` ON `users`(`last_active_tenant_id`, `status`);

-- =====================================================
-- Table: tenant_preferences
-- Store user-specific preferences per tenant
-- =====================================================
DROP TABLE IF EXISTS `tenant_preferences`;
CREATE TABLE `tenant_preferences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `preference_key` VARCHAR(100) NOT NULL,
  `preference_value` JSON DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_prefs` (`user_id`, `tenant_id`, `preference_key`),
  KEY `idx_tenant_prefs_user` (`user_id`),
  KEY `idx_tenant_prefs_tenant` (`tenant_id`),
  CONSTRAINT `fk_tenant_prefs_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tenant_prefs_tenant` FOREIGN KEY (`tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User preferences specific to each tenant';

-- =====================================================
-- Table: login_history
-- Track login attempts and tenant selections
-- =====================================================
DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `login_status` ENUM('success', 'failed', 'blocked') NOT NULL,
  `selected_tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `failure_reason` VARCHAR(255) DEFAULT NULL,
  `session_id` VARCHAR(128) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_history_user` (`user_id`),
  KEY `idx_login_history_email` (`email`),
  KEY `idx_login_history_status` (`login_status`),
  KEY `idx_login_history_created` (`created_at`),
  KEY `idx_login_history_ip` (`ip_address`),
  CONSTRAINT `fk_login_history_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_login_history_tenant` FOREIGN KEY (`selected_tenant_id`)
    REFERENCES `tenants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Detailed login history for security auditing'
PARTITION BY RANGE (YEAR(created_at)) (
  PARTITION p2025 VALUES LESS THAN (2026),
  PARTITION p2026 VALUES LESS THAN (2027),
  PARTITION p_future VALUES LESS THAN MAXVALUE
);

-- =====================================================
-- Enhanced Indexes for Performance
-- =====================================================

-- Optimize user-tenant association lookups
DROP INDEX IF EXISTS `idx_uta_user_active` ON `user_tenant_associations`;
CREATE INDEX `idx_uta_user_active` ON `user_tenant_associations`(
  `user_id`,
  `access_expires_at`,
  `tenant_id`
) WHERE `access_expires_at` IS NULL OR `access_expires_at` > NOW();

-- Optimize tenant lookup by status and subscription
DROP INDEX IF EXISTS `idx_tenants_active` ON `tenants`;
CREATE INDEX `idx_tenants_active` ON `tenants`(
  `status`,
  `deleted_at`,
  `subscription_expires_at`
) WHERE `status` = 'active' AND `deleted_at` IS NULL;

-- =====================================================
-- STORED PROCEDURES FOR TENANT-LESS LOGIN
-- =====================================================

DELIMITER $$

-- Procedure: Authenticate user and get tenant associations
DROP PROCEDURE IF EXISTS `sp_authenticate_user`$$
CREATE PROCEDURE `sp_authenticate_user`(
  IN p_email VARCHAR(255),
  OUT p_user_id BIGINT,
  OUT p_user_role VARCHAR(20),
  OUT p_user_status VARCHAR(20)
)
BEGIN
  SELECT
    id,
    role,
    status
  INTO
    p_user_id,
    p_user_role,
    p_user_status
  FROM users
  WHERE email = p_email
    AND deleted_at IS NULL;
END$$

-- Procedure: Get user's available tenants after authentication
DROP PROCEDURE IF EXISTS `sp_get_user_available_tenants`$$
CREATE PROCEDURE `sp_get_user_available_tenants`(
  IN p_user_id BIGINT,
  IN p_user_role VARCHAR(20)
)
BEGIN
  IF p_user_role = 'admin' THEN
    -- Admin sees all active tenants
    SELECT
      t.id,
      t.code,
      t.name,
      t.status,
      t.settings,
      'full_control' as access_level,
      FALSE as is_primary,
      NULL as tenant_role
    FROM tenants t
    WHERE t.status = 'active'
      AND t.deleted_at IS NULL
    ORDER BY t.name;
  ELSE
    -- Other users see only associated tenants
    SELECT
      t.id,
      t.code,
      t.name,
      t.status,
      t.settings,
      CASE
        WHEN uta.tenant_role = 'owner' THEN 'full'
        WHEN uta.tenant_role = 'admin' THEN 'admin'
        WHEN uta.tenant_role = 'manager' THEN 'manage'
        ELSE 'member'
      END as access_level,
      uta.is_primary,
      uta.tenant_role
    FROM user_tenant_associations uta
    INNER JOIN tenants t ON uta.tenant_id = t.id
    WHERE uta.user_id = p_user_id
      AND t.status = 'active'
      AND t.deleted_at IS NULL
      AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
    ORDER BY uta.is_primary DESC, t.name;
  END IF;
END$$

-- Procedure: Auto-select default tenant for user
DROP PROCEDURE IF EXISTS `sp_get_default_tenant`$$
CREATE PROCEDURE `sp_get_default_tenant`(
  IN p_user_id BIGINT,
  IN p_user_role VARCHAR(20),
  OUT p_tenant_id BIGINT,
  OUT p_tenant_code VARCHAR(20),
  OUT p_tenant_name VARCHAR(255)
)
BEGIN
  DECLARE v_last_tenant_id BIGINT;

  -- Get user's last active tenant
  SELECT last_active_tenant_id INTO v_last_tenant_id
  FROM users WHERE id = p_user_id;

  -- Try to use last active tenant if still valid
  IF v_last_tenant_id IS NOT NULL THEN
    IF p_user_role = 'admin' THEN
      -- Admin can access any active tenant
      SELECT id, code, name
      INTO p_tenant_id, p_tenant_code, p_tenant_name
      FROM tenants
      WHERE id = v_last_tenant_id
        AND status = 'active'
        AND deleted_at IS NULL;
    ELSE
      -- Check if user still has access to last tenant
      SELECT t.id, t.code, t.name
      INTO p_tenant_id, p_tenant_code, p_tenant_name
      FROM tenants t
      INNER JOIN user_tenant_associations uta ON t.id = uta.tenant_id
      WHERE t.id = v_last_tenant_id
        AND uta.user_id = p_user_id
        AND t.status = 'active'
        AND t.deleted_at IS NULL
        AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW());
    END IF;
  END IF;

  -- If no last tenant or invalid, get primary or first available
  IF p_tenant_id IS NULL THEN
    IF p_user_role = 'admin' THEN
      -- Get first active tenant for admin
      SELECT id, code, name
      INTO p_tenant_id, p_tenant_code, p_tenant_name
      FROM tenants
      WHERE status = 'active'
        AND deleted_at IS NULL
      ORDER BY name
      LIMIT 1;
    ELSE
      -- Get primary or first available tenant for user
      SELECT t.id, t.code, t.name
      INTO p_tenant_id, p_tenant_code, p_tenant_name
      FROM tenants t
      INNER JOIN user_tenant_associations uta ON t.id = uta.tenant_id
      WHERE uta.user_id = p_user_id
        AND t.status = 'active'
        AND t.deleted_at IS NULL
        AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
      ORDER BY uta.is_primary DESC, t.name
      LIMIT 1;
    END IF;
  END IF;
END$$

-- Procedure: Log successful login with tenant selection
DROP PROCEDURE IF EXISTS `sp_log_successful_login`$$
CREATE PROCEDURE `sp_log_successful_login`(
  IN p_user_id BIGINT,
  IN p_email VARCHAR(255),
  IN p_tenant_id BIGINT,
  IN p_ip_address VARCHAR(45),
  IN p_user_agent VARCHAR(500),
  IN p_session_id VARCHAR(128)
)
BEGIN
  -- Insert login history
  INSERT INTO login_history (
    user_id, email, login_status, selected_tenant_id,
    ip_address, user_agent, session_id, created_at
  ) VALUES (
    p_user_id, p_email, 'success', p_tenant_id,
    p_ip_address, p_user_agent, p_session_id, NOW()
  );

  -- Update user's last login info and last active tenant
  UPDATE users
  SET
    last_login_at = NOW(),
    last_login_ip = p_ip_address,
    last_active_tenant_id = p_tenant_id,
    failed_login_attempts = 0,
    locked_until = NULL
  WHERE id = p_user_id;

  -- Log to audit table
  INSERT INTO audit_logs (
    tenant_id, user_id, activity_type, activity_category,
    ip_address, user_agent, session_id, details, created_at
  ) VALUES (
    p_tenant_id, p_user_id, 'user_login', 'auth',
    p_ip_address, p_user_agent, p_session_id,
    JSON_OBJECT('method', 'tenant_less', 'tenant_id', p_tenant_id),
    NOW()
  );
END$$

-- Function: Check if user needs tenant selection
DROP FUNCTION IF EXISTS `fn_needs_tenant_selection`$$
CREATE FUNCTION `fn_needs_tenant_selection`(
  p_user_id BIGINT,
  p_user_role VARCHAR(20)
) RETURNS BOOLEAN
DETERMINISTIC
READS SQL DATA
BEGIN
  DECLARE v_tenant_count INT;

  IF p_user_role = 'admin' THEN
    -- Admin always has multiple tenants available
    SELECT COUNT(*) INTO v_tenant_count
    FROM tenants
    WHERE status = 'active' AND deleted_at IS NULL;
  ELSE
    -- Count user's accessible tenants
    SELECT COUNT(*) INTO v_tenant_count
    FROM user_tenant_associations uta
    INNER JOIN tenants t ON uta.tenant_id = t.id
    WHERE uta.user_id = p_user_id
      AND t.status = 'active'
      AND t.deleted_at IS NULL
      AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW());
  END IF;

  -- Need selection if more than one tenant available
  RETURN v_tenant_count > 1;
END$$

DELIMITER ;

-- =====================================================
-- VIEWS FOR TENANT-LESS LOGIN
-- =====================================================

-- View: User login eligibility with tenant counts
CREATE OR REPLACE VIEW `v_user_login_eligibility` AS
SELECT
  u.id,
  u.email,
  u.first_name,
  u.last_name,
  u.role,
  u.status,
  u.last_login_at,
  u.last_active_tenant_id,
  u.failed_login_attempts,
  u.locked_until,
  CASE
    WHEN u.role = 'admin' THEN
      (SELECT COUNT(*) FROM tenants WHERE status = 'active' AND deleted_at IS NULL)
    ELSE
      (SELECT COUNT(*) FROM user_tenant_associations uta
       INNER JOIN tenants t ON uta.tenant_id = t.id
       WHERE uta.user_id = u.id
         AND t.status = 'active'
         AND t.deleted_at IS NULL
         AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW()))
  END as accessible_tenant_count,
  CASE
    WHEN u.status != 'active' THEN FALSE
    WHEN u.locked_until IS NOT NULL AND u.locked_until > NOW() THEN FALSE
    WHEN u.role = 'admin' THEN TRUE
    ELSE EXISTS (
      SELECT 1 FROM user_tenant_associations uta
      INNER JOIN tenants t ON uta.tenant_id = t.id
      WHERE uta.user_id = u.id
        AND t.status = 'active'
        AND t.deleted_at IS NULL
        AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
    )
  END as can_login
FROM users u
WHERE u.deleted_at IS NULL;

-- View: Tenant selection dashboard
CREATE OR REPLACE VIEW `v_tenant_selection` AS
SELECT
  t.id as tenant_id,
  t.code as tenant_code,
  t.name as tenant_name,
  t.status,
  t.subscription_tier,
  COUNT(DISTINCT uta.user_id) as user_count,
  t.storage_used_bytes,
  t.storage_quota_gb * 1024 * 1024 * 1024 as storage_quota_bytes,
  t.settings
FROM tenants t
LEFT JOIN user_tenant_associations uta ON t.id = uta.tenant_id
  AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
WHERE t.status = 'active'
  AND t.deleted_at IS NULL
GROUP BY t.id;

-- =====================================================
-- SAMPLE QUERIES FOR COMMON OPERATIONS
-- =====================================================

-- Query 1: Authenticate user and get their tenants
-- Used after email/password validation
/*
CALL sp_authenticate_user('user@example.com', @user_id, @role, @status);
CALL sp_get_user_available_tenants(@user_id, @role);
*/

-- Query 2: Get default tenant for auto-selection
/*
CALL sp_get_default_tenant(@user_id, @role, @tenant_id, @tenant_code, @tenant_name);
*/

-- Query 3: Check if user needs tenant selection screen
/*
SELECT fn_needs_tenant_selection(@user_id, @role) as needs_selection;
*/

-- Query 4: Switch user to different tenant
/*
CALL sp_switch_tenant(@user_id, @new_tenant_id);
*/

-- Query 5: Get user's login eligibility
/*
SELECT * FROM v_user_login_eligibility WHERE email = 'user@example.com';
*/

-- =====================================================
-- DATA MIGRATION FOR EXISTING SYSTEMS
-- =====================================================

-- Ensure all users with tenant associations have a primary tenant set
UPDATE user_tenant_associations uta1
SET is_primary = TRUE
WHERE NOT EXISTS (
  SELECT 1 FROM (
    SELECT user_id FROM user_tenant_associations WHERE is_primary = TRUE
  ) uta2 WHERE uta2.user_id = uta1.user_id
)
AND uta1.id = (
  SELECT MIN(id) FROM (
    SELECT id, user_id FROM user_tenant_associations
  ) uta3 WHERE uta3.user_id = uta1.user_id
);

-- Set last_active_tenant_id for users who don't have one
UPDATE users u
SET last_active_tenant_id = (
  SELECT tenant_id
  FROM user_tenant_associations uta
  WHERE uta.user_id = u.id
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
  ORDER BY uta.is_primary DESC, uta.id ASC
  LIMIT 1
)
WHERE u.last_active_tenant_id IS NULL
  AND u.role != 'admin'
  AND EXISTS (
    SELECT 1 FROM user_tenant_associations
    WHERE user_id = u.id
  );

-- For admins, set the first available tenant as default
UPDATE users u
SET last_active_tenant_id = (
  SELECT id FROM tenants
  WHERE status = 'active' AND deleted_at IS NULL
  ORDER BY name
  LIMIT 1
)
WHERE u.last_active_tenant_id IS NULL
  AND u.role = 'admin';

-- =====================================================
-- PERFORMANCE OPTIMIZATION ANALYSIS
-- =====================================================

-- Analyze table statistics for optimizer
ANALYZE TABLE users;
ANALYZE TABLE tenants;
ANALYZE TABLE user_tenant_associations;
ANALYZE TABLE sessions;
ANALYZE TABLE audit_logs;

-- =====================================================
-- SECURITY ENHANCEMENTS
-- =====================================================

-- Create event to clean up old login history (keep 90 days)
DELIMITER $$

DROP EVENT IF EXISTS `evt_cleanup_login_history`$$
CREATE EVENT `evt_cleanup_login_history`
ON SCHEDULE EVERY 1 DAY
STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 2 HOUR)
DO
BEGIN
  DELETE FROM login_history
  WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- MIGRATION COMPLETE
-- =====================================================
/*
MIGRATION SUMMARY:
1. Enhanced user table indexing for tenant switching
2. Added tenant_preferences table for per-tenant user settings
3. Added login_history table for security auditing
4. Created optimized indexes for tenant-less login queries
5. Added stored procedures for authentication workflow
6. Created views for login eligibility and tenant selection
7. Included data migration for existing records
8. Added performance optimization and security features

USAGE NOTES:
- Run this migration after schema_v2.sql and migrations_part2.sql
- Existing data will be automatically migrated
- No data loss will occur
- All new features are backward compatible
*/