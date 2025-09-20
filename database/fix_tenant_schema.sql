-- Fix database schema for tenant-less login flow

-- Add last_active_tenant_id column to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_active_tenant_id BIGINT UNSIGNED DEFAULT NULL
AFTER is_system_admin;

-- Add index for performance
ALTER TABLE users ADD INDEX idx_last_active_tenant (last_active_tenant_id);

-- Fix tenants table - ensure required columns exist
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add status column if missing
ALTER TABLE tenants MODIFY COLUMN status ENUM('active', 'inactive', 'suspended', 'archived') DEFAULT 'active';

-- Ensure user_tenant_associations table exists
CREATE TABLE IF NOT EXISTS user_tenant_associations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    tenant_role ENUM('owner', 'admin', 'manager', 'member', 'viewer') DEFAULT 'member',
    access_expires_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_user_tenant (user_id, tenant_id),
    KEY idx_uta_user_active (user_id, access_expires_at, tenant_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default tenant if not exists
INSERT IGNORE INTO tenants (id, code, name, status) VALUES
(1, 'DEFAULT', 'Default Tenant', 'active'),
(2, 'DEMO', 'Demo Tenant', 'active');

-- Ensure admin has association with tenants (for non-admin users)
-- Admin users don't need associations as they have access to all tenants
-- But let's add one for testing purposes
-- First check if the association exists, if not create it
INSERT INTO user_tenant_associations (user_id, tenant_id, is_primary, tenant_role)
SELECT 1, 1, 1, 'owner'
WHERE NOT EXISTS (
    SELECT 1 FROM user_tenant_associations
    WHERE user_id = 1 AND tenant_id = 1
) AND EXISTS (SELECT 1 FROM users WHERE id = 1);

-- Update admin user to ensure it has proper role
UPDATE users SET role = 'admin', is_system_admin = 1 WHERE email = 'asamodeo@fortibyte.it';

-- Display summary
SELECT 'Schema fixes applied successfully' as Status;