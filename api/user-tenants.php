<?php
/**
 * User Tenants API - List accessible tenants for authenticated user
 *
 * This endpoint returns all tenants accessible to the current user based on their role:
 * - admin: All active tenants
 * - special_user: Assigned tenants from associations
 * - standard_user: Single assigned tenant
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include authentication
require_once __DIR__ . '/../includes/SimpleAuth.php';

try {
    // Only GET method allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'method_not_allowed',
                'message' => 'Only GET method is allowed',
                'fields' => []
            ]
        ]);
        exit;
    }

    $auth = new SimpleAuth();

    // Check authentication
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'unauthorized',
                'message' => 'Authentication required',
                'fields' => []
            ]
        ]);
        exit;
    }

    // Get available tenants
    $tenants = $auth->getAvailableTenants();
    $currentTenant = $auth->getCurrentTenant();
    $user = $auth->getCurrentUser();

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'tenants' => $tenants,
            'current_tenant' => $currentTenant,
            'total' => count($tenants),
            'user' => [
                'id' => $user['id'] ?? null,
                'email' => $user['email'] ?? null,
                'role' => $user['role'] ?? null,
                'can_switch' => in_array($user['role'] ?? '', ['admin', 'special_user'])
            ]
        ]
    ];

    // Add additional metadata
    if (count($tenants) === 1) {
        $response['data']['auto_selected'] = true;
        $response['data']['message'] = 'Single tenant auto-selected';
    } else if (count($tenants) > 1) {
        $response['data']['auto_selected'] = false;
        $response['data']['message'] = 'Multiple tenants available for selection';
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log('[user-tenants.php] Error: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'server_error',
            'message' => 'Internal server error',
            'fields' => []
        ]
    ]);
}