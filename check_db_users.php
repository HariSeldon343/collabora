<?php
/**
 * Check database users and tenants
 */

require_once __DIR__ . '/config_v2.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
        DB_USER,
        DB_PASS
    );

    echo "=== Database Check ===\n\n";

    // Check if last_active_tenant_id column exists
    echo "1. Checking users table structure:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Columns: " . implode(', ', $columns) . "\n";

    $hasLastActiveTenant = in_array('last_active_tenant_id', $columns);
    echo "   Has last_active_tenant_id: " . ($hasLastActiveTenant ? 'Yes' : 'No') . "\n\n";

    // Check users
    echo "2. Users in database:\n";
    $stmt = $pdo->query("SELECT id, email, role, is_system_admin, status FROM users WHERE deleted_at IS NULL OR deleted_at = ''");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {
        echo "   - ID: {$user['id']}, Email: {$user['email']}, Role: {$user['role']}, Admin: {$user['is_system_admin']}, Status: {$user['status']}\n";
    }

    if (empty($users)) {
        echo "   No users found!\n";
    }

    // Check tenants
    echo "\n3. Tenants in database:\n";
    $stmt = $pdo->query("SELECT id, code, name, status FROM tenants WHERE deleted_at IS NULL OR deleted_at = ''");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tenants as $tenant) {
        echo "   - ID: {$tenant['id']}, Code: {$tenant['code']}, Name: {$tenant['name']}, Status: {$tenant['status']}\n";
    }

    if (empty($tenants)) {
        echo "   No tenants found!\n";
    }

    // Check user-tenant associations
    echo "\n4. User-Tenant Associations:\n";
    $stmt = $pdo->query("
        SELECT uta.user_id, u.email, uta.tenant_id, t.name as tenant_name, uta.is_primary, uta.tenant_role
        FROM user_tenant_associations uta
        JOIN users u ON uta.user_id = u.id
        JOIN tenants t ON uta.tenant_id = t.id
        WHERE (uta.access_expires_at IS NULL OR uta.access_expires_at > NOW())
        ORDER BY uta.user_id, uta.tenant_id
    ");
    $associations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($associations as $assoc) {
        echo "   - User: {$assoc['email']} → Tenant: {$assoc['tenant_name']}, Primary: {$assoc['is_primary']}, Role: {$assoc['tenant_role']}\n";
    }

    if (empty($associations)) {
        echo "   No associations found!\n";
    }

    // If last_active_tenant_id doesn't exist, add it
    if (!$hasLastActiveTenant) {
        echo "\n5. Adding last_active_tenant_id column to users table...\n";
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN last_active_tenant_id BIGINT UNSIGNED DEFAULT NULL");
            echo "   ✓ Column added successfully\n";
        } catch (PDOException $e) {
            echo "   ✗ Error adding column: " . $e->getMessage() . "\n";
        }
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";