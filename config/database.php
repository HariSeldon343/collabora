<?php
declare(strict_types=1);

/**
 * Database Configuration Bridge
 * Ponte tra config_v2.php e i manager che richiedono config/database.php
 *
 * Questo file garantisce compatibilità con il sistema esistente
 * caricando le configurazioni da config_v2.php e fornendo
 * una connessione PDO ai componenti che lo richiedono.
 */

// Prevenzione accesso diretto
if (!defined('SECURE_ACCESS')) {
    define('SECURE_ACCESS', true);
}

// Carica la configurazione principale se non già caricata
if (!defined('DB_HOST')) {
    $configFile = dirname(__DIR__) . '/config_v2.php';
    if (file_exists($configFile)) {
        require_once $configFile;
    } else {
        // Configurazione di fallback per compatibilità
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'nexio_collabora_v2');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_CHARSET', 'utf8mb4');

        // Path essenziali
        define('BASE_PATH', dirname(__DIR__));
        define('LOG_PATH', BASE_PATH . '/logs');
        define('UPLOAD_PATH', BASE_PATH . '/uploads');
        define('TEMP_PATH', BASE_PATH . '/temp');

        // Configurazione sistema
        define('APP_NAME', 'Nexio Solution V2');
        define('APP_VERSION', '2.0.0');
        define('DEBUG_MODE', true);
        define('DEFAULT_TIMEZONE', 'Europe/Rome');

        // Multi-tenant
        define('ENABLE_MULTI_TENANT', true);
        define('DEFAULT_TENANT_CODE', 'DEFAULT');
    }
}

// Carica il database helper se non già caricato
if (!class_exists('Database')) {
    require_once dirname(__DIR__) . '/includes/db.php';
}

/**
 * Classe di configurazione database per compatibilità
 * con i manager che si aspettano questa struttura
 */
class DatabaseConfig {
    /**
     * Ottiene la configurazione del database come array
     *
     * @return array Configurazione database
     */
    public static function getConfig(): array {
        return [
            'driver' => 'mysql',
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASS,
            'charset' => DB_CHARSET,
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => 'InnoDB',
            'timezone' => '+01:00',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_PERSISTENT => false
            ]
        ];
    }

    /**
     * Ottiene una connessione PDO al database
     *
     * @return PDO Connessione database
     * @throws Exception Se la connessione fallisce
     */
    public static function getConnection(): PDO {
        return Database::getInstance();
    }

    /**
     * Ottiene il DSN per la connessione
     *
     * @return string DSN string
     */
    public static function getDSN(): string {
        return sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
    }

    /**
     * Verifica se il database è accessibile
     *
     * @return bool True se accessibile, false altrimenti
     */
    public static function isAccessible(): bool {
        try {
            $pdo = self::getConnection();
            $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Ottiene informazioni sullo stato del database
     *
     * @return array Informazioni database
     */
    public static function getStatus(): array {
        try {
            $pdo = self::getConnection();

            // Versione MySQL
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            // Conteggio tabelle
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = ?
            ");
            $stmt->execute([DB_NAME]);
            $tableCount = $stmt->fetchColumn();

            // Dimensione database
            $stmt = $pdo->prepare("
                SELECT
                    SUM(data_length + index_length) as size
                FROM information_schema.tables
                WHERE table_schema = ?
                GROUP BY table_schema
            ");
            $stmt->execute([DB_NAME]);
            $dbSize = $stmt->fetchColumn() ?? 0;

            return [
                'connected' => true,
                'database' => DB_NAME,
                'host' => DB_HOST,
                'version' => $version,
                'charset' => DB_CHARSET,
                'tables' => $tableCount,
                'size' => $dbSize,
                'size_formatted' => self::formatBytes((int)$dbSize)
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Formatta i bytes in formato leggibile
     *
     * @param int $bytes Numero di bytes
     * @param int $precision Precisione decimale
     * @return string Stringa formattata
     */
    private static function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

/**
 * Funzione helper per ottenere rapidamente una connessione
 * Mantiene compatibilità con codice esistente
 */
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection(): PDO {
        return DatabaseConfig::getConnection();
    }
}

/**
 * Funzione helper per ottenere la configurazione
 * Mantiene compatibilità con codice esistente
 */
if (!function_exists('getDatabaseConfig')) {
    function getDatabaseConfig(): array {
        return DatabaseConfig::getConfig();
    }
}

// Export per compatibilità con require che si aspettano un return
return [
    'connection' => DatabaseConfig::getConnection(),
    'config' => DatabaseConfig::getConfig(),
    'dsn' => DatabaseConfig::getDSN()
];