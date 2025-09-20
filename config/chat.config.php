<?php
/**
 * NEXIOSOLUTION - CHAT MODULE CONFIGURATION
 * Part 4: Chat & Communication System Settings
 *
 * This file contains all configuration parameters for the chat module.
 * Adjust these values based on your server capabilities and requirements.
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(dirname(__FILE__)));
}

// ========================================
// LONG-POLLING CONFIGURATION
// ========================================

/**
 * Maximum time in seconds for long-polling connections
 * Recommended: 30-60 seconds
 * Note: Must be less than Apache KeepAliveTimeout
 */
define('CHAT_POLL_TIMEOUT', 45);

/**
 * Polling interval in seconds
 * How often the server checks for new messages during long-polling
 */
define('CHAT_POLL_INTERVAL', 1);

/**
 * Maximum number of retries for failed polling connections
 */
define('CHAT_POLL_MAX_RETRIES', 3);

// ========================================
// MESSAGE CONFIGURATION
// ========================================

/**
 * Maximum message length in characters
 * Prevents abuse and ensures UI consistency
 */
define('CHAT_MAX_MESSAGE_LENGTH', 5000);

/**
 * Maximum number of messages to retrieve per request
 */
define('CHAT_MESSAGES_PER_PAGE', 50);

/**
 * Maximum number of messages to store per channel
 * Older messages are archived or deleted
 */
define('CHAT_MAX_MESSAGES_PER_CHANNEL', 10000);

/**
 * Message types supported
 */
define('CHAT_MESSAGE_TYPES', [
    'text',
    'file',
    'image',
    'system',
    'notification'
]);

/**
 * Enable message editing
 * Users can edit their own messages within time limit
 */
define('CHAT_ALLOW_MESSAGE_EDIT', true);

/**
 * Time limit for message editing (in minutes)
 * 0 = no time limit
 */
define('CHAT_EDIT_TIME_LIMIT', 15);

/**
 * Enable message deletion
 * Users can delete their own messages
 */
define('CHAT_ALLOW_MESSAGE_DELETE', true);

// ========================================
// FILE UPLOAD CONFIGURATION
// ========================================

/**
 * Maximum file size for uploads (in bytes)
 * Default: 10MB
 */
define('CHAT_MAX_FILE_SIZE', 10 * 1024 * 1024);

/**
 * Allowed file extensions for uploads
 * Add or remove extensions based on security requirements
 */
define('CHAT_ALLOWED_EXTENSIONS', [
    // Documents
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt',
    // Images
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp',
    // Archives
    'zip', 'rar', '7z', 'tar', 'gz',
    // Media
    'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv',
    // Code
    'html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'h', 'json', 'xml'
]);

/**
 * Upload directory path (relative to project root)
 */
define('CHAT_UPLOAD_DIR', 'uploads/chat');

/**
 * Enable automatic image thumbnail generation
 */
define('CHAT_GENERATE_THUMBNAILS', true);

/**
 * Thumbnail dimensions (width x height in pixels)
 */
define('CHAT_THUMBNAIL_WIDTH', 200);
define('CHAT_THUMBNAIL_HEIGHT', 200);

// ========================================
// PRESENCE & STATUS CONFIGURATION
// ========================================

/**
 * User presence timeout in seconds
 * User is considered offline after this period of inactivity
 */
define('CHAT_PRESENCE_TIMEOUT', 120);

/**
 * Presence update interval in seconds
 * How often client sends heartbeat to server
 */
define('CHAT_PRESENCE_UPDATE_INTERVAL', 30);

/**
 * Available user status options
 */
define('CHAT_USER_STATUSES', [
    'online' => ['label' => 'Online', 'color' => '#10B981'],
    'away' => ['label' => 'Away', 'color' => '#F59E0B'],
    'busy' => ['label' => 'Busy', 'color' => '#EF4444'],
    'offline' => ['label' => 'Offline', 'color' => '#6B7280']
]);

/**
 * Enable typing indicators
 */
define('CHAT_TYPING_INDICATORS', true);

/**
 * Typing indicator timeout in seconds
 */
define('CHAT_TYPING_TIMEOUT', 5);

// ========================================
// NOTIFICATION CONFIGURATION
// ========================================

/**
 * Enable desktop notifications
 */
