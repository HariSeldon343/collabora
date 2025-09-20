-- =====================================================
-- Part 3: Sample Queries for Tenant-Less Login Flow
-- MariaDB 10.6+ / MySQL 8.0+ Compatible
-- Created: 2025-01-20
-- Version: 3.0.0
--
-- Purpose: Common query patterns for tenant-less authentication
-- =====================================================

-- =====================================================
-- AUTHENTICATION QUERIES
-- =====================================================

-- 1. Authenticate user by email (no tenant required)
SELECT
    u.id,
    u.email,
    u.password,
    u.first_name,
    u.last_name,
    u.role,
    u.status,
    u.last_active_tenant_id,
    u.failed_login_attempts,
    u.locked_until,
    u.two_factor_enabled
FROM users u
WHERE u.email = 'asamodeo@fortibyte.it'
    AND u.deleted_at IS NULL
    AND u.status = 'active'
    AND (u.locked_until IS NULL OR u.locked_until < NOW());

-- 2. Get user's available tenants (for admin)
-- Admin users see ALL active tenants
SELECT
    t.id as tenant_id,
    t.code as tenant_code,
    t.name as tenant_name,
    t.status,
    t.settings,
    'full_control' as access_level,
    FALSE as is_primary,
    NULL as tenant_role,
    (t.id = 1) as is_last_active -- Replace 1 with actual last_active_tenant_id
FROM tenants t
WHERE t.status = 'active'
    AND t.deleted_at IS NULL
ORDER BY t.name;

-- 3. Get user's available tenants (for special_user/standard_user)
-- Non-admin users see only associated tenants
SELECT
    t.id as tenant_id,
    t.code as tenant_code,
    t.name as tenant_name,
    t.status,
    t.settings,
    uta.tenant_role,
    uta.is_primary,
    uta.permissions,
    uta.modules_access,
    (t.id = u.last_active_tenant_id) as is_last_active
FROM user_tenant_associations uta
INNER JOIN tenants t ON uta.tenant_id = t.id
INNER JOIN users u ON uta.user_id = u.id
WHERE uta.user_id = 2 -- Replace with actual user_id
    AND t.status = 'active'
    AND t.deleted_at IS NULL
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
ORDER BY uta.is_primary DESC, t.name;

-- 4. Check if user needs tenant selection screen
SELECT
    u.id,
    u.email,
    u.role,
    CASE
        WHEN u.role = 'admin' THEN
            (SELECT COUNT(*) FROM tenants WHERE status = 'active' AND deleted_at IS NULL)
        ELSE
            (SELECT COUNT(*) FROM user_tenant_associations uta2
             INNER JOIN tenants t2 ON uta2.tenant_id = t2.id
             WHERE uta2.user_id = u.id
                AND t2.status = 'active'
                AND t2.deleted_at IS NULL
                AND (uta2.access_expires_at IS NULL OR uta2.access_expires_at > NOW()))
    END as tenant_count,
    CASE
        WHEN u.role = 'admin' THEN
            (SELECT COUNT(*) FROM tenants WHERE status = 'active' AND deleted_at IS NULL) > 1
        ELSE
            (SELECT COUNT(*) FROM user_tenant_associations uta3
             INNER JOIN tenants t3 ON uta3.tenant_id = t3.id
             WHERE uta3.user_id = u.id
                AND t3.status = 'active'
                AND t3.deleted_at IS NULL
                AND (uta3.access_expires_at IS NULL OR uta3.access_expires_at > NOW())) > 1
    END as needs_selection
FROM users u
WHERE u.id = 1; -- Replace with actual user_id

-- 5. Get user's default/primary tenant
SELECT
    t.id as tenant_id,
    t.code as tenant_code,
    t.name as tenant_name,
    COALESCE(uta.tenant_role, 'admin') as role_in_tenant
FROM users u
LEFT JOIN user_tenant_associations uta ON u.id = uta.user_id
    AND uta.is_primary = TRUE
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
LEFT JOIN tenants t ON (
    CASE
        WHEN u.role = 'admin' THEN
            t.id = COALESCE(u.last_active_tenant_id, (SELECT id FROM tenants WHERE status = 'active' AND deleted_at IS NULL ORDER BY name LIMIT 1))
        ELSE
            t.id = COALESCE(uta.tenant_id, u.last_active_tenant_id)
    END
)
WHERE u.id = 1 -- Replace with actual user_id
    AND t.status = 'active'
    AND t.deleted_at IS NULL
LIMIT 1;

-- =====================================================
-- TENANT SWITCHING QUERIES
-- =====================================================

-- 6. Validate user can switch to specific tenant
SELECT
    CASE
        WHEN u.role = 'admin' THEN TRUE
        WHEN u.role = 'standard_user' THEN FALSE
        ELSE EXISTS (
            SELECT 1 FROM user_tenant_associations uta
            INNER JOIN tenants t ON uta.tenant_id = t.id
            WHERE uta.user_id = u.id
                AND uta.tenant_id = 2 -- Target tenant_id
                AND t.status = 'active'
                AND t.deleted_at IS NULL
                AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
        )
    END as can_switch,
    u.role,
    u.last_active_tenant_id
