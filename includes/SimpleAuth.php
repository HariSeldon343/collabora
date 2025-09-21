<?php
/**
 * Simple Authentication Class for Nexio Collabora
 * Minimal implementation for login functionality
 */

// Custom exceptions for specific error types
class AuthException extends Exception {
    protected $errorCode = 'auth_error';
    protected $httpCode = 500;
    protected $fields = [];

    public function getErrorCode() {
        return $this->errorCode;
    }

    public function getHttpCode() {
        return $this->httpCode;
    }

    public function getFields() {
        return $this->fields;
    }
}

class InvalidCredentialsException extends AuthException {
    protected $errorCode = 'invalid_credentials';
    protected $httpCode = 401;
}

class MissingFieldsException extends AuthException {
    protected $errorCode = 'missing_fields';
    protected $httpCode = 400;

    public function __construct($message = '', $fields = [], $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->fields = $fields;
    }
}

class DatabaseException extends AuthException {
    protected $errorCode = 'database_error';
    protected $httpCode = 500;
}

class AccountInactiveException extends AuthException {
    protected $errorCode = 'account_inactive';
    protected $httpCode = 403;
}

class SimpleAuth {
    private $db;

    public function __construct() {
        // Start session if not started with proper configuration
        if (session_status() === PHP_SESSION_NONE) {
            // Load config to get session settings
            if (!defined('SESSION_PATH')) {
                require_once __DIR__ . '/../config_v2.php';
            }

            // Configure session parameters for subfolder installation
            session_set_cookie_params([
                'lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200,
                'path' => defined('SESSION_PATH') ? SESSION_PATH : '/Nexiosolution/collabora/',
                'domain' => '',
                'secure' => defined('SESSION_SECURE') ? SESSION_SECURE : false,
                'httponly' => defined('SESSION_HTTPONLY') ? SESSION_HTTPONLY : true,
                'samesite' => defined('SESSION_SAMESITE') ? SESSION_SAMESITE : 'Lax'
            ]);

            // Use custom session name if defined
            if (defined('SESSION_NAME')) {
                session_name(SESSION_NAME);
            }

            session_start();

            // Log session initialization
            error_log('[SimpleAuth] Session initialized - ID: ' . session_id() . ', Path: ' . (defined('SESSION_PATH') ? SESSION_PATH : 'default'));
        }

        // Get database connection
        $this->db = $this->getDbConnection();
    }

