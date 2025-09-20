-- Apply remaining fixes

-- Fix tenants table
ALTER TABLE tenants ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

-- Update status values for existing tenants
UPDATE tenants SET status = 'active' WHERE status IS NULL;

-- Display current state
SELECT 'Checking Users:' as Status;
SELECT id, email, role, is_system_admin, status, last_active_tenant_id FROM users LIMIT 5;

SELECT 'Checking Tenants:' as Status;
SELECT id, code, name, status FROM tenants;

SELECT 'Checking Associations:' as Status;
SELECT * FROM user_tenant_associations;

SELECT 'Schema fixes completed' as Status;