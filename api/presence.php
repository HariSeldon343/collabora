<?php
/**
 * Presence API Endpoint
 * Part 4: Chat & Communication Module
 * Manages user online status and presence
 */

// Include configuration first to set session name
require_once __DIR__ . '/../config_v2.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include required files
require_once __DIR__ . '/../includes/SimpleAuth.php';
require_once __DIR__ . '/../includes/ChatManager.php';
require_once __DIR__ . '/../includes/db.php';

// Initialize authentication
$auth = new SimpleAuth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'Authentication required'
    ]);
    exit;
}

// Get current user and tenant
$current_user = $auth->getCurrentUser();
$current_tenant = $auth->getCurrentTenant();

if (!$current_tenant) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'no_tenant',
        'message' => 'No tenant selected'
    ]);
    exit;
}

// Initialize ChatManager
$chatManager = new ChatManager($current_tenant['id'], $current_user['id']);

// Handle requests based on method
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetPresence();
            break;
        case 'POST':
            handleUpdatePresence();
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'method_not_allowed',
                'message' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Presence API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'An error occurred processing your request'
    ]);
}

/**
 * Handle GET request - Get presence status
 */
function handleGetPresence() {
    global $chatManager, $current_user, $current_tenant;

    // Get parameters
    $channel_id = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : null;
    $include_self = isset($_GET['include_self']) ? filter_var($_GET['include_self'], FILTER_VALIDATE_BOOLEAN) : false;

    // If channel_id is provided, verify user is member
    if ($channel_id) {
        $membership = $chatManager->isChannelMember($channel_id, $current_user['id']);
        if (!$membership && $current_user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'not_member',
                'message' => 'You are not a member of this channel'
            ]);
            return;
        }
    }

    // Get presence data
    $presence = $chatManager->getPresence($channel_id);

    // Filter out self unless requested
    if (!$include_self) {
        $presence = array_filter($presence, function($user) use ($current_user) {
            return $user['user_id'] != $current_user['id'];
        });
        $presence = array_values($presence); // Re-index array
    }

    // Group users by status
    $grouped = [
        'online' => [],
        'busy' => [],
        'away' => [],
        'do_not_disturb' => [],
        'offline' => []
    ];

    foreach ($presence as $user) {
        $status = $user['status'] ?? 'offline';

        // Check if user is actually online based on last activity
        $last_activity = strtotime($user['last_activity']);
        if ($status !== 'offline' && (time() - $last_activity) > 300) {
            // If no activity for 5 minutes, consider offline
            $status = 'offline';
            $user['status'] = 'offline';
        }

        $grouped[$status][] = $user;
    }

    // Calculate summary statistics
    $summary = [
        'total_users' => count($presence),
        'online_count' => count($grouped['online']),
        'busy_count' => count($grouped['busy']),
        'away_count' => count($grouped['away']),
        'do_not_disturb_count' => count($grouped['do_not_disturb']),
        'offline_count' => count($grouped['offline'])
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'presence' => $presence,
            'grouped' => $grouped,
            'summary' => $summary,
            'channel_id' => $channel_id,
            'timestamp' => time()
        ],
        'message' => 'Presence data retrieved successfully'
    ]);
}

/**
 * Handle POST request - Update user presence
 */
function handleUpdatePresence() {
    global $chatManager, $current_user, $current_tenant;
    $db = getDbConnection();

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    // If JSON parsing failed, try form data
    if (!$input) {
        $input = $_POST;
    }

    // Validate status
    $valid_statuses = ['online', 'away', 'offline', 'busy', 'do_not_disturb'];
    $status = $input['status'] ?? 'online';

    if (!in_array($status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'invalid_status',
            'message' => 'Invalid status. Valid values are: ' . implode(', ', $valid_statuses)
        ]);
        return;
    }

    // Prepare presence data
    $presenceData = [
        'status' => $status,
        'status_message' => $input['status_message'] ?? null,
        'current_channel_id' => isset($input['current_channel_id']) ? (int)$input['current_channel_id'] : null
    ];

    // Add device info if provided
    if (isset($input['device_info'])) {
        $presenceData['device_info'] = $input['device_info'];
    } else {
        // Auto-detect device info
        $presenceData['device_info'] = [
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'timestamp' => time()
        ];
    }

    // Update presence
    $success = $chatManager->updatePresence($presenceData);

    // Handle typing indicator if provided
    if (isset($input['is_typing']) && isset($input['channel_id'])) {
        $channel_id = (int)$input['channel_id'];
        $is_typing = filter_var($input['is_typing'], FILTER_VALIDATE_BOOLEAN);

        if ($is_typing) {
            // Add typing indicator
            $sql = "INSERT INTO chat_typing_indicators
                    (channel_id, user_id, expires_at)
                    VALUES (:channel_id, :user_id, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 SECOND))
                    ON DUPLICATE KEY UPDATE
                    started_at = CURRENT_TIMESTAMP,
                    expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 10 SECOND)";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'channel_id' => $channel_id,
                'user_id' => $current_user['id']
            ]);
        } else {
            // Remove typing indicator
            $sql = "DELETE FROM chat_typing_indicators
                    WHERE channel_id = :channel_id AND user_id = :user_id";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'channel_id' => $channel_id,
                'user_id' => $current_user['id']
            ]);
        }
    }

    if ($success) {
        // Get updated presence for current tenant
        $presence = $chatManager->getPresence($presenceData['current_channel_id']);

        echo json_encode([
            'success' => true,
            'data' => [
                'user_status' => $status,
                'status_message' => $presenceData['status_message'],
                'current_channel_id' => $presenceData['current_channel_id'],
                'tenant_presence' => $presence,
                'timestamp' => time()
            ],
            'message' => 'Presence updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'update_failed',
            'message' => 'Failed to update presence'
        ]);
    }
}

// Clean up expired typing indicators (runs on each request)
function cleanupTypingIndicators() {
    $db = getDbConnection();
    $sql = "DELETE FROM chat_typing_indicators WHERE expires_at < CURRENT_TIMESTAMP";
    $db->exec($sql);
}

// Run cleanup
cleanupTypingIndicators();
?>