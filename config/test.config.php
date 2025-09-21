<?php
/**
 * Configurazione Test Environment
 *
 * Questo file controlla l'accesso ai test e definisce
 * le impostazioni di sicurezza per diversi ambienti
 */

// Rilevamento ambiente automatico
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$serverAddr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

// Determina se siamo in development
define('TEST_ENV_IS_DEV', in_array($serverName, ['localhost', '127.0.0.1', 'dev.nexiosolution.local']));
define('TEST_ENV_IS_CLI', php_sapi_name() === 'cli');

// Configurazione accessi
define('TEST_ALLOW_BROWSER', TEST_ENV_IS_DEV); // Permetti accesso browser solo in dev
define('TEST_ALLOW_CLI', true); // CLI sempre permesso
define('TEST_REQUIRE_AUTH', false); // Richiedi autenticazione (impostare true in staging)

// Credenziali test (se TEST_REQUIRE_AUTH è true)
define('TEST_AUTH_USERNAME', 'test_admin');
define('TEST_AUTH_PASSWORD', '$2y$10$7.CKyJR0gNm6cY5PxQg8n.KWhLxmHfGqZ1rIZGg8YJ8kqJ9B5qZGW'); // password: Test@2025

// Directory test
define('TEST_ROOT_PATH', dirname(__DIR__));
define('TEST_TESTS_PATH', TEST_ROOT_PATH . '/tests');
define('TEST_LOGS_PATH', TEST_ROOT_PATH . '/logs/tests');

// Limiti esecuzione
define('TEST_MAX_EXECUTION_TIME', 300); // 5 minuti
define('TEST_MEMORY_LIMIT', '256M');

// Database test (se diverso dal principale)
define('TEST_DB_HOST', 'localhost');
define('TEST_DB_NAME', 'nexio_collabora_test'); // Database separato per test
define('TEST_DB_USER', 'root');
define('TEST_DB_PASS', '');

// Impostazioni report
define('TEST_REPORT_ERRORS', true);
define('TEST_REPORT_VERBOSE', TEST_ENV_IS_DEV);
define('TEST_SAVE_RESULTS', true);
define('TEST_RESULTS_RETENTION_DAYS', 30);

// Test specifici abilitati/disabilitati
$TEST_ENABLED_CATEGORIES = [
    'auth' => true,
    'api' => true,
    'db' => true,
    'ui' => true,
    'system' => true,
    'security' => TEST_ENV_IS_DEV, // Security test solo in dev
    'integration' => true,
    'migration' => true
];

// Test che richiedono servizi esterni
$TEST_EXTERNAL_DEPENDENCIES = [
    'test_email_sending' => ['smtp' => true],
    'test_file_upload' => ['storage' => true],
    'test_api_integration' => ['external_api' => true]
];

// Whitelist IP per accesso test (vuoto = tutti)
$TEST_ALLOWED_IPS = TEST_ENV_IS_DEV ? [] : [
    '127.0.0.1',
    '::1',
    '192.168.1.0/24' // Rete locale
];

/**
 * Verifica se l'accesso ai test è permesso
 */
function isTestAccessAllowed() {
    // CLI sempre permesso
    if (TEST_ENV_IS_CLI) {
        return true;
    }

    // Verifica ambiente
    if (!TEST_ALLOW_BROWSER) {
        return false;
    }

    // Verifica IP se whitelist configurata
    global $TEST_ALLOWED_IPS;
    if (!empty($TEST_ALLOWED_IPS)) {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $allowed = false;

        foreach ($TEST_ALLOWED_IPS as $allowedIp) {
            if (strpos($allowedIp, '/') !== false) {
                // CIDR notation
                list($subnet, $mask) = explode('/', $allowedIp);
                if (ip2long($clientIp) >> (32 - $mask) == ip2long($subnet) >> (32 - $mask)) {
                    $allowed = true;
                    break;
                }
            } else {
                // IP singolo
                if ($clientIp === $allowedIp) {
                    $allowed = true;
                    break;
                }
            }
        }

        if (!$allowed) {
            return false;
        }
    }

    // Verifica autenticazione se richiesta
    if (TEST_REQUIRE_AUTH) {
        session_start();
        if (!isset($_SESSION['test_authenticated'])) {
            // Verifica credenziali HTTP Basic
            $user = $_SERVER['PHP_AUTH_USER'] ?? '';
            $pass = $_SERVER['PHP_AUTH_PW'] ?? '';

            if ($user !== TEST_AUTH_USERNAME || !password_verify($pass, TEST_AUTH_PASSWORD)) {
                header('WWW-Authenticate: Basic realm="Test Area"');
                header('HTTP/1.0 401 Unauthorized');
                return false;
            }

            $_SESSION['test_authenticated'] = true;
        }
    }

    return true;
}

/**
 * Log attività test
 */
function logTestActivity($action, $details = []) {
    if (!TEST_SAVE_RESULTS) {
        return;
    }

    $logDir = TEST_LOGS_PATH;
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/test_activity_' . date('Y-m-d') . '.log';
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'user' => $_SESSION['test_authenticated_user'] ?? 'anonymous',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
        'details' => $details
    ];

    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Pulizia log vecchi
 */
function cleanOldTestLogs() {
    $logDir = TEST_LOGS_PATH;
    if (!is_dir($logDir)) {
        return;
    }

    $files = glob($logDir . '/test_*.log');
    $cutoffTime = time() - (TEST_RESULTS_RETENTION_DAYS * 86400);

    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            unlink($file);
        }
    }
}

// Pulizia automatica (1% di probabilità per evitare overhead)
if (rand(1, 100) === 1) {
    cleanOldTestLogs();
}

// Esporta configurazione
return [
    'is_dev' => TEST_ENV_IS_DEV,
    'is_cli' => TEST_ENV_IS_CLI,
    'allow_browser' => TEST_ALLOW_BROWSER,
    'allow_cli' => TEST_ALLOW_CLI,
    'require_auth' => TEST_REQUIRE_AUTH,
    'enabled_categories' => $TEST_ENABLED_CATEGORIES,
    'external_dependencies' => $TEST_EXTERNAL_DEPENDENCIES,
    'allowed_ips' => $TEST_ALLOWED_IPS
];