define('CHAT_DESKTOP_NOTIFICATIONS', true);

/**
 * Enable sound notifications
 */
define('CHAT_SOUND_NOTIFICATIONS', true);

/**
 * Default notification sound file
 */
define('CHAT_NOTIFICATION_SOUND', 'assets/sounds/notification.mp3');

/**
 * Enable email notifications for offline users
 */
define('CHAT_EMAIL_NOTIFICATIONS', false);

/**
 * Email notification delay in minutes
 * Send email if user is offline for this duration
 */
define('CHAT_EMAIL_NOTIFICATION_DELAY', 5);

// ========================================
// RATE LIMITING CONFIGURATION
// ========================================

/**
 * Enable rate limiting to prevent spam
 */
define('CHAT_RATE_LIMITING', true);

/**
 * Maximum messages per minute per user
 */
define('CHAT_RATE_LIMIT', 30);

/**
 * Rate limit window in seconds
 */
define('CHAT_RATE_LIMIT_WINDOW', 60);

/**
 * Cooldown period in seconds after hitting rate limit
 */
define('CHAT_RATE_LIMIT_COOLDOWN', 60);

// ========================================
// CHANNEL CONFIGURATION
// ========================================

/**
 * Maximum number of channels a user can create
 */
define('CHAT_MAX_CHANNELS_PER_USER', 50);

/**
 * Maximum number of members per channel
 */
define('CHAT_MAX_MEMBERS_PER_CHANNEL', 100);

/**
 * Channel types available
 */
define('CHAT_CHANNEL_TYPES', [
    'public' => 'Public Channel',
    'private' => 'Private Channel',
    'direct' => 'Direct Message',
    'group' => 'Group Message'
]);

/**
 * Default channel type for new channels
 */
define('CHAT_DEFAULT_CHANNEL_TYPE', 'private');

/**
 * Enable channel archiving
 */
define('CHAT_ENABLE_CHANNEL_ARCHIVE', true);

// ========================================
// SEARCH CONFIGURATION
// ========================================

/**
 * Enable message search functionality
 */
define('CHAT_ENABLE_SEARCH', true);

/**
 * Minimum search query length
 */
define('CHAT_MIN_SEARCH_LENGTH', 3);

/**
 * Maximum search results to return
 */
define('CHAT_MAX_SEARCH_RESULTS', 100);

/**
 * Enable full-text search (requires MySQL FULLTEXT index)
 */
define('CHAT_FULLTEXT_SEARCH', true);

// ========================================
// MODERATION CONFIGURATION
// ========================================

/**
 * Enable content moderation
 */
define('CHAT_ENABLE_MODERATION', false);

/**
 * Blocked words list (basic profanity filter)
 */
define('CHAT_BLOCKED_WORDS', []);

/**
 * Enable user reporting
 */
define('CHAT_ENABLE_REPORTING', true);

/**
 * Maximum reports before auto-flagging
 */
define('CHAT_MAX_REPORTS_THRESHOLD', 3);

// ========================================
// PERFORMANCE CONFIGURATION
// ========================================

/**
 * Enable message caching
 */
define('CHAT_ENABLE_CACHE', true);

/**
 * Cache TTL in seconds
 */
define('CHAT_CACHE_TTL', 300);

/**
 * Database connection pooling
 */
define('CHAT_DB_POOLING', false);

/**
 * Maximum database connections
 */
define('CHAT_MAX_DB_CONNECTIONS', 10);

/**
 * Enable message compression for large messages
 */
define('CHAT_ENABLE_COMPRESSION', false);

/**
 * Compression threshold in bytes
 */
define('CHAT_COMPRESSION_THRESHOLD', 1024);

// ========================================
// SECURITY CONFIGURATION
// ========================================

/**
 * Enable end-to-end encryption (requires additional setup)
 */
define('CHAT_E2E_ENCRYPTION', false);

/**
 * Message encryption algorithm
 */
define('CHAT_ENCRYPTION_ALGORITHM', 'AES-256-GCM');

/**
 * Enable CSRF protection
 */
define('CHAT_CSRF_PROTECTION', true);

/**
 * Session timeout for chat (in minutes)
 */
define('CHAT_SESSION_TIMEOUT', 60);

/**
 * Enable IP-based access control
 */
define('CHAT_IP_RESTRICTION', false);

