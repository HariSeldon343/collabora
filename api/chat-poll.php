<?php
/**
 * Chat Poll API Endpoint
 * Part 4: Chat & Communication Module
 * Long-polling endpoint for real-time message updates
 */

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Cache-Control: no-cache');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set execution time limit for long-polling
set_time_limit(35); // 35 seconds to account for 30-second timeout + processing

// Error reporting per debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Include required files
require_once __DIR__ . '/../config_v2.php';
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'method_not_allowed',
        'message' => 'Only GET method is allowed'
    ]);
    exit;
}

// Get parameters
$last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;
$channel_id = isset($_GET['channel_id']) ? (int)$_GET['channel_id'] : null;
$timeout = isset($_GET['timeout']) ? min((int)$_GET['timeout'], 30) : 30;
$include_presence = isset($_GET['include_presence']) ? filter_var($_GET['include_presence'], FILTER_VALIDATE_BOOLEAN) : true;

// Initialize ChatManager
$chatManager = new ChatManager($current_tenant['id'], $current_user['id']);

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
        exit;
    }
}

try {
    $start_time = time();
    $messages = [];
    $presence_updates = [];
    $unread_counts = [];

    // Long-polling loop
    while (time() - $start_time < $timeout) {
        // Check for new messages
        $messages = $chatManager->pollMessages($last_message_id, $channel_id, 1);

        if (!empty($messages)) {
            break; // Exit loop if we have messages
        }

        // Check for presence updates if requested
        if ($include_presence && (time() - $start_time) % 5 === 0) {
            // Check presence every 5 seconds
            $presence_updates = $chatManager->getPresence($channel_id);
        }

        // Sleep for 1 second before next check
        sleep(1);

        // Send keep-alive comment to prevent timeout
        if ((time() - $start_time) % 10 === 0) {
            echo " "; // Send space to keep connection alive
            ob_flush();
            flush();
        }
    }

    // Get final presence updates if we haven't already
    if ($include_presence && empty($presence_updates)) {
        $presence_updates = $chatManager->getPresence($channel_id);
    }

    // Get unread counts for the user
    $unread_counts = $chatManager->getUnreadCounts();

    // Update user's own presence to show they're active
    $chatManager->updatePresence([
        'status' => 'online',
        'current_channel_id' => $channel_id
    ]);

    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'messages' => $messages,
            'last_message_id' => !empty($messages) ? end($messages)['id'] : $last_message_id,
            'timestamp' => time(),
            'polling_duration' => time() - $start_time
        ],
        'message' => !empty($messages) ? 'New messages available' : 'No new messages'
    ];

    // Add presence updates if available
    if ($include_presence) {
        $response['data']['presence'] = $presence_updates;
    }

    // Add unread counts
    $response['data']['unread_counts'] = $unread_counts;

    // Check if there are typing indicators
    if ($channel_id) {
        $typing_users = getTypingUsers($channel_id, $current_user['id']);
        if (!empty($typing_users)) {
            $response['data']['typing_users'] = $typing_users;
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Chat Poll Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'An error occurred during polling'
    ]);
}

/**
 * Get users currently typing in a channel
 *
 * @param int $channel_id Channel ID
 * @param int $exclude_user_id User ID to exclude (current user)
 * @return array List of users currently typing
 */
function getTypingUsers($channel_id, $exclude_user_id) {
    $db = getDbConnection();

    // Clean up expired typing indicators
    $sql = "DELETE FROM chat_typing_indicators WHERE expires_at < CURRENT_TIMESTAMP";
    $db->exec($sql);

    // Get active typing users
    $sql = "
        SELECT
            t.user_id,
            u.name,
            u.email,
            u.avatar
        FROM chat_typing_indicators t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.channel_id = :channel_id
        AND t.user_id != :exclude_user_id
        AND t.expires_at > CURRENT_TIMESTAMP
        ORDER BY t.started_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        'channel_id' => $channel_id,
        'exclude_user_id' => $exclude_user_id
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>