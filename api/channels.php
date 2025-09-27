<?php
/**
 * Channels API Endpoint
 * Part 4: Chat & Communication Module
 * Manages chat channels and membership
 */

// Include configuration first to set session name
require_once __DIR__ . '/../config_v2.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error reporting per debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require_once __DIR__ . '/../includes/autoload.php';
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
            handleGetChannels();
            break;
        case 'POST':
            handleCreateChannel();
            break;
        case 'PUT':
            handleUpdateChannel();
            break;
        case 'DELETE':
            handleDeleteChannel();
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
    error_log("Channels API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'An error occurred processing your request'
    ]);
}

/**
 * Handle GET request - List channels
 */
function handleGetChannels() {
    global $chatManager, $current_user, $current_tenant;
    $db = getDbConnection();

    // Get parameters
    $channel_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $type = isset($_GET['type']) ? $_GET['type'] : null;
    $archived = isset($_GET['archived']) ? filter_var($_GET['archived'], FILTER_VALIDATE_BOOLEAN) : false;
    $include_members = isset($_GET['include_members']) ? filter_var($_GET['include_members'], FILTER_VALIDATE_BOOLEAN) : false;

    // If specific channel requested
    if ($channel_id) {
        $channel = $chatManager->getChannel($channel_id);

        if (!$channel) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'channel_not_found',
                'message' => 'Channel not found'
            ]);
            return;
        }

        // Check if user is member or admin
        $membership = $chatManager->isChannelMember($channel_id, $current_user['id']);
        if (!$membership && $current_user['role'] !== 'admin' && $channel['type'] !== 'public') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'not_member',
                'message' => 'You do not have access to this channel'
            ]);
            return;
        }

        // Get channel members if requested
        if ($include_members) {
            $sql = "SELECT cm.*, u.name, u.email, u.avatar
                    FROM chat_channel_members cm
                    LEFT JOIN users u ON cm.user_id = u.id
                    WHERE cm.channel_id = :channel_id
                    ORDER BY cm.role ASC, u.name ASC";

            $stmt = $db->prepare($sql);
            $stmt->execute(['channel_id' => $channel_id]);
            $channel['members'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Get unread count for user
        $sql = "SELECT unread_count, unread_mentions
                FROM message_reads
                WHERE user_id = :user_id AND channel_id = :channel_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'user_id' => $current_user['id'],
            'channel_id' => $channel_id
        ]);

        $unread = $stmt->fetch(PDO::FETCH_ASSOC);
        $channel['unread_count'] = $unread ? $unread['unread_count'] : 0;
        $channel['unread_mentions'] = $unread ? $unread['unread_mentions'] : 0;

        echo json_encode([
            'success' => true,
            'data' => $channel,
            'message' => 'Channel retrieved successfully'
        ]);
        return;
    }

    // List all channels user has access to
    $filters = [
        'user_id' => $current_user['id'],
        'archived' => $archived
    ];

    if ($type) {
        $filters['type'] = $type;
    }

    $channels = $chatManager->getChannels($filters);

    // Add unread counts for each channel
    foreach ($channels as &$channel) {
        $sql = "SELECT unread_count, unread_mentions
                FROM message_reads
                WHERE user_id = :user_id AND channel_id = :channel_id";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'user_id' => $current_user['id'],
            'channel_id' => $channel['id']
        ]);

        $unread = $stmt->fetch(PDO::FETCH_ASSOC);
        $channel['unread_count'] = $unread ? $unread['unread_count'] : 0;
        $channel['unread_mentions'] = $unread ? $unread['unread_mentions'] : 0;

        // Get user's membership info
        $membership = $chatManager->isChannelMember($channel['id'], $current_user['id']);
        $channel['user_role'] = $membership ? $membership['role'] : null;
        $channel['notification_preference'] = $membership ? $membership['notification_preference'] : null;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'channels' => $channels,
            'total' => count($channels)
        ],
        'message' => 'Channels retrieved successfully'
    ]);
}

/**
 * Handle POST request - Create new channel
 */
function handleCreateChannel() {
    global $chatManager, $current_user, $current_tenant;

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    // If JSON parsing failed, try form data
    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (empty($input['name']) && $input['type'] !== 'direct') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_name',
            'message' => 'Channel name is required for non-direct channels'
        ]);
        return;
    }

    // Validate channel type
    $valid_types = ['public', 'private', 'direct'];
    $type = $input['type'] ?? 'public';

    if (!in_array($type, $valid_types)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'invalid_type',
            'message' => 'Invalid channel type. Valid values are: ' . implode(', ', $valid_types)
        ]);
        return;
    }

    // For direct messages, validate members
    if ($type === 'direct') {
        if (empty($input['members']) || !is_array($input['members'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'missing_members',
                'message' => 'Direct channels require exactly one other member'
            ]);
            return;
        }

        // Direct messages should have exactly 2 members (including current user)
        if (count($input['members']) !== 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'invalid_members',
                'message' => 'Direct channels must have exactly one other member'
            ]);
            return;
        }

        // Check if direct channel already exists between these users
        $other_user_id = $input['members'][0];
        $existing = checkExistingDirectChannel($current_user['id'], $other_user_id, $current_tenant['id']);

        if ($existing) {
            echo json_encode([
                'success' => true,
                'data' => $existing,
                'message' => 'Direct channel already exists'
            ]);
            return;
        }
    }

    // Prepare channel data
    $channelData = [
        'name' => $input['name'] ?? null,
        'type' => $type,
        'description' => $input['description'] ?? null,
        'members' => $input['members'] ?? []
    ];

    // Add metadata if provided
    if (!empty($input['metadata'])) {
        $channelData['metadata'] = $input['metadata'];
    }

    // Create the channel
    $channel = $chatManager->createChannel($channelData);

    if ($channel) {
        // Send system message announcing channel creation
        if ($type !== 'direct') {
            $chatManager->sendMessage([
                'channel_id' => $channel['id'],
                'content' => "Channel '{$channel['name']}' was created by {$current_user['name']}",
                'message_type' => 'system'
            ]);
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $channel,
            'message' => 'Channel created successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'creation_failed',
            'message' => 'Failed to create channel'
        ]);
    }
}