FROM users u
WHERE u.id = 1; -- User attempting to switch

-- 7. Update user's last active tenant
UPDATE users
SET
    last_active_tenant_id = 2, -- New tenant_id
    updated_at = NOW()
WHERE id = 1; -- User_id

-- 8. Log tenant switch in audit
INSERT INTO audit_logs (
    tenant_id,
    user_id,
    activity_type,
    activity_category,
    entity_type,
    entity_id,
    details,
    ip_address,
    user_agent,
    session_id,
    created_at
) VALUES (
    2, -- New tenant_id
    1, -- User_id
    'tenant_switch',
    'auth',
    'tenant',
    2, -- New tenant_id
    JSON_OBJECT(
        'from_tenant_id', 1,
        'to_tenant_id', 2,
        'method', 'user_initiated'
    ),
    '192.168.1.100',
    'Mozilla/5.0...',
    'session_abc123',
    NOW()
);

-- =====================================================
-- SESSION MANAGEMENT QUERIES
-- =====================================================

-- 9. Create/update session with tenant
INSERT INTO sessions (
    id,
    user_id,
    tenant_id,
    ip_address,
    user_agent,
    payload,
    last_activity,
    expires_at,
    created_at
) VALUES (
    'session_abc123',
    1, -- User_id
    2, -- Current tenant_id
    '192.168.1.100',
    'Mozilla/5.0...',
    '{"authenticated":true}',
    UNIX_TIMESTAMP(),
    DATE_ADD(NOW(), INTERVAL 2 HOUR),
    NOW()
) ON DUPLICATE KEY UPDATE
    tenant_id = VALUES(tenant_id),
    last_activity = VALUES(last_activity),
    expires_at = VALUES(expires_at);

-- 10. Get session with user and tenant info
SELECT
    s.id as session_id,
    s.user_id,
    s.tenant_id,
    u.email,
    u.first_name,
    u.last_name,
    u.role as user_role,
    t.code as tenant_code,
    t.name as tenant_name,
    uta.tenant_role,
    s.last_activity,
    s.expires_at
FROM sessions s
INNER JOIN users u ON s.user_id = u.id
INNER JOIN tenants t ON s.tenant_id = t.id
LEFT JOIN user_tenant_associations uta ON u.id = uta.user_id AND t.id = uta.tenant_id
WHERE s.id = 'session_abc123'
    AND s.expires_at > NOW()
    AND u.status = 'active'
    AND t.status = 'active';

-- =====================================================
-- ANALYTICS & REPORTING QUERIES
-- =====================================================

-- 11. User login statistics by role
SELECT
    u.role,
    COUNT(DISTINCT u.id) as total_users,
    COUNT(DISTINCT CASE WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.id END) as active_30_days,
    COUNT(DISTINCT CASE WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN u.id END) as active_7_days,
    COUNT(DISTINCT CASE WHEN u.last_login_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN u.id END) as active_today
FROM users u
WHERE u.deleted_at IS NULL
GROUP BY u.role;

-- 12. Tenant usage statistics
SELECT
    t.id as tenant_id,
    t.code,
    t.name,
    t.subscription_tier,
    COUNT(DISTINCT uta.user_id) as user_count,
    COUNT(DISTINCT CASE WHEN uta.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN uta.user_id END) as new_users_30_days,
    t.storage_used_bytes,
    t.storage_quota_gb * 1024 * 1024 * 1024 as storage_quota_bytes,
    ROUND((t.storage_used_bytes / (t.storage_quota_gb * 1024 * 1024 * 1024)) * 100, 2) as storage_usage_percent
FROM tenants t
LEFT JOIN user_tenant_associations uta ON t.id = uta.tenant_id
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
WHERE t.deleted_at IS NULL
GROUP BY t.id
ORDER BY user_count DESC;

-- 13. Multi-tenant user analysis
SELECT
    u.id,
    u.email,
    u.role,
    COUNT(DISTINCT uta.tenant_id) as tenant_count,
    GROUP_CONCAT(t.name ORDER BY uta.is_primary DESC, t.name SEPARATOR ', ') as tenant_names,
    MAX(CASE WHEN uta.is_primary = TRUE THEN t.name END) as primary_tenant
FROM users u
LEFT JOIN user_tenant_associations uta ON u.id = uta.user_id
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
LEFT JOIN tenants t ON uta.tenant_id = t.id
    AND t.status = 'active'
    AND t.deleted_at IS NULL
WHERE u.deleted_at IS NULL
    AND u.status = 'active'
GROUP BY u.id
HAVING tenant_count > 1 OR u.role = 'admin'
ORDER BY tenant_count DESC;

-- =====================================================
-- SECURITY & COMPLIANCE QUERIES
-- =====================================================