    private function getDbConnection() {
        try {
            // Include config if not already defined
            if (!defined('DB_HOST')) {
                require_once __DIR__ . '/../config_v2.php';
            }

            // Check if db.php exists and has getDbConnection function
            if (file_exists(__DIR__ . '/db.php')) {
                require_once __DIR__ . '/db.php';
                if (function_exists('getDbConnection')) {
                    return getDbConnection();
                }
            }

            // Fallback to direct connection
            $dsn = 'mysql:host=' . (defined('DB_HOST') ? DB_HOST : 'localhost') .
                   ';dbname=' . (defined('DB_NAME') ? DB_NAME : 'nexio_collabora_v2') .
                   ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4');

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];

            $pdo = new PDO(
                $dsn,
                defined('DB_USER') ? DB_USER : 'root',
                defined('DB_PASS') ? DB_PASS : '',
                $options
            );

            // Test connection
            $pdo->query('SELECT 1');

            return $pdo;
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            throw new DatabaseException('Database connection failed');
        }
    }

    public function login($email, $password) {
        try {
            // Validate input - NO TENANT CODE REQUIRED
            $missingFields = [];
            if (empty($email)) {
                $missingFields[] = 'email';
            }
            if (empty($password)) {
                $missingFields[] = 'password';
            }

            if (!empty($missingFields)) {
                throw new MissingFieldsException(
                    'Email e password sono obbligatori',
                    $missingFields
                );
            }

            // Log attempt (without password)
            error_log('[SimpleAuth] Login attempt for email: ' . $email);

            // Get user from database with last_active_tenant_id
            $stmt = $this->db->prepare("
                SELECT id, email, password, first_name, last_name, role,
                       is_system_admin, status, last_active_tenant_id
                FROM users
                WHERE email = :email
                AND (deleted_at IS NULL OR deleted_at = '')
            ");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if (!$user) {
                error_log('[SimpleAuth] User not found for email: ' . $email);
                throw new InvalidCredentialsException('Credenziali non valide');
            }

            // Check if account is active
            if ($user['status'] !== 'active') {
                error_log('[SimpleAuth] Account inactive for email: ' . $email);
                throw new AccountInactiveException('Account non attivo');
            }

            // Verify password
            if (!password_verify($password, $user['password'])) {
                error_log('[SimpleAuth] Invalid password for email: ' . $email);
                throw new InvalidCredentialsException('Credenziali non valide');
            }

            error_log('[SimpleAuth] Password verified for user: ' . $user['id']);

            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);

            // Get user's accessible tenants based on role
            $tenants = [];
            $selectedTenantId = null;
            $autoSelected = false;

            if ($user['role'] === 'admin' || $user['is_system_admin'] == 1) {
                // Admin: Get ALL active tenants (no association required)
                error_log('[SimpleAuth] User is admin - fetching all active tenants');
                $stmt = $this->db->prepare("
                    SELECT id, code, name, status
                    FROM tenants
                    WHERE status = 'active'
                    AND (deleted_at IS NULL OR deleted_at = '')
                    ORDER BY name
                ");
                $stmt->execute();
                $tenants = $stmt->fetchAll();

            } else if ($user['role'] === 'special_user') {
                // Special user: Get assigned tenants from associations
                error_log('[SimpleAuth] User is special_user - fetching assigned tenants');
                $stmt = $this->db->prepare("
                    SELECT t.id, t.code, t.name, t.status,
                           uta.is_default as is_primary, uta.role as tenant_role,
                           (t.id = :last_active) as was_last_active
                    FROM user_tenant_associations uta
                    INNER JOIN tenants t ON uta.tenant_id = t.id
                    WHERE uta.user_id = :user_id
                    AND t.status = 'active'
                    AND (t.deleted_at IS NULL OR t.deleted_at = '')
                    ORDER BY uta.is_default DESC, was_last_active DESC, t.name
                ");
                $stmt->execute([
                    'user_id' => $user['id'],
                    'last_active' => $user['last_active_tenant_id']
                ]);
                $tenants = $stmt->fetchAll();

            } else {
                // Standard user: Get single assigned tenant (auto-select)
                error_log('[SimpleAuth] User is standard_user - fetching single tenant');
                $stmt = $this->db->prepare("
                    SELECT t.id, t.code, t.name, t.status,
                           uta.is_default as is_primary, uta.role as tenant_role
                    FROM user_tenant_associations uta
                    INNER JOIN tenants t ON uta.tenant_id = t.id
                    WHERE uta.user_id = :user_id
                    AND t.status = 'active'
                    AND (t.deleted_at IS NULL OR t.deleted_at = '')
                    LIMIT 1
                ");
                $stmt->execute(['user_id' => $user['id']]);
                $tenants = $stmt->fetchAll();

                // Auto-select for standard user
                if (!empty($tenants)) {
                    $autoSelected = true;
                }
            }

            error_log('[SimpleAuth] Found ' . count($tenants) . ' accessible tenants');

            // Check if user has no tenant access
            if (empty($tenants)) {
                error_log('[SimpleAuth] No tenant access for user: ' . $user['id']);
                throw new AuthException('Nessun tenant accessibile per questo utente');
            }

            // Determine default tenant to select
            if ($autoSelected || count($tenants) === 1) {
                // Single tenant or standard user - auto select
                $selectedTenantId = $tenants[0]['id'];
                $autoSelected = true;
            } else {
                // Multiple tenants - use smart selection
                // Priority: last_active_tenant_id > primary > first
                $selectedTenantId = null;

                // Check if last active tenant is still accessible
                if ($user['last_active_tenant_id']) {
                    foreach ($tenants as $tenant) {
                        if ($tenant['id'] == $user['last_active_tenant_id']) {
                            $selectedTenantId = $tenant['id'];
                            break;
                        }
                    }
                }

                // If not found, look for primary tenant
                if (!$selectedTenantId) {
                    foreach ($tenants as $tenant) {
                        if (isset($tenant['is_primary']) && $tenant['is_primary']) {
                            $selectedTenantId = $tenant['id'];
                            break;
                        }
                    }
                }

                // If still not found, select first tenant
                if (!$selectedTenantId) {
                    $selectedTenantId = $tenants[0]['id'];
                }
            }

            // Update last_active_tenant_id if changed
            if ($selectedTenantId && $selectedTenantId != $user['last_active_tenant_id']) {
                $stmt = $this->db->prepare("
                    UPDATE users
                    SET last_active_tenant_id = :tenant_id
                    WHERE id = :user_id
                ");
                $stmt->execute([
                    'tenant_id' => $selectedTenantId,
                    'user_id' => $user['id']
                ]);
            }

            // Set up session with tenant information
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['is_admin'] = ($user['role'] === 'admin' || $user['is_system_admin'] == 1);
            $_SESSION['current_tenant_id'] = $selectedTenantId;
            $_SESSION['available_tenants'] = $tenants;
            $_SESSION['user_v2'] = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'role' => $user['role']
            ];

            error_log('[SimpleAuth] Session configured with tenant_id: ' . $selectedTenantId);

            // Store session in user_sessions table
            $this->storeUserSession($user['id'], $selectedTenantId);

            // Return success response with tenant information
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                    'role' => $user['role'],
                    'is_admin' => $_SESSION['is_admin']
                ],
                'tenants' => $tenants,
                'current_tenant_id' => $selectedTenantId,
                'auto_selected' => $autoSelected,
                'needs_tenant_selection' => (count($tenants) > 1 && !$autoSelected),
                'session_id' => session_id()
            ];

        } catch (AuthException $e) {
            // Re-throw custom exceptions
            throw $e;
        } catch (PDOException $e) {
            error_log('[SimpleAuth] Database error during login: ' . $e->getMessage());
            throw new DatabaseException('Database error during login');
        } catch (Exception $e) {
            error_log('[SimpleAuth] Unexpected error during login: ' . $e->getMessage());
            throw new AuthException('Unexpected error during login');
        }
    }

    /**
     * Store user session in database
     */
    private function storeUserSession($userId, $tenantId) {
        try {
            $sessionId = session_id();
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

            // First, check if user_sessions table exists
            $stmt = $this->db->query("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name = 'user_sessions'
            ");
            $result = $stmt->fetch();

            if ($result['count'] == 0) {
                // Create table if it doesn't exist
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS user_sessions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        session_id VARCHAR(255) NOT NULL,
                        tenant_id INT NOT NULL,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                        INDEX idx_session_id (session_id),
                        INDEX idx_user_tenant (user_id, tenant_id),
                        INDEX idx_last_activity (last_activity)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // Delete old sessions for this user
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);

            // Insert new session
            $stmt = $this->db->prepare("
                INSERT INTO user_sessions (user_id, session_id, tenant_id, ip_address, user_agent)
                VALUES (:user_id, :session_id, :tenant_id, :ip_address, :user_agent)
            ");
            $stmt->execute([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'tenant_id' => $tenantId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ]);

            error_log('[SimpleAuth] User session stored in database');

        } catch (PDOException $e) {
            // Log error but don't fail login if session table doesn't work
            error_log('[SimpleAuth] Could not store user session: ' . $e->getMessage());
        }
    }

    public function logout() {
        // Remove session from database
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $_SESSION['user_id']]);
            } catch (PDOException $e) {
                error_log('[SimpleAuth] Could not remove user session: ' . $e->getMessage());
            }
        }

        session_destroy();
        return ['success' => true, 'message' => 'Logout effettuato con successo'];
    }

    public function isAuthenticated() {
        return isset($_SESSION['user_id']);
    }

    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $_SESSION['user_v2'] ?? null;
    }

    public function getAvailableTenants() {
        if (!$this->isAuthenticated()) {
            throw new InvalidCredentialsException('Non autenticato');
        }

        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? '';
        $isAdmin = $_SESSION['is_admin'] ?? false;

        $tenants = [];

        if ($isAdmin || $userRole === 'admin') {
            // Admin: Get ALL active tenants
            $stmt = $this->db->prepare("
                SELECT id, code, name, status
                FROM tenants
                WHERE status = 'active'
                AND (deleted_at IS NULL OR deleted_at = '')
                ORDER BY name
            ");
            $stmt->execute();
            $tenants = $stmt->fetchAll();

        } else if ($userRole === 'special_user') {
            // Special user: Get assigned tenants
            $stmt = $this->db->prepare("
                SELECT t.id, t.code, t.name, t.status,
                       uta.is_default as is_primary, uta.role as tenant_role
                FROM user_tenant_associations uta
                INNER JOIN tenants t ON uta.tenant_id = t.id
                WHERE uta.user_id = :user_id
                AND t.status = 'active'
                AND (t.deleted_at IS NULL OR t.deleted_at = '')
                ORDER BY uta.is_default DESC, t.name
            ");
            $stmt->execute(['user_id' => $userId]);
            $tenants = $stmt->fetchAll();

        } else {
            // Standard user: Get single assigned tenant
            $stmt = $this->db->prepare("
                SELECT t.id, t.code, t.name, t.status,
                       uta.is_default as is_primary, uta.role as tenant_role
                FROM user_tenant_associations uta
                INNER JOIN tenants t ON uta.tenant_id = t.id
                WHERE uta.user_id = :user_id
                AND t.status = 'active'
                AND (t.deleted_at IS NULL OR t.deleted_at = '')
                LIMIT 1
            ");
            $stmt->execute(['user_id' => $userId]);
            $tenants = $stmt->fetchAll();
        }

        // Mark current tenant
        $currentTenantId = $_SESSION['current_tenant_id'] ?? null;
        foreach ($tenants as &$tenant) {
            $tenant['is_current'] = ($tenant['id'] == $currentTenantId);
        }

        return $tenants;
    }

    public function getCurrentTenant() {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $tenantId = $_SESSION['current_tenant_id'] ?? null;
        if (!$tenantId) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT id, code, name, status
            FROM tenants
            WHERE id = :tenant_id
            AND status = 'active'
            AND (deleted_at IS NULL OR deleted_at = '')
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        return $stmt->fetch() ?: null;
    }

    public function switchTenant($tenantId) {
        if (!$this->isAuthenticated()) {
            throw new InvalidCredentialsException('Non autenticato');
        }

        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? '';
        $isAdmin = $_SESSION['is_admin'] ?? false;

        // Standard users cannot switch tenants
        if ($userRole === 'standard_user') {
            throw new AuthException('Gli utenti standard non possono cambiare tenant');
        }

        // Verify the tenant exists and is active
        $stmt = $this->db->prepare("
            SELECT id, code, name, status
            FROM tenants
            WHERE id = :tenant_id
            AND status = 'active'
            AND (deleted_at IS NULL OR deleted_at = '')
        ");
        $stmt->execute(['tenant_id' => $tenantId]);
        $tenant = $stmt->fetch();

        if (!$tenant) {
            throw new AuthException('Tenant non valido o non attivo');
        }

        // Admin can switch to any tenant
        if ($isAdmin || $userRole === 'admin') {
            error_log('[SimpleAuth] Admin switching to tenant: ' . $tenantId);
        } else {
            // Special user - verify they have access
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM user_tenant_associations
                WHERE user_id = :user_id
                AND tenant_id = :tenant_id
                AND (access_expires_at IS NULL OR access_expires_at > NOW())
            ");
            $stmt->execute([
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            $result = $stmt->fetch();

            if ($result['count'] == 0) {
                error_log('[SimpleAuth] User ' . $userId . ' denied access to tenant ' . $tenantId);
                throw new AuthException('Accesso al tenant non autorizzato');
            }
        }

        // Update session
        $_SESSION['current_tenant_id'] = $tenantId;

        // Update last_active_tenant_id in database
        $stmt = $this->db->prepare("
            UPDATE users
            SET last_active_tenant_id = :tenant_id
            WHERE id = :user_id
        ");
        $stmt->execute([
            'tenant_id' => $tenantId,
            'user_id' => $userId
        ]);

        error_log('[SimpleAuth] User ' . $userId . ' switched to tenant ' . $tenantId);

        return [
            'success' => true,
            'tenant' => $tenant,
            'message' => 'Tenant cambiato con successo'
        ];
    }

    public function generateCSRFToken() {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
    }
}