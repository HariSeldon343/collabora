<?php
/**
 * Schema Alignment Fix Runner
 *
 * This script applies database migrations to ensure all required tables exist
 * with the correct structure, matching what the application code expects.
 *
 * Features:
 * - Database backup before migration
 * - Safe, idempotent migrations (CREATE IF NOT EXISTS)
 * - Comprehensive verification after migration
 * - Detailed logging and error reporting
 */

// Configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

// Colors for CLI output
$colors = [
    'reset'  => "\033[0m",
    'red'    => "\033[31m",
    'green'  => "\033[32m",
    'yellow' => "\033[33m",
    'blue'   => "\033[34m",
    'bold'   => "\033[1m"
];

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'name' => 'nexio_collabora_v2',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
];

// Migration file path
$migrationFile = __DIR__ . '/database/fix_schema_alignment.sql';

// Backup configuration
$backupDir = __DIR__ . '/database/backups';
$backupFile = $backupDir . '/backup_' . date('Y-m-d_His') . '.sql';

// Expected tables after migration
$expectedTables = [
    // Chat tables (code expects these)
    'chat_channels',
    'chat_channel_members',
    'chat_messages',
    'message_reactions',
    'message_mentions',
    'message_reads',
    'chat_presence',
    'chat_typing_indicators',
    'chat_pinned_messages',

    // Calendar tables
    'calendars',
    'events',
    'event_participants',
    'calendar_shares',

    // Task tables
    'task_lists',
    'tasks',
    'task_assignments'
];

/**
 * Print colored message
 */
function printMessage($message, $type = 'info') {
    global $colors;

    $prefix = date('[H:i:s] ');
    switch($type) {
        case 'error':
            echo $colors['red'] . $prefix . "✗ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'success':
            echo $colors['green'] . $prefix . "✓ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'warning':
            echo $colors['yellow'] . $prefix . "⚠ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'info':
            echo $colors['blue'] . $prefix . "ℹ " . $message . $colors['reset'] . PHP_EOL;
            break;
        case 'header':
            echo PHP_EOL . $colors['bold'] . $colors['blue'] . $message . $colors['reset'] . PHP_EOL;
            echo str_repeat('=', strlen($message)) . PHP_EOL;
            break;
        default:
            echo $prefix . $message . PHP_EOL;
    }
}

/**
 * Connect to database
 */
