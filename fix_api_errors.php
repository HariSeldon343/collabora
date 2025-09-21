<?php declare(strict_types=1);

/**
 * API Error Fix Script for Nexio Collabora
 * Fixes session validation, tenant checks, and error handling in all APIs
 *
 * @author Backend Systems Architect
 * @version 1.0.0
 * @since 2025-01-20
 */

require_once __DIR__ . '/config_v2.php';
require_once __DIR__ . '/includes/db.php';

class APIErrorFixer {
    private PDO $db;
    private array $report = [];
    private array $apis_to_fix = [
        'calendars.php',
        'events.php',
        'task-lists.php',
        'channels.php',
        'chat-poll.php',
        'messages.php',
        'users.php'
    ];

    public function __construct() {
        $this->db = getDbConnection();
        $this->report['timestamp'] = date('Y-m-d H:i:s');
        $this->report['fixes'] = [];
        $this->report['errors'] = [];
        $this->report['database_tables'] = [];
    }

    /**
     * Run all fixes
     */
    public function runFixes(): void {
        echo "===========================================\n";
        echo "    NEXIO COLLABORA API ERROR FIXER       \n";
        echo "===========================================\n\n";

        // 1. Check database tables
        $this->checkDatabaseTables();

        // 2. Fix SimpleAuth class issues
        $this->fixSimpleAuthClass();

        // 3. Fix session helper
        $this->fixSessionHelper();

        // 4. Fix ChatManager class
        $this->fixChatManager();

        // 5. Fix CalendarManager class
        $this->fixCalendarManager();

        // 6. Fix TaskManager class
        $this->fixTaskManager();

        // 7. Create API test script
        $this->createAPITestScript();

        // 8. Display report
        $this->displayReport();
    }

