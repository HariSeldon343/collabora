<?php
/**
 * Reactions API Endpoint
 * Part 4: Chat & Communication Module
 * Manages emoji reactions on messages
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include required files
require_once '../includes/SimpleAuth.php';
require_once '../includes/ChatManager.php';
require_once '../includes/db.php';

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
            handleGetReactions();
            break;
        case 'POST':
            handleAddReaction();
            break;
        case 'DELETE':
            handleRemoveReaction();
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
    error_log("Reactions API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'An error occurred processing your request'
    ]);
}

/**
 * Handle GET request - Get reactions for a message
 */
function handleGetReactions() {
    global $chatManager, $current_user, $current_tenant;
    $db = getDbConnection();

    // Get message ID
    $message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : null;

    if (!$message_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_message_id',
            'message' => 'Message ID is required'
        ]);
        return;
    }

    // Verify message exists and user has access
    $message = verifyMessageAccess($message_id);

    if (!$message) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'message_not_found',
            'message' => 'Message not found or access denied'
        ]);
        return;
    }

    // Get all reactions for the message
    $sql = "
        SELECT
            r.emoji,
            COUNT(*) as count,
            GROUP_CONCAT(u.id SEPARATOR ',') as user_ids,
            GROUP_CONCAT(u.name SEPARATOR ', ') as user_names,
            MAX(CASE WHEN r.user_id = :current_user_id THEN 1 ELSE 0 END) as user_reacted
        FROM message_reactions r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.message_id = :message_id
        GROUP BY r.emoji
        ORDER BY count DESC, r.emoji ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'message_id' => $message_id,
        'current_user_id' => $current_user['id']
    ]);

    $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process the results
    foreach ($reactions as &$reaction) {
        $reaction['user_ids'] = explode(',', $reaction['user_ids']);
        $reaction['user_reacted'] = (bool)$reaction['user_reacted'];
    }

    // Get detailed user list for each reaction if requested
    if (isset($_GET['include_users']) && filter_var($_GET['include_users'], FILTER_VALIDATE_BOOLEAN)) {
        foreach ($reactions as &$reaction) {
            $sql = "
                SELECT u.id, u.name, u.email, u.avatar, r.created_at
                FROM message_reactions r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.message_id = :message_id AND r.emoji = :emoji
                ORDER BY r.created_at ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'message_id' => $message_id,
                'emoji' => $reaction['emoji']
            ]);

            $reaction['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'message_id' => $message_id,
            'reactions' => $reactions,
            'total_reactions' => array_sum(array_column($reactions, 'count'))
        ],
        'message' => 'Reactions retrieved successfully'
    ]);
}

/**
 * Handle POST request - Add reaction to message
 */
function handleAddReaction() {
    global $chatManager, $current_user, $current_tenant;

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    // If JSON parsing failed, try form data
    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (empty($input['message_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_message_id',
            'message' => 'Message ID is required'
        ]);
        return;
    }

    if (empty($input['emoji'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_emoji',
            'message' => 'Emoji is required'
        ]);
        return;
    }

    $message_id = (int)$input['message_id'];
    $emoji = $input['emoji'];

    // Validate emoji (basic validation - ensure it's not too long)
    if (mb_strlen($emoji) > 10) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'invalid_emoji',
            'message' => 'Invalid emoji format'
        ]);
        return;
    }

    // Verify message exists and user has access
    $message = verifyMessageAccess($message_id);

    if (!$message) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'message_not_found',
            'message' => 'Message not found or access denied'
        ]);
        return;
    }

    // Add the reaction
    $success = $chatManager->addReaction($message_id, $emoji);

    if ($success) {
        // Get updated reaction count
        $db = getDbConnection();
        $sql = "SELECT COUNT(*) as count FROM message_reactions
                WHERE message_id = :message_id AND emoji = :emoji";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'message_id' => $message_id,
            'emoji' => $emoji
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'message_id' => $message_id,
                'emoji' => $emoji,
                'count' => $result['count'],
                'user_reacted' => true
            ],
            'message' => 'Reaction added successfully'
        ]);
    } else {
        // Reaction might already exist
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'reaction_exists',
            'message' => 'You have already reacted with this emoji'
        ]);
    }
}

