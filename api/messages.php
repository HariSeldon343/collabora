<?php
/**
 * Messages API Endpoint
 * Part 4: Chat & Communication Module
 * Handles message listing and sending
 */

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

// Include required files
require_once __DIR__ . '/../config_v2.php';
require_once __DIR__ . '/../includes/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SimpleAuth.php';
require_once __DIR__ . '/../includes/ChatManager.php';

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
    // Check for action parameter
    $action = $_GET['action'] ?? null;

    // Handle get_last_id action
    if ($action === 'get_last_id') {
        handleGetLastMessageId();
    } else {
        switch ($method) {
            case 'GET':
                handleGetMessages();
                break;
            case 'POST':
                handleSendMessage();
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
    }
} catch (Exception $e) {
    error_log("Messages API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'An error occurred processing your request'
    ]);
}

/**
 * Handle GET request - List messages for a channel
 */
function handleGetMessages() {
    global $chatManager, $current_user;

    // Get parameters
    $channel_id = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : null;
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $parent_message_id = isset($_GET['parent_message_id']) ? (int)$_GET['parent_message_id'] : null;

    // Validate channel_id
    if (!$channel_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_channel_id',
            'message' => 'Channel ID is required'
        ]);
        return;
    }

    // Verify user is member of channel
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

    // Get messages
    $messages = $chatManager->getMessages($channel_id, $limit, $offset, $parent_message_id);

    // Mark channel as read if fetching main messages (not threads)
    if (!$parent_message_id && !empty($messages)) {
        $chatManager->markAsRead($channel_id, $messages[0]['id']);
    }

    // Return messages in reverse order (oldest first for display)
    $messages = array_reverse($messages);

    echo json_encode([
        'success' => true,
        'data' => [
            'messages' => $messages,
            'channel_id' => $channel_id,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => count($messages) === $limit
        ],
        'message' => 'Messages retrieved successfully'
    ]);
}

/**
 * Handle POST request - Send a new message
 */
function handleSendMessage() {
    global $chatManager, $current_user;

    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);

    // If JSON parsing failed, try form data
    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    if (empty($input['channel_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_channel_id',
            'message' => 'Channel ID is required'
        ]);
        return;
    }

    if (empty($input['content']) && empty($input['attachment_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'missing_content',
            'message' => 'Message content or attachment is required'
        ]);
        return;
    }

    $channel_id = (int)$input['channel_id'];

    // Verify user is member of channel
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

    // Check if user is muted
    if ($membership && $membership['muted_until']) {
        $muted_until = strtotime($membership['muted_until']);
        if ($muted_until > time()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'user_muted',
                'message' => 'You are muted in this channel until ' . date('Y-m-d H:i:s', $muted_until)
            ]);
            return;
        }
    }

    // Prepare message data
    $messageData = [
        'channel_id' => $channel_id,
        'content' => $input['content'] ?? '',
        'parent_message_id' => isset($input['parent_message_id']) ? (int)$input['parent_message_id'] : null,
        'attachment_id' => isset($input['attachment_id']) ? (int)$input['attachment_id'] : null,
        'message_type' => $input['message_type'] ?? 'text'
    ];

    // Add metadata if provided
    if (!empty($input['metadata'])) {
        $messageData['metadata'] = $input['metadata'];
    }

    // Send the message
    $message = $chatManager->sendMessage($messageData);

    if ($message) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'data' => $message,
            'message' => 'Message sent successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'send_failed',
            'message' => 'Failed to send message'
        ]);
    }
}

/**
 * Handle get_last_id action - Get the last message ID for a channel or globally
 */
function handleGetLastMessageId() {
    global $chatManager, $current_user;

    // Get parameters
    $channel_id = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : null;

    try {
        $db = getDbConnection();

        // If channel_id is provided, get last message ID for that channel
        if ($channel_id) {
            // Verify user is member of channel
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

            $stmt = $db->prepare("
                SELECT MAX(id) as last_id
                FROM chat_messages
                WHERE channel_id = :channel_id
            ");
            $stmt->execute(['channel_id' => $channel_id]);
        } else {
            // Get last message ID for all channels user has access to
            $stmt = $db->prepare("
                SELECT MAX(cm.id) as last_id
                FROM chat_messages cm
                INNER JOIN chat_channel_members ccm ON cm.channel_id = ccm.channel_id
                WHERE ccm.user_id = :user_id
            ");
            $stmt->execute(['user_id' => $current_user['id']]);
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastId = $result['last_id'] ?? 0;

        echo json_encode([
            'success' => true,
            'data' => [
                'last_message_id' => (int)$lastId,
                'channel_id' => $channel_id
            ],
            'message' => 'Last message ID retrieved successfully'
        ]);

    } catch (Exception $e) {
        error_log("Get Last Message ID Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'server_error',
            'message' => 'Failed to get last message ID'
        ]);
    }
}
?>