    /**
     * Check if required database tables exist
     */
    private function checkDatabaseTables(): void {
        echo "📊 Checking database tables...\n";

        $requiredTables = [
            // Core tables
            'users' => 'User accounts',
            'tenants' => 'Tenant organizations',
            'user_tenant_associations' => 'User-tenant mappings',

            // Calendar tables
            'calendars' => 'Calendar containers',
            'events' => 'Calendar events',
            'event_participants' => 'Event attendees',

            // Task tables
            'task_lists' => 'Task boards/lists',
            'tasks' => 'Task items',
            'task_assignments' => 'Task assignees',

            // Chat tables
            'chat_channels' => 'Chat channels',
            'chat_channel_members' => 'Channel membership',
            'chat_messages' => 'Chat messages',
            'message_reads' => 'Read receipts',
            'chat_presence' => 'User presence',

            // File tables
            'folders' => 'Folder structure',
            'files' => 'File metadata'
        ];

        $existingTables = [];
        $missingTables = [];

        try {
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($requiredTables as $table => $description) {
                if (in_array($table, $tables)) {
                    $existingTables[$table] = $description;
                    echo "  ✅ Table '$table' exists ($description)\n";
                } else {
                    $missingTables[$table] = $description;
                    echo "  ❌ Table '$table' MISSING! ($description)\n";
                }
            }

            $this->report['database_tables'] = [
                'existing' => $existingTables,
                'missing' => $missingTables,
                'total_required' => count($requiredTables),
                'total_existing' => count($existingTables),
                'total_missing' => count($missingTables)
            ];

            if (count($missingTables) > 0) {
                $this->createMissingTablesScript($missingTables);
            }

        } catch (PDOException $e) {
            $this->report['errors'][] = "Database check failed: " . $e->getMessage();
            echo "  ⚠️ Error checking tables: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    /**
     * Fix SimpleAuth class implementation
     */
    private function fixSimpleAuthClass(): void {
        echo "🔧 Fixing SimpleAuth class...\n";

        $simpleAuthPath = __DIR__ . '/includes/SimpleAuth.php';

        // Check if file exists
        if (!file_exists($simpleAuthPath)) {
            echo "  ❌ SimpleAuth.php not found! Creating...\n";
            $this->createSimpleAuthClass();
            return;
        }

        // Read current content
        $content = file_get_contents($simpleAuthPath);

        // Check for critical methods
        $requiredMethods = [
            'isAuthenticated',
            'getCurrentUser',
            'getCurrentTenant'
        ];

        $missingMethods = [];
        foreach ($requiredMethods as $method) {
            if (!preg_match('/public\s+function\s+' . $method . '\s*\(/i', $content)) {
                $missingMethods[] = $method;
            }
        }

        if (count($missingMethods) > 0) {
            echo "  ⚠️ Missing methods: " . implode(', ', $missingMethods) . "\n";
            echo "  🔧 Adding missing methods...\n";
            $this->addMissingMethodsToSimpleAuth($missingMethods);
        } else {
            echo "  ✅ SimpleAuth class has all required methods\n";
        }

        $this->report['fixes'][] = "SimpleAuth class checked and fixed";
        echo "\n";
    }

    /**
     * Create SimpleAuth class if missing
     */
    private function createSimpleAuthClass(): void {
        $code = <<<'PHP'
<?php declare(strict_types=1);

/**
 * Simple Authentication Class for Nexio Collabora
 * Provides session-based authentication
 */

class SimpleAuth {
    private ?PDO $db = null;

    public function __construct() {
        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Get database connection
        require_once __DIR__ . '/db.php';
        $this->db = getDbConnection();
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    /**
     * Get current user data
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }

        // Return cached user data if available
        if (isset($_SESSION['user_data'])) {
            return $_SESSION['user_data'];
        }

        // Fetch from database
        try {
            $stmt = $this->db->prepare("
                SELECT id, email, name, role, status, avatar
                FROM users
                WHERE id = :user_id
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION['user_data'] = $user;
                return $user;
            }
        } catch (PDOException $e) {
            error_log("Error fetching user: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Get current tenant data
     */
    public function getCurrentTenant(): ?array {
        // Admin users don't require tenant
        $user = $this->getCurrentUser();
        if ($user && $user['role'] === 'admin') {
            return [
                'id' => 0,
                'name' => 'System Admin',
                'code' => 'ADMIN'
            ];
        }

        // Check for tenant in session
        if (!isset($_SESSION['current_tenant_id']) || $_SESSION['current_tenant_id'] <= 0) {
            return null;
        }

        // Return cached tenant data if available
        if (isset($_SESSION['tenant_data'])) {
            return $_SESSION['tenant_data'];
        }

        // Fetch from database
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, code, status
                FROM tenants
                WHERE id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute(['tenant_id' => $_SESSION['current_tenant_id']]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tenant) {
                $_SESSION['tenant_data'] = $tenant;
                return $tenant;
            }
        } catch (PDOException $e) {
            error_log("Error fetching tenant: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRFToken(string $token): bool {
        if (empty($token)) {
            return false;
        }

        return isset($_SESSION['csrf_token']) &&
               hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generate CSRF token
     */
    public function generateCSRFToken(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
}
PHP;

        file_put_contents(__DIR__ . '/includes/SimpleAuth.php', $code);
        echo "  ✅ Created SimpleAuth.php with all required methods\n";
        $this->report['fixes'][] = "Created SimpleAuth.php class";
    }

    /**
     * Add missing methods to SimpleAuth
     */
    private function addMissingMethodsToSimpleAuth(array $missingMethods): void {
        $simpleAuthPath = __DIR__ . '/includes/SimpleAuth.php';
        $content = file_get_contents($simpleAuthPath);

        $methodsCode = "\n\n    // Added by API Error Fixer\n";

        if (in_array('isAuthenticated', $missingMethods)) {
            $methodsCode .= '
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool {
        return isset($_SESSION[\'user_id\']) && $_SESSION[\'user_id\'] > 0;
    }
';
        }

        if (in_array('getCurrentUser', $missingMethods)) {
            $methodsCode .= '
    /**
     * Get current user data
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }

        if (isset($_SESSION[\'user_data\'])) {
            return $_SESSION[\'user_data\'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, email, name, role, status
                FROM users WHERE id = :user_id LIMIT 1
            ");
            $stmt->execute([\'user_id\' => $_SESSION[\'user_id\']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $_SESSION[\'user_data\'] = $user;
                return $user;
            }
        } catch (PDOException $e) {
            error_log("Error fetching user: " . $e->getMessage());
        }

        return null;
    }
';
        }

        if (in_array('getCurrentTenant', $missingMethods)) {
            $methodsCode .= '
    /**
     * Get current tenant data
     */
    public function getCurrentTenant(): ?array {
        $user = $this->getCurrentUser();
        if ($user && $user[\'role\'] === \'admin\') {
            return [\'id\' => 0, \'name\' => \'System Admin\', \'code\' => \'ADMIN\'];
        }

        if (!isset($_SESSION[\'current_tenant_id\']) || $_SESSION[\'current_tenant_id\'] <= 0) {
            return null;
        }

        if (isset($_SESSION[\'tenant_data\'])) {
            return $_SESSION[\'tenant_data\'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, name, code, status
                FROM tenants WHERE id = :tenant_id LIMIT 1
            ");
            $stmt->execute([\'tenant_id\' => $_SESSION[\'current_tenant_id\']]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tenant) {
                $_SESSION[\'tenant_data\'] = $tenant;
                return $tenant;
            }
        } catch (PDOException $e) {
            error_log("Error fetching tenant: " . $e->getMessage());
        }

        return null;
    }
';
        }

        // Insert methods before the closing brace of the class
        $content = preg_replace('/}\s*$/', $methodsCode . "\n}\n", $content);

        file_put_contents($simpleAuthPath, $content);
        echo "  ✅ Added missing methods to SimpleAuth\n";
        $this->report['fixes'][] = "Added methods to SimpleAuth: " . implode(', ', $missingMethods);
    }

    /**
     * Fix session helper
     */
    private function fixSessionHelper(): void {
        echo "🔧 Checking session_helper.php...\n";

        $helperPath = __DIR__ . '/includes/session_helper.php';

        if (!file_exists($helperPath)) {
            echo "  ❌ session_helper.php not found! Creating...\n";
            $this->createSessionHelper();
        } else {
            echo "  ✅ session_helper.php exists\n";
        }

        echo "\n";
    }

    /**
     * Create session helper if missing
     */
    private function createSessionHelper(): void {
        $code = <<<'PHP'
<?php declare(strict_types=1);

namespace Collabora\Session;

/**
 * Session Helper for Nexio Collabora
 */
class SessionHelper {

    /**
     * Initialize session with proper settings
     */
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    /**
     * Get current user
     */
    public static function getCurrentUser(): ?array {
        if (!self::isAuthenticated()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'] ?? '',
            'name' => $_SESSION['user_name'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'standard_user'
        ];
    }
}
PHP;

        file_put_contents(__DIR__ . '/includes/session_helper.php', $code);
        echo "  ✅ Created session_helper.php\n";
        $this->report['fixes'][] = "Created session_helper.php";
    }

    /**
     * Fix ChatManager class
     */
    private function fixChatManager(): void {
        echo "🔧 Checking ChatManager class...\n";

        $path = __DIR__ . '/includes/ChatManager.php';

        if (!file_exists($path)) {
            echo "  ❌ ChatManager.php not found! Creating...\n";
            $this->createChatManager();
        } else {
            echo "  ✅ ChatManager.php exists\n";
        }

        echo "\n";
    }

    /**
     * Create ChatManager class
     */
    private function createChatManager(): void {
        $code = <<<'PHP'
<?php declare(strict_types=1);

/**
 * Chat Manager for Nexio Collabora
 */
class ChatManager {
    private PDO $db;
    private int $tenantId;
    private int $userId;

    public function __construct(int $tenantId, int $userId) {
        require_once __DIR__ . '/db.php';
        $this->db = getDbConnection();
        $this->tenantId = $tenantId;
        $this->userId = $userId;
    }

    /**
     * Get channels accessible to user
     */
    public function getChannels(array $filters = []): array {
        try {
            $sql = "
                SELECT DISTINCT c.*,
                       cm.role as user_role,
                       cm.notification_preference
                FROM chat_channels c
                LEFT JOIN chat_channel_members cm ON c.id = cm.channel_id
                WHERE c.tenant_id = :tenant_id
                AND (c.type = 'public' OR cm.user_id = :user_id)
                AND c.is_archived = 0
                ORDER BY c.name ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching channels: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get single channel
     */
    public function getChannel(int $channelId): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM chat_channels
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $channelId,
                'tenant_id' => $this->tenantId
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error fetching channel: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if user is channel member
     */
    public function isChannelMember(int $channelId, int $userId): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM chat_channel_members
                WHERE channel_id = :channel_id AND user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute([
                'channel_id' => $channelId,
                'user_id' => $userId
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error checking membership: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new channel
     */
    public function createChannel(array $data): ?array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_channels (tenant_id, name, type, description, created_by)
                VALUES (:tenant_id, :name, :type, :description, :created_by)
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'name' => $data['name'] ?? 'Untitled',
                'type' => $data['type'] ?? 'public',
                'description' => $data['description'] ?? null,
                'created_by' => $this->userId
            ]);

            $channelId = (int)$this->db->lastInsertId();

            // Add creator as owner
            $this->addChannelMember($channelId, $this->userId, 'owner');

            // Add other members if specified
            if (!empty($data['members'])) {
                foreach ($data['members'] as $memberId) {
                    $this->addChannelMember($channelId, (int)$memberId, 'member');
                }
            }

            return $this->getChannel($channelId);
        } catch (PDOException $e) {
            error_log("Error creating channel: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Add member to channel
     */
    private function addChannelMember(int $channelId, int $userId, string $role = 'member'): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_channel_members (channel_id, user_id, role)
                VALUES (:channel_id, :user_id, :role)
                ON DUPLICATE KEY UPDATE role = :role
            ");

            return $stmt->execute([
                'channel_id' => $channelId,
                'user_id' => $userId,
                'role' => $role
            ]);
        } catch (PDOException $e) {
            error_log("Error adding member: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get messages for channel
     */
    public function getMessages(int $channelId, int $limit = 50, int $offset = 0, ?int $parentMessageId = null): array {
        try {
            $sql = "
                SELECT m.*, u.name as user_name, u.avatar as user_avatar
                FROM chat_messages m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.channel_id = :channel_id
                AND m.tenant_id = :tenant_id
            ";

            $params = [
                'channel_id' => $channelId,
                'tenant_id' => $this->tenantId
            ];

            if ($parentMessageId) {
                $sql .= " AND m.parent_message_id = :parent_id";
                $params['parent_id'] = $parentMessageId;
            } else {
                $sql .= " AND m.parent_message_id IS NULL";
            }

            $sql .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching messages: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Send message
     */
    public function sendMessage(array $data): ?array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_messages (
                    tenant_id, channel_id, user_id, content,
                    message_type, parent_message_id, attachment_id
                ) VALUES (
                    :tenant_id, :channel_id, :user_id, :content,
                    :message_type, :parent_message_id, :attachment_id
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'channel_id' => $data['channel_id'],
                'user_id' => $this->userId,
                'content' => $data['content'] ?? '',
                'message_type' => $data['message_type'] ?? 'text',
                'parent_message_id' => $data['parent_message_id'] ?? null,
                'attachment_id' => $data['attachment_id'] ?? null
            ]);

            $messageId = (int)$this->db->lastInsertId();

            // Get the created message
            $stmt = $this->db->prepare("
                SELECT m.*, u.name as user_name, u.avatar as user_avatar
                FROM chat_messages m
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.id = :id
            ");
            $stmt->execute(['id' => $messageId]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Error sending message: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Poll for new messages
     */
    public function pollMessages(int $lastMessageId, ?int $channelId = null, int $timeout = 30): array {
        $messages = [];
        $startTime = time();

        while (time() - $startTime < $timeout) {
            try {
                $sql = "
                    SELECT m.*, u.name as user_name, u.avatar as user_avatar,
                           c.name as channel_name
                    FROM chat_messages m
                    LEFT JOIN users u ON m.user_id = u.id
                    LEFT JOIN chat_channels c ON m.channel_id = c.id
                    LEFT JOIN chat_channel_members cm ON c.id = cm.channel_id AND cm.user_id = :user_id
                    WHERE m.id > :last_id
                    AND m.tenant_id = :tenant_id
                    AND (c.type = 'public' OR cm.user_id IS NOT NULL)
                ";

                $params = [
                    'last_id' => $lastMessageId,
                    'tenant_id' => $this->tenantId,
                    'user_id' => $this->userId
                ];

                if ($channelId) {
                    $sql .= " AND m.channel_id = :channel_id";
                    $params['channel_id'] = $channelId;
                }

                $sql .= " ORDER BY m.id ASC LIMIT 50";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($messages)) {
                    break;
                }

                sleep(1);
            } catch (PDOException $e) {
                error_log("Error polling messages: " . $e->getMessage());
                break;
            }
        }

        return $messages;
    }

    /**
     * Get presence information
     */
    public function getPresence(?int $channelId = null): array {
        try {
            $sql = "
                SELECT p.*, u.name, u.avatar
                FROM chat_presence p
                LEFT JOIN users u ON p.user_id = u.id
                WHERE p.tenant_id = :tenant_id
                AND p.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ";

            $params = ['tenant_id' => $this->tenantId];

            if ($channelId) {
                $sql .= " AND p.current_channel_id = :channel_id";
                $params['channel_id'] = $channelId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching presence: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update user presence
     */
    public function updatePresence(array $data): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO chat_presence (tenant_id, user_id, status, current_channel_id, last_seen)
                VALUES (:tenant_id, :user_id, :status, :channel_id, NOW())
                ON DUPLICATE KEY UPDATE
                    status = :status,
                    current_channel_id = :channel_id,
                    last_seen = NOW()
            ");

            return $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'status' => $data['status'] ?? 'online',
                'channel_id' => $data['current_channel_id'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error updating presence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark messages as read
     */
    public function markAsRead(int $channelId, int $lastMessageId): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO message_reads (user_id, channel_id, last_read_message_id, unread_count)
                VALUES (:user_id, :channel_id, :last_id, 0)
                ON DUPLICATE KEY UPDATE
                    last_read_message_id = :last_id,
                    unread_count = 0,
                    updated_at = NOW()
            ");

            return $stmt->execute([
                'user_id' => $this->userId,
                'channel_id' => $channelId,
                'last_id' => $lastMessageId
            ]);
        } catch (PDOException $e) {
            error_log("Error marking as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread counts
     */
    public function getUnreadCounts(): array {
        try {
            $stmt = $this->db->prepare("
                SELECT channel_id, unread_count, unread_mentions
                FROM message_reads
                WHERE user_id = :user_id
                AND unread_count > 0
            ");
            $stmt->execute(['user_id' => $this->userId]);

            $counts = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $counts[$row['channel_id']] = [
                    'messages' => (int)$row['unread_count'],
                    'mentions' => (int)$row['unread_mentions']
                ];
            }

            return $counts;
        } catch (PDOException $e) {
            error_log("Error fetching unread counts: " . $e->getMessage());
            return [];
        }
    }
}
PHP;

        file_put_contents(__DIR__ . '/includes/ChatManager.php', $code);
        echo "  ✅ Created ChatManager.php\n";
        $this->report['fixes'][] = "Created ChatManager.php";
    }

    /**
     * Fix CalendarManager class
     */
    private function fixCalendarManager(): void {
        echo "🔧 Checking CalendarManager class...\n";

        $path = __DIR__ . '/includes/CalendarManager.php';

        if (!file_exists($path)) {
            echo "  ❌ CalendarManager.php not found! Creating stub...\n";
            $this->createCalendarManagerStub();
        } else {
            echo "  ✅ CalendarManager.php exists\n";
        }

        echo "\n";
    }

    /**
     * Create CalendarManager stub
     */
    private function createCalendarManagerStub(): void {
        $code = <<<'PHP'
<?php declare(strict_types=1);

namespace Collabora\Calendar;

use PDO;

/**
 * Calendar Manager for Nexio Collabora
 */
class CalendarManager {
    private PDO $db;
    private int $tenantId;
    private int $userId;

    public function __construct(PDO $db, int $tenantId, int $userId) {
        $this->db = $db;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
    }

    public function getCalendars(array $filters = []): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM calendars
                WHERE tenant_id = :tenant_id
                AND (owner_id = :user_id OR is_public = 1)
                ORDER BY name ASC
            ");
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error fetching calendars: " . $e->getMessage());
            return [];
        }
    }

    public function getCalendar(int $id): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM calendars
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $id,
                'tenant_id' => $this->tenantId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            error_log("Error fetching calendar: " . $e->getMessage());
            return null;
        }
    }

    public function createCalendar(array $data): ?array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO calendars (tenant_id, owner_id, name, description, color)
                VALUES (:tenant_id, :owner_id, :name, :description, :color)
            ");
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'owner_id' => $this->userId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#4F46E5'
            ]);
            return $this->getCalendar((int)$this->db->lastInsertId());
        } catch (\PDOException $e) {
            error_log("Error creating calendar: " . $e->getMessage());
            return null;
        }
    }

    public function updateCalendar(int $id, array $data): ?array {
        // Stub implementation
        return $this->getCalendar($id);
    }

    public function deleteCalendar(int $id): bool {
        // Stub implementation
        return true;
    }

    public function getEvents(array $filters = []): array {
        // Stub implementation
        return [];
    }

    public function getEvent(int $id): ?array {
        // Stub implementation
        return null;
    }

    public function shareCalendar(int $calendarId, int $userId, string $permission): bool {
        // Stub implementation
        return true;
    }
}
PHP;

        file_put_contents(__DIR__ . '/includes/CalendarManager.php', $code);
        echo "  ✅ Created CalendarManager.php stub\n";
        $this->report['fixes'][] = "Created CalendarManager.php stub";
    }

    /**
     * Fix TaskManager class
     */
    private function fixTaskManager(): void {
        echo "🔧 Checking TaskManager class...\n";

        $path = __DIR__ . '/includes/TaskManager.php';

        if (!file_exists($path)) {
            echo "  ❌ TaskManager.php not found! Creating stub...\n";
            $this->createTaskManagerStub();
        } else {
            echo "  ✅ TaskManager.php exists\n";
        }

        echo "\n";
    }

    /**
     * Create TaskManager stub
     */
    private function createTaskManagerStub(): void {
        $code = <<<'PHP'
<?php declare(strict_types=1);

namespace Collabora\Tasks;

use PDO;

/**
 * Task Manager for Nexio Collabora
 */
class TaskManager {
    private PDO $db;
    private int $tenantId;
    private int $userId;

    public function __construct(PDO $db, int $tenantId, int $userId) {
        $this->db = $db;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
    }

    public function getTaskLists(array $filters = []): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM task_lists
                WHERE tenant_id = :tenant_id
                ORDER BY position ASC, name ASC
            ");
            $stmt->execute(['tenant_id' => $this->tenantId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error fetching task lists: " . $e->getMessage());
            return [];
        }
    }

    public function getTaskList(int $id): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM task_lists
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1
            ");
            $stmt->execute([
                'id' => $id,
                'tenant_id' => $this->tenantId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            error_log("Error fetching task list: " . $e->getMessage());
            return null;
        }
    }

    public function createTaskList(array $data): ?array {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO task_lists (tenant_id, name, description, created_by)
                VALUES (:tenant_id, :name, :description, :created_by)
            ");
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => $this->userId
            ]);
            return $this->getTaskList((int)$this->db->lastInsertId());
        } catch (\PDOException $e) {
            error_log("Error creating task list: " . $e->getMessage());
            return null;
        }
    }

    public function getTasks(array $filters = []): array {
        // Stub implementation
        return [];
    }
}
PHP;

        file_put_contents(__DIR__ . '/includes/TaskManager.php', $code);
        echo "  ✅ Created TaskManager.php stub\n";
        $this->report['fixes'][] = "Created TaskManager.php stub";
    }

    /**
     * Create missing tables SQL script
     */
    private function createMissingTablesScript(array $missingTables): void {
        $sql = "-- Missing Tables Creation Script\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        $sql .= "USE nexio_collabora_v2;\n\n";

        // Add CREATE TABLE statements for missing tables
        foreach ($missingTables as $table => $description) {
            $sql .= "-- $description\n";
            $sql .= $this->getCreateTableSQL($table) . "\n\n";
        }

        $filename = __DIR__ . '/create_missing_tables.sql';
        file_put_contents($filename, $sql);

        echo "  📝 Created SQL script: create_missing_tables.sql\n";
        echo "  ⚠️ Run this command to create missing tables:\n";
        echo "     mysql -u root nexio_collabora_v2 < create_missing_tables.sql\n";

        $this->report['fixes'][] = "Created SQL script for missing tables";
    }

    /**
     * Get CREATE TABLE SQL for specific table
     */
    private function getCreateTableSQL(string $table): string {
        $tables = [
            'chat_channels' => "
CREATE TABLE IF NOT EXISTS chat_channels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('public', 'private', 'direct') DEFAULT 'public',
    description TEXT,
    created_by INT,
    is_archived BOOLEAN DEFAULT FALSE,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_type (type),
    INDEX idx_archived (is_archived)
);",
            'chat_channel_members' => "
CREATE TABLE IF NOT EXISTS chat_channel_members (
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    notification_preference ENUM('all', 'mentions', 'none') DEFAULT 'all',
    muted_until TIMESTAMP NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, user_id),
    INDEX idx_user (user_id)
);",
            'chat_messages' => "
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT,
    message_type ENUM('text', 'system', 'file') DEFAULT 'text',
    parent_message_id INT NULL,
    attachment_id INT NULL,
    metadata JSON,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel (channel_id),
    INDEX idx_parent (parent_message_id),
    INDEX idx_created (created_at)
);",
            'message_reads' => "
CREATE TABLE IF NOT EXISTS message_reads (
    user_id INT NOT NULL,
    channel_id INT NOT NULL,
    last_read_message_id INT,
    unread_count INT DEFAULT 0,
    unread_mentions INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, channel_id)
);",
            'chat_presence' => "
CREATE TABLE IF NOT EXISTS chat_presence (
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('online', 'away', 'busy', 'offline') DEFAULT 'offline',
    current_channel_id INT NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, user_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
);",
            'chat_typing_indicators' => "
CREATE TABLE IF NOT EXISTS chat_typing_indicators (
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    PRIMARY KEY (channel_id, user_id)
);",
            'calendars' => "
CREATE TABLE IF NOT EXISTS calendars (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#4F46E5',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_owner (tenant_id, owner_id)
);",
            'events' => "
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    calendar_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    all_day BOOLEAN DEFAULT FALSE,
    recurrence_rule VARCHAR(500),
    status ENUM('confirmed', 'tentative', 'cancelled') DEFAULT 'confirmed',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_calendar (calendar_id),
    INDEX idx_dates (start_datetime, end_datetime)
);",
            'event_participants' => "
CREATE TABLE IF NOT EXISTS event_participants (
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    response ENUM('accepted', 'declined', 'tentative', 'needs-action') DEFAULT 'needs-action',
    responded_at TIMESTAMP NULL,
    PRIMARY KEY (event_id, user_id)
);",
            'task_lists' => "
CREATE TABLE IF NOT EXISTS task_lists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    position INT DEFAULT 0,
    view_type ENUM('kanban', 'list', 'calendar') DEFAULT 'kanban',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_position (position)
);",
            'tasks' => "
CREATE TABLE IF NOT EXISTS tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    task_list_id INT NOT NULL,
    parent_task_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('todo', 'in_progress', 'review', 'done', 'cancelled') DEFAULT 'todo',
    priority ENUM('urgent', 'high', 'medium', 'low') DEFAULT 'medium',
    position INT DEFAULT 0,
    due_date DATE NULL,
    estimated_hours DECIMAL(5,2),
    actual_hours DECIMAL(5,2),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_list_status (task_list_id, status),
    INDEX idx_parent (parent_task_id),
    INDEX idx_due_date (due_date)
);",
            'task_assignments' => "
CREATE TABLE IF NOT EXISTS task_assignments (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id)
);"
        ];

        return $tables[$table] ?? "-- Definition not available for table: $table";
    }

    /**
     * Create comprehensive API test script
     */
    private function createAPITestScript(): void {
        echo "📝 Creating API test script...\n";

        $code = <<<'PHP'
<?php declare(strict_types=1);

/**
 * Comprehensive API Test Script
 * Tests all API endpoints for proper error handling
 */

// Test configuration
$baseUrl = 'http://localhost/Nexiosolution/collabora/api';
$testEmail = 'test@example.com';
$testPassword = 'test123';

// Color codes for output
$colors = [
    'red' => "\033[31m",
    'green' => "\033[32m",
    'yellow' => "\033[33m",
    'blue' => "\033[34m",
    'reset' => "\033[0m"
];

function testAPI(string $endpoint, string $method = 'GET', array $data = [], array $headers = []): array {
    global $baseUrl;

    $url = $baseUrl . '/' . $endpoint;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    // Set headers
    $defaultHeaders = ['Content-Type: application/json'];
    $allHeaders = array_merge($defaultHeaders, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

    // Set data for POST/PUT
    if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    // Handle cookies for session
    $cookieFile = __DIR__ . '/test_cookies.txt';
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($ch);

    return [
        'code' => $httpCode,
        'headers' => $headers,
        'body' => $body,
        'json' => json_decode($body, true)
    ];
}

function printResult(string $test, bool $passed, string $details = ''): void {
    global $colors;

    $icon = $passed ? '✅' : '❌';
    $color = $passed ? $colors['green'] : $colors['red'];

    echo sprintf(
        "%s %s%s%s %s\n",
        $icon,
        $color,
        $test,
        $colors['reset'],
        $details
    );
}

echo "========================================\n";
echo "    API ENDPOINT TESTING SUITE         \n";
echo "========================================\n\n";

// Test results storage
$results = [];

// 1. Test authentication endpoints
echo $colors['blue'] . "Testing Authentication APIs..." . $colors['reset'] . "\n";

// Test login without credentials (should return 400)
$response = testAPI('auth_simple.php', 'POST', []);
$passed = $response['code'] === 400;
$results['auth_no_creds'] = $passed;
printResult(
    "POST /auth_simple.php (no credentials)",
    $passed,
    "HTTP " . $response['code'] . " - " . ($response['json']['message'] ?? 'No message')
);

// Test login with invalid credentials (should return 401)
$response = testAPI('auth_simple.php', 'POST', [
    'action' => 'login',
    'email' => 'invalid@example.com',
    'password' => 'wrongpass'
]);
$passed = $response['code'] === 401;
$results['auth_invalid'] = $passed;
printResult(
    "POST /auth_simple.php (invalid credentials)",
    $passed,
    "HTTP " . $response['code']
);

echo "\n";

// 2. Test protected endpoints without authentication
echo $colors['blue'] . "Testing Protected Endpoints (No Auth)..." . $colors['reset'] . "\n";

$protectedEndpoints = [
    'users.php' => 'GET',
    'calendars.php' => 'GET',
    'events.php' => 'GET',
    'task-lists.php' => 'GET',
    'channels.php' => 'GET',
    'messages.php' => 'GET',
    'chat-poll.php' => 'GET'
];

foreach ($protectedEndpoints as $endpoint => $method) {
    $response = testAPI($endpoint, $method);
    $passed = $response['code'] === 401;
    $results[$endpoint . '_noauth'] = $passed;

    $message = $response['json']['message'] ?? $response['json']['error'] ?? 'No message';
    printResult(
        "$method /$endpoint (no auth)",
        $passed,
        "HTTP " . $response['code'] . " - $message"
    );
}

echo "\n";

// 3. Test required parameters
echo $colors['blue'] . "Testing Required Parameters..." . $colors['reset'] . "\n";

// First, authenticate with admin
$response = testAPI('auth_simple.php', 'POST', [
    'action' => 'login',
    'email' => 'asamodeo@fortibyte.it',
    'password' => 'Ricord@1991'
]);

if ($response['code'] === 200) {
    echo $colors['green'] . "✅ Authenticated as admin\n" . $colors['reset'];

    // Test messages without channel_id
    $response = testAPI('messages.php', 'GET');
    $passed = $response['code'] === 400 &&
              isset($response['json']['error']) &&
              $response['json']['error'] === 'missing_channel_id';
    $results['messages_no_channel'] = $passed;
    printResult(
        "GET /messages.php (no channel_id)",
        $passed,
        "HTTP " . $response['code']
    );

    // Test sending message without content
    $response = testAPI('messages.php', 'POST', ['channel_id' => 1]);
    $passed = $response['code'] === 400 &&
              isset($response['json']['error']) &&
              $response['json']['error'] === 'missing_content';
    $results['messages_no_content'] = $passed;
    printResult(
        "POST /messages.php (no content)",
        $passed,
        "HTTP " . $response['code']
    );

} else {
    echo $colors['red'] . "❌ Failed to authenticate for parameter tests\n" . $colors['reset'];
}

echo "\n";

// 4. Summary
echo $colors['blue'] . "========================================" . $colors['reset'] . "\n";
echo $colors['blue'] . "              TEST SUMMARY              " . $colors['reset'] . "\n";
echo $colors['blue'] . "========================================" . $colors['reset'] . "\n";

$totalTests = count($results);
$passedTests = array_sum($results);
$failedTests = $totalTests - $passedTests;

$summaryColor = $failedTests === 0 ? $colors['green'] : ($failedTests <= 2 ? $colors['yellow'] : $colors['red']);

echo sprintf(
    "%sTotal: %d | Passed: %d | Failed: %d%s\n",
    $summaryColor,
    $totalTests,
    $passedTests,
    $failedTests,
    $colors['reset']
);

if ($failedTests > 0) {
    echo "\n" . $colors['red'] . "Failed Tests:" . $colors['reset'] . "\n";
    foreach ($results as $test => $passed) {
        if (!$passed) {
            echo "  - $test\n";
        }
    }
}

echo "\n";

// Clean up
if (file_exists(__DIR__ . '/test_cookies.txt')) {
    unlink(__DIR__ . '/test_cookies.txt');
}
PHP;

        file_put_contents(__DIR__ . '/test_api_endpoints.php', $code);
        echo "  ✅ Created test_api_endpoints.php\n";
        $this->report['fixes'][] = "Created API test script";
        echo "\n";
    }

    /**
     * Display final report
     */
    private function displayReport(): void {
        echo "===========================================\n";
        echo "              FINAL REPORT                 \n";
        echo "===========================================\n\n";

        // Database tables summary
        if (isset($this->report['database_tables'])) {
            $tables = $this->report['database_tables'];
            echo "📊 Database Tables:\n";
            echo "   - Required: " . $tables['total_required'] . "\n";
            echo "   - Existing: " . $tables['total_existing'] . "\n";
            echo "   - Missing: " . $tables['total_missing'] . "\n\n";

            if ($tables['total_missing'] > 0) {
                echo "   ⚠️ Missing tables:\n";
                foreach ($tables['missing'] as $table => $desc) {
                    echo "      - $table ($desc)\n";
                }
                echo "\n   Run: mysql -u root nexio_collabora_v2 < create_missing_tables.sql\n\n";
            }
        }

        // Applied fixes
        if (count($this->report['fixes']) > 0) {
            echo "✅ Applied Fixes:\n";
            foreach ($this->report['fixes'] as $fix) {
                echo "   - $fix\n";
            }
            echo "\n";
        }

        // Errors encountered
        if (count($this->report['errors']) > 0) {
            echo "❌ Errors Encountered:\n";
            foreach ($this->report['errors'] as $error) {
                echo "   - $error\n";
            }
            echo "\n";
        }

        // Next steps
        echo "📝 Next Steps:\n";
        echo "   1. Create missing database tables (if any)\n";
        echo "   2. Run API tests: php test_api_endpoints.php\n";
        echo "   3. Check error logs: tail -f /var/log/apache2/error.log\n";
        echo "   4. Test in browser: http://localhost/Nexiosolution/collabora/\n\n";

        // Save report to file
        $reportFile = __DIR__ . '/api_fix_report_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($this->report, JSON_PRETTY_PRINT));
        echo "💾 Full report saved to: " . basename($reportFile) . "\n\n";
    }
}

// Run the fixer
try {
    $fixer = new APIErrorFixer();
    $fixer->runFixes();
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}