/**
 * Handle DELETE request - Remove reaction from message
 */
function handleRemoveReaction() {
    global $chatManager, $current_user, $current_tenant;

    // Get parameters
    $message_id = isset($_GET['message_id']) ? (int)$_GET['message_id'] : null;
    $emoji = isset($_GET['emoji']) ? $_GET['emoji'] : null;

    // Try to get from request body if not in URL
    if (!$message_id || !$emoji) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $message_id = $message_id ?: (isset($input['message_id']) ? (int)$input['message_id'] : null);
            $emoji = $emoji ?: ($input['emoji'] ?? null);
        }
    }

    if (!$message_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_message_id',
            'message' => 'Message ID is required'
        ]);
        return;
    }

    if (!$emoji) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_emoji',
            'message' => 'Emoji is required'
        ]);
        return;
    }

    // Verify message exists and user has access
    $message = verifyMessageAccess($message_id);

    if (!$message) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'message_not_found',
            'message' => 'Message not found or access denied'
        ]);
        return;
    }

    // Remove the reaction
    $success = $chatManager->removeReaction($message_id, $emoji);

    if ($success) {
        // Get updated reaction count
        $db = getDbConnection();
        $sql = "SELECT COUNT(*) as count FROM message_reactions
                WHERE message_id = :message_id AND emoji = :emoji";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'message_id' => $message_id,
            'emoji' => $emoji
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'message_id' => $message_id,
                'emoji' => $emoji,
                'count' => $result['count'],
                'user_reacted' => false
            ],
            'message' => 'Reaction removed successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'reaction_not_found',
            'message' => 'Reaction not found'
        ]);
    }
}

/**
 * Verify that a message exists and user has access to it
 *
 * @param int $message_id Message ID
 * @return array|false Message data or false if not found/accessible
 */
function verifyMessageAccess($message_id) {
    global $chatManager, $current_user, $current_tenant;
    $db = getDbConnection();

    // Get message with channel info
    $sql = "
        SELECT m.*, c.tenant_id, c.type as channel_type
        FROM chat_messages m
        JOIN chat_channels c ON m.channel_id = c.id
        WHERE m.id = :message_id
        AND c.tenant_id = :tenant_id
        AND m.deleted_at IS NULL
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'message_id' => $message_id,
        'tenant_id' => $current_tenant['id']
    ]);

    $message = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$message) {
        return false;
    }

    // Check if user has access to the channel
    if ($message['channel_type'] !== 'public') {
        $membership = $chatManager->isChannelMember($message['channel_id'], $current_user['id']);
        if (!$membership && $current_user['role'] !== 'admin') {
            return false;
        }
    }

    return $message;
}

/**
 * Get list of commonly used emojis
 */
function getCommonEmojis() {
    return [
        '👍' => 'thumbs_up',
        '❤️' => 'heart',
        '😂' => 'joy',
        '🎉' => 'tada',
        '👏' => 'clap',
        '🔥' => 'fire',
        '👀' => 'eyes',
        '✅' => 'check',
        '💯' => '100',
        '🚀' => 'rocket',
        '😊' => 'smile',
        '🤔' => 'thinking',
        '👎' => 'thumbs_down',
        '😢' => 'cry',
        '😮' => 'surprised'
    ];
}

// If requested, return list of common emojis
if (isset($_GET['list_emojis']) && filter_var($_GET['list_emojis'], FILTER_VALIDATE_BOOLEAN)) {
    echo json_encode([
        'success' => true,
        'data' => getCommonEmojis(),
        'message' => 'Common emojis list'
    ]);
    exit;
}
?>