/**
 * Handle PUT request - Update channel
 */
function handleUpdateChannel() {
    global $chatManager, $current_user, $current_tenant;
    $db = getDbConnection();

    // Get channel ID from URL or input
    $channel_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    // If JSON parsing failed, try form data
    if (!$input) {
        parse_str(file_get_contents('php://input'), $input);
    }

    if (!$channel_id && isset($input['id'])) {
        $channel_id = (int)$input['id'];
    }

    if (!$channel_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_channel_id',
            'message' => 'Channel ID is required'
        ]);
        return;
    }

    // Get channel
    $channel = $chatManager->getChannel($channel_id);

    if (!$channel) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'channel_not_found',
            'message' => 'Channel not found'
        ]);
        return;
    }

    // Check if user has permission to update (must be owner or admin)
    $membership = $chatManager->isChannelMember($channel_id, $current_user['id']);

    if (!$membership || ($membership['role'] !== 'owner' && $membership['role'] !== 'admin' && $current_user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'permission_denied',
            'message' => 'You do not have permission to update this channel'
        ]);
        return;
    }

    // Prepare update data
    $updates = [];
    $params = ['channel_id' => $channel_id];

    if (isset($input['name'])) {
        $updates[] = "name = :name";
        $params['name'] = $input['name'];
    }

    if (isset($input['description'])) {
        $updates[] = "description = :description";
        $params['description'] = $input['description'];
    }

    if (isset($input['is_archived'])) {
        $updates[] = "is_archived = :is_archived";
        $params['is_archived'] = $input['is_archived'] ? 1 : 0;
    }

    if (isset($input['metadata'])) {
        $updates[] = "metadata = :metadata";
        $params['metadata'] = json_encode($input['metadata']);
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'no_updates',
            'message' => 'No fields to update'
        ]);
        return;
    }

    // Update the channel
    $sql = "UPDATE chat_channels SET " . implode(', ', $updates) . " WHERE id = :channel_id";

    $stmt = $db->prepare($sql);
    $success = $stmt->execute($params);

    if ($success) {
        // Get updated channel
        $channel = $chatManager->getChannel($channel_id);

        // Send system message about update
        $chatManager->sendMessage([
            'channel_id' => $channel_id,
            'content' => "Channel settings were updated by {$current_user['name']}",
            'message_type' => 'system'
        ]);

        echo json_encode([
            'success' => true,
            'data' => $channel,
            'message' => 'Channel updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'update_failed',
            'message' => 'Failed to update channel'
        ]);
    }
}

/**
 * Handle DELETE request - Delete/Archive channel
 */
function handleDeleteChannel() {
    global $chatManager, $current_user, $current_tenant;
    $db = getDbConnection();

    // Get channel ID
    $channel_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if (!$channel_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_channel_id',
            'message' => 'Channel ID is required'
        ]);
        return;
    }

    // Get channel
    $channel = $chatManager->getChannel($channel_id);

    if (!$channel) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'channel_not_found',
            'message' => 'Channel not found'
        ]);
        return;
    }

    // Check if user has permission to delete (must be owner or system admin)
    $membership = $chatManager->isChannelMember($channel_id, $current_user['id']);

    if (!$membership || ($membership['role'] !== 'owner' && $current_user['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'permission_denied',
            'message' => 'You do not have permission to delete this channel'
        ]);
        return;
    }

    // Soft delete (archive) the channel
    $sql = "UPDATE chat_channels
            SET is_archived = 1, updated_at = CURRENT_TIMESTAMP
            WHERE id = :channel_id";

    $stmt = $db->prepare($sql);
    $success = $stmt->execute(['channel_id' => $channel_id]);

    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Channel archived successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'delete_failed',
            'message' => 'Failed to archive channel'
        ]);
    }
}

/**
 * Check if direct channel already exists between two users
 *
 * @param int $user1_id First user ID
 * @param int $user2_id Second user ID
 * @param int $tenant_id Tenant ID
 * @return array|false Existing channel or false
 */
function checkExistingDirectChannel($user1_id, $user2_id, $tenant_id) {
    $db = getDbConnection();

    $sql = "
        SELECT c.*
        FROM chat_channels c
        WHERE c.tenant_id = :tenant_id
        AND c.type = 'direct'
        AND EXISTS (
            SELECT 1 FROM chat_channel_members
            WHERE channel_id = c.id AND user_id = :user1_id
        )
        AND EXISTS (
            SELECT 1 FROM chat_channel_members
            WHERE channel_id = c.id AND user_id = :user2_id
        )
        AND c.is_archived = 0
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'tenant_id' => $tenant_id,
        'user1_id' => $user1_id,
        'user2_id' => $user2_id
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>