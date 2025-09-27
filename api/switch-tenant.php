<?php
/**
 * Switch Tenant API - Change the current active tenant
 *
 * This endpoint allows users to switch between accessible tenants:
 * - admin: Can switch to any active tenant
 * - special_user: Can switch between assigned tenants
 * - standard_user: Cannot switch (will return error)
 */

// Include configuration first to set session name
require_once __DIR__ . '/../config_v2.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Include authentication
require_once __DIR__ . '/../includes/SimpleAuth.php';

// Safe logging function
function safeLog($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . ' [switch-tenant] ' . $message;
    if ($data !== null) {
        $logEntry .= ' | Data: ' . json_encode($data);
    }
    error_log($logEntry);
}

try {
    // Only POST method allowed
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'method_not_allowed',
                'message' => 'Only POST method is allowed',
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

    // Parse request body
    $rawInput = file_get_contents('php://input');
    $data = [];

    if (!empty($rawInput)) {
        $data = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try form-encoded format
            parse_str($rawInput, $data);
        }
    }

    // Fallback to POST data
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }

    // Get tenant_id from request
    $tenantId = $data['tenant_id'] ?? null;

    if (empty($tenantId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'missing_field',
                'message' => 'tenant_id is required',
                'fields' => ['tenant_id']
            ]
        ]);
        exit;
    }

    // Validate tenant_id is numeric
    if (!is_numeric($tenantId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'invalid_field',
                'message' => 'tenant_id must be a number',
                'fields' => ['tenant_id']
            ]
        ]);
        exit;
    }

    $user = $auth->getCurrentUser();
    safeLog('Switch tenant request', [
        'user_id' => $user['id'] ?? null,
        'user_role' => $user['role'] ?? null,
        'target_tenant_id' => $tenantId
    ]);

    // Attempt to switch tenant
    try {
        $result = $auth->switchTenant((int)$tenantId);

        // Get updated tenant information
        $newTenant = $auth->getCurrentTenant();
        $availableTenants = $auth->getAvailableTenants();

        // Success response
        $response = [
            'success' => true,
            'message' => $result['message'] ?? 'Tenant switched successfully',
            'data' => [
                'current_tenant' => $newTenant,
                'previous_tenant_id' => $_SESSION['previous_tenant_id'] ?? null,
                'available_tenants' => $availableTenants
            ]
        ];

        safeLog('Tenant switch successful', [
            'user_id' => $user['id'],
            'new_tenant_id' => $tenantId
        ]);

        echo json_encode($response);

    } catch (AuthException $e) {
        // Handle specific auth errors
        $errorCode = 403;
        $errorType = 'forbidden';

        if (strpos($e->getMessage(), 'standard') !== false) {
            $errorType = 'role_restriction';
        } else if (strpos($e->getMessage(), 'non autorizzato') !== false) {
            $errorType = 'access_denied';
        } else if (strpos($e->getMessage(), 'non valido') !== false) {
            $errorCode = 400;
            $errorType = 'invalid_tenant';
        }

        safeLog('Tenant switch failed', [
            'user_id' => $user['id'],
            'error' => $e->getMessage()
        ]);

        http_response_code($errorCode);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $errorType,
                'message' => $e->getMessage(),
                'fields' => []
            ]
        ]);
    }

} catch (Exception $e) {
    safeLog('Unexpected error', ['error' => $e->getMessage()]);

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