/**
 * Allowed IP ranges (if IP restriction is enabled)
 */
define('CHAT_ALLOWED_IPS', []);

// ========================================
// LOGGING CONFIGURATION
// ========================================

/**
 * Enable chat logging
 */
define('CHAT_ENABLE_LOGGING', true);

/**
 * Log file path
 */
define('CHAT_LOG_PATH', BASE_PATH . '/logs/chat/');

/**
 * Log level (debug, info, warning, error)
 */
define('CHAT_LOG_LEVEL', 'info');

/**
 * Maximum log file size in MB
 */
define('CHAT_MAX_LOG_SIZE', 10);

/**
 * Log retention period in days
 */
define('CHAT_LOG_RETENTION_DAYS', 30);

// ========================================
// EMOJI & REACTIONS CONFIGURATION
// ========================================

/**
 * Enable emoji support
 */
define('CHAT_ENABLE_EMOJI', true);

/**
 * Enable message reactions
 */
define('CHAT_ENABLE_REACTIONS', true);

/**
 * Available reaction emojis
 */
define('CHAT_AVAILABLE_REACTIONS', [
    '👍', '👎', '❤️', '😂', '😮', '😢', '🎉', '🔥'
]);

/**
 * Maximum reactions per message
 */
define('CHAT_MAX_REACTIONS_PER_MESSAGE', 10);

// ========================================
// INTEGRATION CONFIGURATION
// ========================================

/**
 * Enable webhook integrations
 */
define('CHAT_ENABLE_WEBHOOKS', false);

/**
 * Webhook endpoint URL
 */
define('CHAT_WEBHOOK_URL', '');

/**
 * Enable REST API
 */
define('CHAT_ENABLE_API', true);

/**
 * API rate limit (requests per minute)
 */
define('CHAT_API_RATE_LIMIT', 60);

// ========================================
// DEVELOPMENT/DEBUG CONFIGURATION
// ========================================

/**
 * Enable debug mode
 * WARNING: Disable in production!
 */
define('CHAT_DEBUG_MODE', false);

/**
 * Show SQL queries in debug mode
 */
define('CHAT_DEBUG_SQL', false);

/**
 * Enable performance profiling
 */
define('CHAT_PROFILING', false);

/**
 * Test mode (uses test database/data)
 */
define('CHAT_TEST_MODE', false);

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Get chat configuration value
 *
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function getChatConfig($key, $default = null) {
    if (defined($key)) {
        return constant($key);
    }
    return $default;
}

/**
 * Validate file extension for upload
 *
 * @param string $filename Filename to check
 * @return bool True if allowed, false otherwise
 */
function isAllowedFileType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, CHAT_ALLOWED_EXTENSIONS);
}

/**
 * Format file size for display
 *
 * @param int $bytes File size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Log chat event
 *
 * @param string $level Log level
 * @param string $message Log message
 * @param array $context Additional context
 */
function logChatEvent($level, $message, $context = []) {
    if (!CHAT_ENABLE_LOGGING) {
        return;
    }

    $logFile = CHAT_LOG_PATH . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = sprintf(
        "[%s] [%s] %s %s\n",
        $timestamp,
        strtoupper($level),
        $message,
        !empty($context) ? json_encode($context) : ''
    );

    // Create log directory if not exists
    if (!is_dir(CHAT_LOG_PATH)) {
        mkdir(CHAT_LOG_PATH, 0777, true);
    }

    // Write to log file
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // Check log file size and rotate if needed
    if (file_exists($logFile) && filesize($logFile) > CHAT_MAX_LOG_SIZE * 1024 * 1024) {
        rename($logFile, $logFile . '.' . time());
    }
}

// ========================================
// INITIALIZATION
// ========================================

// Create required directories if they don't exist
$requiredDirs = [
    BASE_PATH . '/' . CHAT_UPLOAD_DIR,
    CHAT_LOG_PATH,
    BASE_PATH . '/temp/chat'
];

foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Log configuration loaded
if (CHAT_DEBUG_MODE) {
    logChatEvent('debug', 'Chat configuration loaded', [
        'poll_timeout' => CHAT_POLL_TIMEOUT,
        'max_message_length' => CHAT_MAX_MESSAGE_LENGTH,
        'rate_limit' => CHAT_RATE_LIMIT
    ]);
}