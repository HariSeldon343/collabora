# Part 3: Tenant-Less Login Flow - Database Architecture

## Executive Summary

This document details the database architecture supporting the tenant-less login flow for Nexio Solution V2. The system allows users to authenticate with just email and password, automatically detecting their tenant associations and handling multi-tenant switching seamlessly.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Core Schema Components](#core-schema-components)
3. [Authentication Flow](#authentication-flow)
4. [Role-Based Access Control](#role-based-access-control)
5. [Performance Optimizations](#performance-optimizations)
6. [Sample Queries](#sample-queries)
7. [Migration Guide](#migration-guide)
8. [Testing Scenarios](#testing-scenarios)

## Architecture Overview

### Key Design Principles

1. **No Tenant Code at Login**: Users authenticate with email/password only
2. **Automatic Tenant Detection**: System determines accessible tenants based on associations
3. **Role-Based Behavior**:
   - `admin`: Full access to all tenants without explicit associations
   - `special_user`: Can access multiple assigned tenants
   - `standard_user`: Limited to single tenant access
4. **Default Tenant Selection**: Smart selection based on last access or primary flag
5. **Session-Based Switching**: Efficient tenant switching without re-authentication

### Database Schema Highlights

```
users (no tenant_id required)
  ├── email (unique login identifier)
  ├── password (bcrypt/argon2id hashed)
  ├── role (admin/special_user/standard_user)
  └── last_active_tenant_id (remembers last selection)

user_tenant_associations (many-to-many)
  ├── user_id
  ├── tenant_id
  ├── is_primary (default tenant flag)
  ├── tenant_role (owner/admin/manager/member/viewer)
  └── access_expires_at (temporary access support)

tenants
  ├── code (unique identifier)
  ├── name (display name)
  ├── status (active/suspended/archived)
  └── settings (JSON configuration)
```

## Core Schema Components

### 1. Users Table

The `users` table has been designed to work independently of tenants:

```sql
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'special_user', 'standard_user') NOT NULL DEFAULT 'standard_user',
  `last_active_tenant_id` BIGINT UNSIGNED DEFAULT NULL,
  -- ... other fields
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_last_active_tenant` (`last_active_tenant_id`, `status`)
);
```

**Key Points:**
- No `tenant_id` field in users table
- `last_active_tenant_id` tracks user's last selected tenant
- Role determines multi-tenant capabilities

### 2. User-Tenant Associations

The `user_tenant_associations` table manages the many-to-many relationship:

```sql
CREATE TABLE `user_tenant_associations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `is_primary` BOOLEAN DEFAULT FALSE,
  `tenant_role` ENUM('owner', 'admin', 'manager', 'member', 'viewer') DEFAULT 'member',
  `access_expires_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tenant` (`user_id`, `tenant_id`),
  KEY `idx_uta_user_active` (`user_id`, `access_expires_at`, `tenant_id`)
);
```

**Key Features:**
- Supports multiple tenants per user (except standard_user)
- Per-tenant roles for granular permissions
- Temporary access via `access_expires_at`
- Primary tenant marking for default selection

### 3. Additional Tables (Part 3 Migration)

#### Tenant Preferences
Stores user-specific settings per tenant:

```sql
CREATE TABLE `tenant_preferences` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `preference_key` VARCHAR(100) NOT NULL,
  `preference_value` JSON DEFAULT NULL,
  UNIQUE KEY `uk_tenant_prefs` (`user_id`, `tenant_id`, `preference_key`)
);
```

#### Login History
Comprehensive audit trail for security:

```sql
CREATE TABLE `login_history` (
  `user_id` BIGINT UNSIGNED,
  `email` VARCHAR(255) NOT NULL,
  `login_status` ENUM('success', 'failed', 'blocked'),
  `selected_tenant_id` BIGINT UNSIGNED,
  `ip_address` VARCHAR(45),
  -- Partitioned by year for performance
) PARTITION BY RANGE (YEAR(created_at));
```

## Authentication Flow

### Step-by-Step Process

1. **User Login**
   ```sql
   -- Validate credentials
   SELECT id, password, role, status
   FROM users
   WHERE email = ? AND deleted_at IS NULL;
   ```

2. **Detect Available Tenants**
   ```sql
   -- For admin users (all tenants)
   SELECT * FROM tenants WHERE status = 'active';

   -- For other users (associated tenants only)
   SELECT t.*, uta.tenant_role, uta.is_primary
   FROM user_tenant_associations uta
   JOIN tenants t ON uta.tenant_id = t.id
   WHERE uta.user_id = ?
     AND t.status = 'active'
     AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW());
   ```

3. **Auto-Select Default Tenant**
   ```sql
   -- Priority: Last active > Primary > First available
   CALL sp_get_default_tenant(user_id, user_role, @tenant_id, @tenant_code, @tenant_name);
   ```

4. **Create Session**
   ```sql
   -- Log successful login
   CALL sp_log_successful_login(user_id, email, tenant_id, ip_address, user_agent, session_id);
   ```

## Role-Based Access Control

### Role Capabilities Matrix

| Role | Multiple Tenants | Tenant Association Required | Can Delete | Can Switch Tenants |
|------|-----------------|----------------------------|------------|-------------------|
| admin | Yes (All) | No | Yes | Yes |
| special_user | Yes (Assigned) | Yes | No | Yes |
| standard_user | No (Single) | Yes | No | No |

### Enforcement Mechanisms

1. **Database Triggers**
   - `trg_validate_standard_user_single_tenant`: Prevents multiple associations for standard users
   - `trg_validate_user_login_check`: Ensures valid tenant association before login
   - `trg_prevent_special_user_delete_*`: Blocks deletion operations by special users

2. **Stored Procedures**
   - `sp_validate_user_tenant_access`: Verifies user can access specific tenant
   - `sp_get_user_available_tenants`: Returns accessible tenants based on role
   - `fn_needs_tenant_selection`: Determines if tenant selection UI is needed

## Performance Optimizations

### Strategic Indexes

```sql
-- User-tenant lookup optimization
CREATE INDEX idx_uta_user_active ON user_tenant_associations(
  user_id, access_expires_at, tenant_id
) WHERE access_expires_at IS NULL OR access_expires_at > NOW();

-- Active tenant filtering
CREATE INDEX idx_tenants_active ON tenants(
  status, deleted_at, subscription_expires_at
) WHERE status = 'active' AND deleted_at IS NULL;

-- Login history partitioning
PARTITION BY RANGE (YEAR(created_at));
```

### Query Optimization Patterns

1. **Use Covering Indexes**: Include all needed columns in index
2. **Minimize JOIN Operations**: Pre-calculate common aggregations
3. **Leverage Stored Procedures**: Reduce round-trips for complex operations
4. **Implement Caching**: Store frequently accessed tenant lists in session

## Sample Queries

### 1. Complete Authentication Flow

```php
// PHP implementation example
function authenticateUser($email, $password) {
    // Step 1: Validate credentials
    $stmt = $pdo->prepare("CALL sp_authenticate_user(?, @user_id, @role, @status)");
    $stmt->execute([$email]);

    $result = $pdo->query("SELECT @user_id, @role, @status")->fetch();

    if (!password_verify($password, $hashedPassword)) {
        return false;
    }

    // Step 2: Get available tenants
    $stmt = $pdo->prepare("CALL sp_get_user_available_tenants(?, ?)");
    $stmt->execute([$result['user_id'], $result['role']]);
    $tenants = $stmt->fetchAll();

    // Step 3: Auto-select or show selection
    if (count($tenants) == 1) {
        $selectedTenant = $tenants[0];
    } else {
        // Show tenant selection UI
        $selectedTenant = showTenantSelection($tenants);
    }

    // Step 4: Log and create session
    $stmt = $pdo->prepare("CALL sp_log_successful_login(?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $result['user_id'],
        $email,
        $selectedTenant['id'],
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        session_id()
    ]);

    return [
        'user_id' => $result['user_id'],
        'role' => $result['role'],
        'tenant' => $selectedTenant
    ];
}
```

### 2. Switch Tenant (Post-Login)

```sql
-- Validate access and switch
CALL sp_switch_tenant(@user_id, @new_tenant_id);

-- Update session
UPDATE sessions
SET tenant_id = @new_tenant_id
WHERE user_id = @user_id AND id = @session_id;
```

### 3. Get User's Tenant List

```sql
-- For tenant switcher UI
SELECT
    t.id,
    t.code,
    t.name,
    t.settings->>'$.theme' as theme,
    uta.tenant_role,
    uta.is_primary,
    (t.id = u.last_active_tenant_id) as is_current
FROM user_tenant_associations uta
JOIN tenants t ON uta.tenant_id = t.id
JOIN users u ON uta.user_id = u.id
WHERE uta.user_id = ?
    AND t.status = 'active'
    AND (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
ORDER BY uta.is_primary DESC, t.name;
```

### 4. Check Login Eligibility

```sql
-- Single query to determine if user can login
SELECT
    can_login,
    accessible_tenant_count,
    CASE
        WHEN status != 'active' THEN 'Account inactive'
        WHEN locked_until > NOW() THEN 'Account locked'
        WHEN accessible_tenant_count = 0 THEN 'No tenant access'
        ELSE 'OK'
    END as login_status
FROM v_user_login_eligibility
WHERE email = ?;
```

### 5. Admin Dashboard Query

```sql
-- Get system-wide statistics
SELECT
    (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL) as total_users,
    (SELECT COUNT(*) FROM tenants WHERE status = 'active') as active_tenants,
    (SELECT COUNT(*) FROM user_tenant_associations
     WHERE access_expires_at IS NULL OR access_expires_at > NOW()) as active_associations,
    (SELECT COUNT(*) FROM login_history
     WHERE DATE(created_at) = CURDATE() AND login_status = 'success') as logins_today;
```

## Migration Guide

### Running the Migration

1. **Prerequisites**
   - Ensure `schema_v2.sql` has been applied
   - Backup existing database

2. **Apply Migration**
   ```bash
   mysql -u root -p collabora_files < database/migrations_part3_tenant.sql
   ```

3. **Verify Migration**
   ```sql
   -- Check new tables exist
   SHOW TABLES LIKE '%preferences%';
   SHOW TABLES LIKE '%login_history%';

   -- Verify stored procedures
   SHOW PROCEDURE STATUS WHERE Db = 'collabora_files';

   -- Check indexes
   SHOW INDEX FROM user_tenant_associations;
   ```

### Data Migration Checklist

- [x] All users have primary tenant set if they have associations
- [x] Admin users have last_active_tenant_id populated
- [x] Standard users limited to single association
- [x] Login history table partitioned for performance
- [x] Cleanup events scheduled for old data

## Testing Scenarios

### Scenario 1: Admin Login
```php
// Test: Admin with no associations can access all tenants
$admin = authenticateUser('asamodeo@fortibyte.it', 'Ricord@1991');
assert($admin['role'] === 'admin');
assert(count($admin['available_tenants']) > 0);
```

### Scenario 2: Special User Multi-Tenant
```php
// Test: Special user can switch between assigned tenants
$special = authenticateUser('special@demo.com', 'Ricord@1991');
assert($special['role'] === 'special_user');
assert(count($special['available_tenants']) > 1);
```

### Scenario 3: Standard User Single Tenant
```php
// Test: Standard user auto-selects single tenant
$standard = authenticateUser('standard@demo.com', 'Ricord@1991');
assert($standard['role'] === 'standard_user');
assert(count($standard['available_tenants']) === 1);
assert($standard['auto_selected'] === true);
```

### Scenario 4: No Tenant Access
```php
// Test: User with no active tenants cannot login
$orphan = authenticateUser('orphan@test.com', 'password');
assert($orphan === false);
assert($error['code'] === 'no_tenant_access');
```

### Scenario 5: Tenant Switching
```php
// Test: User can switch to different tenant
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['current_tenant'] = 1;

$result = switchTenant($_SESSION['user_id'], 2);
assert($result === true);
assert($_SESSION['current_tenant'] === 2);
```

## Performance Benchmarks

### Expected Query Performance

| Operation | Expected Time | Optimization |
|-----------|--------------|--------------|
| User authentication | < 50ms | Indexed email lookup |
| Get user tenants | < 20ms | Covering index on associations |
| Switch tenant | < 30ms | Stored procedure |
| Check eligibility | < 10ms | Materialized view |
| Login history insert | < 5ms | Partitioned table |

### Scaling Considerations

1. **Partition Tables**: Login history, audit logs by date
2. **Archive Old Data**: Move old records to archive tables
3. **Cache Tenant Lists**: Store in Redis/Memcached for frequent access
4. **Read Replicas**: Use for tenant selection queries
5. **Connection Pooling**: Optimize database connections

## Security Considerations

### Authentication Security

1. **Password Hashing**: BCrypt with cost factor 10+ or Argon2id
2. **Failed Login Tracking**: Lock account after 5 failed attempts
3. **Session Security**: HTTPOnly, Secure, SameSite cookies
4. **Audit Logging**: All login attempts logged with IP
5. **Token Expiration**: Sessions expire after inactivity

### Data Isolation

1. **Row-Level Security**: All queries filtered by tenant_id
2. **Trigger Validation**: Database-level enforcement
3. **Prepared Statements**: Prevent SQL injection
4. **Permission Checks**: Verify access before operations

## Troubleshooting

### Common Issues

1. **"No tenant access" error**
   - Check user_tenant_associations table
   - Verify tenant status is 'active'
   - Check access_expires_at date

2. **Cannot switch tenants**
   - Verify user role allows multiple tenants
   - Check tenant associations exist
   - Validate session is active

3. **Last tenant not remembered**
   - Ensure last_active_tenant_id is set
   - Check if tenant is still active
   - Verify user still has access

4. **Performance degradation**
   - Run ANALYZE TABLE on key tables
   - Check index usage with EXPLAIN
   - Review slow query log

## Backend Implementation Updates

### Authentication System Changes (Completed 2025-01-20)

The backend authentication system has been fully updated to support tenant-less login:

#### 1. SimpleAuth.php Updates
- **Removed tenant code requirement** from login method
- **Role-based tenant fetching**:
  - `admin`: Fetches all active tenants without association requirement
  - `special_user`: Fetches assigned tenants from user_tenant_associations
  - `standard_user`: Fetches single tenant with auto-select
- **Smart tenant selection** with priority: last_active > primary > first
- **Session management** stores available_tenants for quick access
- **New methods added**:
  - `getAvailableTenants()`: Returns all accessible tenants for current user
  - `getCurrentTenant()`: Returns details of currently selected tenant
  - `switchTenant()`: Enhanced with role checks and last_active_tenant_id update

#### 2. API Endpoints

##### auth_simple.php Enhanced
```php
// Login without tenant code
POST /api/auth_simple.php
{
    "action": "login",
    "email": "user@example.com",
    "password": "password123"
}

// Response includes tenant information
{
    "success": true,
    "user": {...},
    "tenants": [...],
    "current_tenant_id": 1,
    "auto_selected": false,
    "needs_tenant_selection": true
}

// Get available tenants
POST /api/auth_simple.php
{
    "action": "get_tenants"
}

// Switch tenant
POST /api/auth_simple.php
{
    "action": "switch_tenant",
    "tenant_id": 2
}
```

##### New Dedicated Endpoints

**GET /api/user-tenants.php**
- Lists all accessible tenants for authenticated user
- Returns current tenant and switch capability
- No parameters required

**POST /api/switch-tenant.php**
- Changes active tenant for multi-tenant users
- Updates last_active_tenant_id in database
- Body: `{"tenant_id": 2}`

#### 3. Session Management Updates

**session_helper.php Enhanced**:
- `getCurrentTenantId()`: Gets current tenant from session
- `setCurrentTenantId()`: Updates tenant with history tracking
- `getAvailableTenants()`: Returns cached tenant list
- `canSwitchTenant()`: Checks if user role allows switching
- `hasTenantAccess()`: Validates user access to specific tenant

#### 4. Multi-Tenant Data Access

All APIs now use session-based tenant filtering:
- **CalendarManager**: Initialized with `$_SESSION['current_tenant_id']`
- **TaskManager**: Initialized with `$_SESSION['current_tenant_id']`
- **FileManager**: Uses session tenant for all operations
- Admin override capability preserved for system operations

### Testing the Implementation

#### Test Admin Login (All Tenants)
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'
```

#### Test Get Available Tenants
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"action":"get_tenants"}'
```

#### Test Switch Tenant
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/switch-tenant.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"tenant_id":2}'
```

### Frontend Integration Guide

The frontend should implement this flow:

1. **Login Page**: Only show email/password fields (no tenant dropdown)
2. **Post-Login**:
   - Check `needs_tenant_selection` in login response
   - If true: Show tenant selector dialog
   - If false: Proceed directly to dashboard
3. **Tenant Switcher Component**:
   - Only visible for admin/special_user roles
   - Call `/api/user-tenants.php` to get list
   - Call `/api/switch-tenant.php` to change
   - Refresh page/data after switch

### Security Considerations

1. **Role Enforcement**: Database and application level checks
2. **Session Security**: HTTPOnly cookies with SameSite=Lax
3. **Tenant Isolation**: All queries filtered by current_tenant_id
4. **Audit Trail**: Login history tracks tenant selections
5. **Access Expiration**: Temporary tenant access supported

## Conclusion

The Part 3 implementation successfully delivers a tenant-less login system that:

1. ✅ Simplifies user experience by removing tenant code requirement
2. ✅ Maintains security through role-based access control
3. ✅ Optimizes performance with strategic indexing and stored procedures
4. ✅ Provides flexibility for multi-tenant scenarios
5. ✅ Ensures backward compatibility with existing data
6. ✅ Implements complete backend support with tested APIs
7. ✅ Provides session-based tenant management across all modules

The migration is non-destructive and includes all necessary data transformations to ensure a smooth transition from the previous authentication system. The backend is fully ready for frontend integration.