-- 14. Failed login attempts in last hour
SELECT
    lh.email,
    COUNT(*) as attempt_count,
    MAX(lh.created_at) as last_attempt,
    lh.ip_address,
    lh.failure_reason
FROM login_history lh
WHERE lh.login_status = 'failed'
    AND lh.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY lh.email, lh.ip_address
HAVING attempt_count >= 3
ORDER BY attempt_count DESC;

-- 15. Users with expiring tenant access
SELECT
    u.id,
    u.email,
    u.first_name,
    u.last_name,
    t.name as tenant_name,
    uta.access_expires_at,
    DATEDIFF(uta.access_expires_at, NOW()) as days_until_expiry
FROM user_tenant_associations uta
INNER JOIN users u ON uta.user_id = u.id
INNER JOIN tenants t ON uta.tenant_id = t.id
WHERE uta.access_expires_at IS NOT NULL
    AND uta.access_expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
    AND u.deleted_at IS NULL
    AND t.deleted_at IS NULL
ORDER BY uta.access_expires_at;

-- 16. Audit trail for tenant access changes
SELECT
    al.created_at,
    al.activity_type,
    u.email as user_email,
    al.entity_type,
    al.entity_id,
    t.name as tenant_name,
    al.details,
    al.ip_address
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
LEFT JOIN tenants t ON al.tenant_id = t.id
WHERE al.activity_category = 'auth'
    AND al.activity_type IN ('tenant_switch', 'user_login', 'tenant_access_granted', 'tenant_access_revoked')
    AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY al.created_at DESC
LIMIT 100;

-- =====================================================
-- MAINTENANCE QUERIES
-- =====================================================

-- 17. Clean up orphaned associations (users with no valid tenants)
SELECT
    u.id,
    u.email,
    u.role,
    u.status,
    u.last_login_at,
    COUNT(uta.id) as association_count,
    COUNT(CASE WHEN t.status = 'active' THEN 1 END) as active_tenant_count
FROM users u
LEFT JOIN user_tenant_associations uta ON u.id = uta.user_id
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
LEFT JOIN tenants t ON uta.tenant_id = t.id
    AND t.deleted_at IS NULL
WHERE u.deleted_at IS NULL
    AND u.role != 'admin'
GROUP BY u.id
HAVING active_tenant_count = 0;

-- 18. Fix missing primary tenant designations
UPDATE user_tenant_associations uta1
SET is_primary = TRUE
WHERE uta1.id = (
    SELECT id FROM (
        SELECT id, user_id
        FROM user_tenant_associations
        WHERE access_expires_at IS NULL OR access_expires_at > NOW()
    ) uta2
    WHERE uta2.user_id = uta1.user_id
    ORDER BY id
    LIMIT 1
)
AND NOT EXISTS (
    SELECT 1 FROM (
        SELECT user_id
        FROM user_tenant_associations
        WHERE is_primary = TRUE
    ) uta3
    WHERE uta3.user_id = uta1.user_id
);

-- 19. Update tenant user counts (for caching)
UPDATE tenants t
SET t.settings = JSON_SET(
    COALESCE(t.settings, '{}'),
    '$.user_count',
    (
        SELECT COUNT(DISTINCT uta.user_id)
        FROM user_tenant_associations uta
        WHERE uta.tenant_id = t.id
            AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
    ),
    '$.last_count_update',
    NOW()
)
WHERE t.deleted_at IS NULL;

-- 20. Archive old login history (keep last 90 days)
INSERT INTO login_history_archive
SELECT * FROM login_history
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

DELETE FROM login_history
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- =====================================================
-- PERFORMANCE TESTING QUERIES
-- =====================================================

-- 21. EXPLAIN plan for user authentication query
EXPLAIN SELECT
    u.*,
    COUNT(DISTINCT uta.tenant_id) as tenant_count
FROM users u
LEFT JOIN user_tenant_associations uta ON u.id = uta.user_id
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
WHERE u.email = 'test@example.com'
    AND u.deleted_at IS NULL
GROUP BY u.id;

-- 22. Index usage statistics
SELECT
    table_name,
    index_name,
    cardinality,
    seq_in_index,
    column_name,
    index_type
FROM information_schema.statistics
WHERE table_schema = 'collabora_files'
    AND table_name IN ('users', 'tenants', 'user_tenant_associations', 'sessions')
ORDER BY table_name, index_name, seq_in_index;

-- 23. Table size and row counts
SELECT
    table_name,
    table_rows,
    ROUND(data_length / 1024 / 1024, 2) as data_size_mb,
    ROUND(index_length / 1024 / 1024, 2) as index_size_mb,
    ROUND((data_length + index_length) / 1024 / 1024, 2) as total_size_mb
FROM information_schema.tables
WHERE table_schema = 'collabora_files'
    AND table_name IN ('users', 'tenants', 'user_tenant_associations', 'sessions', 'audit_logs', 'login_history')
ORDER BY total_size_mb DESC;

-- =====================================================
-- END OF SAMPLE QUERIES
-- =====================================================