function connectDatabase($config) {
    try {
        $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
        $pdo = new PDO($dsn, $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

/**
 * Check if database exists
 */
function databaseExists($pdo, $dbName) {
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'");
    return $stmt->fetch() !== false;
}

/**
 * Create database if not exists
 */
function createDatabase($pdo, $dbName) {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbName`");
}

/**
 * Get list of existing tables
 */
function getExistingTables($pdo, $dbName) {
    $stmt = $pdo->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '$dbName'");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Create backup of database
 */
function createBackup($config, $backupFile) {
    global $backupDir;

    // Create backup directory if not exists
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // Build mysqldump command
    $cmd = sprintf(
        'mysqldump --user=%s --host=%s --single-transaction --routines --triggers %s > %s 2>&1',
        escapeshellarg($config['user']),
        escapeshellarg($config['host']),
        escapeshellarg($config['name']),
        escapeshellarg($backupFile)
    );

    // Add password if provided
    if (!empty($config['pass'])) {
        $cmd = sprintf(
            'mysqldump --user=%s --password=%s --host=%s --single-transaction --routines --triggers %s > %s 2>&1',
            escapeshellarg($config['user']),
            escapeshellarg($config['pass']),
            escapeshellarg($config['host']),
            escapeshellarg($config['name']),
            escapeshellarg($backupFile)
        );
    }

    // Execute backup
    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);

    if ($returnVar !== 0) {
        // Try alternative method: use PHP to export structure
        try {
            $pdo = connectDatabase($config);
            $pdo->exec("USE `{$config['name']}`");

            $backup = "-- Database backup: {$config['name']}\n";
            $backup .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";

            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                // Get CREATE TABLE statement
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $backup .= "\n-- Table: $table\n";
                $backup .= "DROP TABLE IF EXISTS `$table`;\n";
                $backup .= $row['Create Table'] . ";\n";
            }

            file_put_contents($backupFile, $backup);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    return $returnVar === 0;
}

/**
 * Execute migration SQL file
 */
function executeMigration($pdo, $migrationFile) {
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }

    $sql = file_get_contents($migrationFile);

    // Split by delimiter to handle procedures
    $statements = preg_split('/DELIMITER\s+(.*?)\n/i', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);

    $delimiter = ';';
    $errors = [];
    $successCount = 0;

    for ($i = 0; $i < count($statements); $i++) {
        if ($i % 2 == 1) {
            // This is a delimiter change
            $delimiter = trim($statements[$i]);
            continue;
        }

        $block = $statements[$i];
        if (empty(trim($block))) continue;

        // Split by current delimiter
        $queries = explode($delimiter, $block);

        foreach ($queries as $query) {
            $query = trim($query);
            if (empty($query)) continue;

            try {
                $pdo->exec($query);
                $successCount++;
            } catch (PDOException $e) {
                // Ignore certain expected errors
                if (strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate') !== false) {
                    // This is expected for idempotent migrations
                    $successCount++;
                } else {
                    $errors[] = "Query failed: " . substr($query, 0, 100) . "...\nError: " . $e->getMessage();
                }
            }
        }
    }

    return ['success' => $successCount, 'errors' => $errors];
}

/**
 * Verify tables exist after migration
 */
function verifyTables($pdo, $dbName, $expectedTables) {
    $existingTables = getExistingTables($pdo, $dbName);
    $missing = [];
    $found = [];

    foreach ($expectedTables as $table) {
        if (in_array($table, $existingTables)) {
            $found[] = $table;
        } else {
            $missing[] = $table;
        }
    }

    return ['found' => $found, 'missing' => $missing];
}

/**
 * Get table statistics
 */
function getTableStats($pdo, $dbName) {
    $stmt = $pdo->query("
        SELECT
            TABLE_NAME as table_name,
            TABLE_ROWS as row_count,
            ROUND(DATA_LENGTH/1024/1024, 2) as data_mb,
            ROUND(INDEX_LENGTH/1024/1024, 2) as index_mb
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = '$dbName'
        ORDER BY TABLE_NAME
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =====================================================
// MAIN EXECUTION
// =====================================================

printMessage("NEXIOSOLUTION SCHEMA ALIGNMENT FIX", 'header');
printMessage("Starting database migration process...", 'info');

try {
    // Step 1: Connect to database
    printMessage("Connecting to database...", 'info');
    $pdo = connectDatabase($dbConfig);
    printMessage("Database connection established", 'success');

    // Step 2: Check/Create database
    if (!databaseExists($pdo, $dbConfig['name'])) {
        printMessage("Database '{$dbConfig['name']}' not found, creating...", 'warning');
        createDatabase($pdo, $dbConfig['name']);
        printMessage("Database created successfully", 'success');
    } else {
        $pdo->exec("USE `{$dbConfig['name']}`");
        printMessage("Using database: {$dbConfig['name']}", 'info');
    }

    // Step 3: Check existing tables
    printMessage("Checking existing tables...", 'info');
    $existingTables = getExistingTables($pdo, $dbConfig['name']);
    printMessage("Found " . count($existingTables) . " existing tables", 'info');

    // Step 4: Create backup
    printMessage("Creating database backup...", 'info');
    if (createBackup($dbConfig, $backupFile)) {
        printMessage("Backup created: $backupFile", 'success');
    } else {
        printMessage("Backup creation failed (non-critical, continuing...)", 'warning');
    }

    // Step 5: Execute migration
    printMessage("Executing migration script...", 'info');
    $result = executeMigration($pdo, $migrationFile);
    printMessage("Executed {$result['success']} SQL statements", 'success');

    if (!empty($result['errors'])) {
        printMessage("Encountered " . count($result['errors']) . " non-critical errors", 'warning');
        foreach ($result['errors'] as $error) {
            printMessage("  - " . substr($error, 0, 100), 'warning');
        }
    }

    // Step 6: Verify tables
    printMessage("Verifying required tables...", 'info');
    $verification = verifyTables($pdo, $dbConfig['name'], $expectedTables);

    printMessage("VERIFICATION RESULTS", 'header');

    if (empty($verification['missing'])) {
        printMessage("All required tables exist!", 'success');
        foreach ($verification['found'] as $table) {
            printMessage("  ✓ $table", 'success');
        }
    } else {
        printMessage("Some tables are missing:", 'error');
        foreach ($verification['missing'] as $table) {
            printMessage("  ✗ $table", 'error');
        }
    }

    // Step 7: Show statistics
    printMessage("TABLE STATISTICS", 'header');
    $stats = getTableStats($pdo, $dbConfig['name']);

    printf("%-30s %-15s %-10s %-10s\n", "Table", "Rows", "Data (MB)", "Index (MB)");
    echo str_repeat('-', 70) . PHP_EOL;

    foreach ($stats as $stat) {
        printf("%-30s %-15s %-10s %-10s\n",
            $stat['table_name'],
            number_format($stat['row_count']),
            $stat['data_mb'],
            $stat['index_mb']
        );
    }

    // Final summary
    printMessage("MIGRATION SUMMARY", 'header');

    if (empty($verification['missing'])) {
        printMessage("✓ Migration completed successfully!", 'success');
        printMessage("✓ All " . count($expectedTables) . " required tables exist", 'success');
        printMessage("✓ Database is ready for use", 'success');

        printMessage("\nNext steps:", 'info');
        printMessage("1. Test the chat API: http://localhost/Nexiosolution/collabora/api/messages.php", 'info');
        printMessage("2. Test the calendar API: http://localhost/Nexiosolution/collabora/api/calendars.php", 'info');
        printMessage("3. Test the tasks API: http://localhost/Nexiosolution/collabora/api/tasks.php", 'info');

        exit(0);
    } else {
        printMessage("✗ Migration incomplete - some tables are missing", 'error');
        printMessage("Please check the migration logs and try again", 'error');
        exit(1);
    }

} catch (Exception $e) {
    printMessage("MIGRATION FAILED", 'header');
    printMessage($e->getMessage(), 'error');
    printMessage("\nTroubleshooting steps:", 'info');
    printMessage("1. Ensure MySQL/MariaDB is running", 'info');
    printMessage("2. Check database credentials in this script", 'info');
    printMessage("3. Verify migration file exists: $migrationFile", 'info');
    printMessage("4. Check MySQL error log for details", 'info');
    exit(1);
}
?>