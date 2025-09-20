<?php
/**
 * Simple Test for Tenant-Less Login
 * Direct API test without cURL
 */

// Include required files
require_once __DIR__ . '/includes/SimpleAuth.php';

// Initialize
echo "=== Tenant-Less Login Test ===\n\n";

try {
    $auth = new SimpleAuth();

    echo "1. Testing Admin Login (without tenant code)\n";
    echo "   Email: asamodeo@fortibyte.it\n";

    $result = $auth->login('asamodeo@fortibyte.it', 'Ricord@1991');

    if ($result['success']) {
        echo "   ✓ Login successful!\n";
        echo "   - User ID: " . $result['user']['id'] . "\n";
        echo "   - Role: " . $result['user']['role'] . "\n";
        echo "   - Is Admin: " . ($result['user']['is_admin'] ? 'Yes' : 'No') . "\n";
        echo "   - Tenants found: " . count($result['tenants']) . "\n";

        foreach ($result['tenants'] as $tenant) {
            echo "     • " . $tenant['name'] . " (ID: " . $tenant['id'] . ")\n";
        }

        echo "   - Current tenant ID: " . $result['current_tenant_id'] . "\n";
        echo "   - Auto-selected: " . ($result['auto_selected'] ? 'Yes' : 'No') . "\n";
        echo "   - Needs selection: " . ($result['needs_tenant_selection'] ? 'Yes' : 'No') . "\n";

        echo "\n2. Testing Get Available Tenants\n";
        $tenants = $auth->getAvailableTenants();
        echo "   ✓ Found " . count($tenants) . " accessible tenant(s)\n";

        if (count($tenants) > 1) {
            echo "\n3. Testing Tenant Switch\n";
            $targetId = $tenants[1]['id'] ?? $tenants[0]['id'];
            echo "   Switching to tenant ID: $targetId\n";

            try {
                $switchResult = $auth->switchTenant($targetId);
                echo "   ✓ Switch successful: " . $switchResult['message'] . "\n";
                echo "   - New tenant: " . $switchResult['tenant']['name'] . "\n";
            } catch (Exception $e) {
                echo "   ✗ Switch failed: " . $e->getMessage() . "\n";
            }
        }

        echo "\n4. Testing Current Tenant\n";
        $currentTenant = $auth->getCurrentTenant();
        if ($currentTenant) {
            echo "   ✓ Current tenant: " . $currentTenant['name'] . " (ID: " . $currentTenant['id'] . ")\n";
        }

    } else {
        echo "   ✗ Login failed\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Type: " . get_class($e) . "\n";

    // Check database connection
    echo "\nChecking database connection...\n";
    try {
        require_once __DIR__ . '/config_v2.php';
        echo "  DB Host: " . (defined('DB_HOST') ? DB_HOST : 'not defined') . "\n";
        echo "  DB Name: " . (defined('DB_NAME') ? DB_NAME : 'not defined') . "\n";
        echo "  DB User: " . (defined('DB_USER') ? DB_USER : 'not defined') . "\n";

        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS
        );
        echo "  ✓ Database connection successful\n";

        // Check tables
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->fetch()) {
            echo "  ✓ Users table exists\n";
        } else {
            echo "  ✗ Users table not found\n";
        }

        $stmt = $pdo->query("SHOW TABLES LIKE 'tenants'");
        if ($stmt->fetch()) {
            echo "  ✓ Tenants table exists\n";
        } else {
            echo "  ✗ Tenants table not found\n";
        }

        $stmt = $pdo->query("SHOW TABLES LIKE 'user_tenant_associations'");
        if ($stmt->fetch()) {
            echo "  ✓ User-tenant associations table exists\n";
        } else {
            echo "  ✗ User-tenant associations table not found\n";
        }

    } catch (PDOException $dbError) {
        echo "  ✗ Database error: " . $dbError->getMessage() . "\n";
    }
}

echo "\n=== Test Complete ===\n";