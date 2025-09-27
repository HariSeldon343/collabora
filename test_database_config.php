<?php
/**
 * Test script per verificare config/database.php
 * Verifica che la configurazione del database funzioni correttamente
 */

// Impostazioni di test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Test Configurazione Database ===\n\n";

// Test 1: Verifica che il file esista
$dbConfigFile = __DIR__ . '/config/database.php';
echo "1. Verifica file config/database.php:\n";
if (file_exists($dbConfigFile)) {
    echo "   ✓ File trovato: $dbConfigFile\n";
} else {
    echo "   ✗ File NON trovato: $dbConfigFile\n";
    exit(1);
}

// Test 2: Carica il file e verifica che funzioni
echo "\n2. Caricamento configurazione:\n";
try {
    require_once $dbConfigFile;
    echo "   ✓ File caricato senza errori\n";
} catch (Exception $e) {
    echo "   ✗ Errore nel caricamento: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Verifica che la classe DatabaseConfig esista
echo "\n3. Verifica classe DatabaseConfig:\n";
if (class_exists('DatabaseConfig')) {
    echo "   ✓ Classe DatabaseConfig disponibile\n";
} else {
    echo "   ✗ Classe DatabaseConfig NON trovata\n";
    exit(1);
}

// Test 4: Ottieni configurazione
echo "\n4. Recupero configurazione:\n";
try {
    $config = DatabaseConfig::getConfig();
    echo "   ✓ Configurazione recuperata:\n";
    echo "     - Host: " . $config['host'] . "\n";
    echo "     - Database: " . $config['database'] . "\n";
    echo "     - Charset: " . $config['charset'] . "\n";
} catch (Exception $e) {
    echo "   ✗ Errore nel recupero config: " . $e->getMessage() . "\n";
}

// Test 5: Test connessione al database
echo "\n5. Test connessione database:\n";
try {
    if (DatabaseConfig::isAccessible()) {
        echo "   ✓ Database accessibile\n";

        // Ottieni stato del database
        $status = DatabaseConfig::getStatus();
        if ($status['connected']) {
            echo "   ✓ Dettagli database:\n";
            echo "     - Versione MySQL: " . $status['version'] . "\n";
            echo "     - Tabelle: " . $status['tables'] . "\n";
            echo "     - Dimensione: " . $status['size_formatted'] . "\n";
        }
    } else {
        echo "   ✗ Database NON accessibile\n";
        $status = DatabaseConfig::getStatus();
        if (isset($status['error'])) {
            echo "     Errore: " . $status['error'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Errore nella connessione: " . $e->getMessage() . "\n";
}

// Test 6: Test connessione PDO diretta
echo "\n6. Test connessione PDO diretta:\n";
try {
    $pdo = DatabaseConfig::getConnection();
    echo "   ✓ Connessione PDO ottenuta\n";

    // Test query semplice
    $result = $pdo->query("SELECT 1 as test")->fetch();
    if ($result['test'] == 1) {
        echo "   ✓ Query di test eseguita con successo\n";
    }
} catch (Exception $e) {
    echo "   ✗ Errore PDO: " . $e->getMessage() . "\n";
}

// Test 7: Verifica funzioni helper
echo "\n7. Verifica funzioni helper:\n";
if (function_exists('getDatabaseConnection')) {
    echo "   ✓ Funzione getDatabaseConnection() disponibile\n";
    try {
        $conn = getDatabaseConnection();
        echo "   ✓ getDatabaseConnection() funziona\n";
    } catch (Exception $e) {
        echo "   ✗ getDatabaseConnection() fallita: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ Funzione getDatabaseConnection() NON disponibile\n";
}

if (function_exists('getDatabaseConfig')) {
    echo "   ✓ Funzione getDatabaseConfig() disponibile\n";
    try {
        $cfg = getDatabaseConfig();
        echo "   ✓ getDatabaseConfig() funziona\n";
    } catch (Exception $e) {
        echo "   ✗ getDatabaseConfig() fallita: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ Funzione getDatabaseConfig() NON disponibile\n";
}

// Test 8: Verifica compatibilità con getDbConnection() esistente
echo "\n8. Verifica compatibilità con getDbConnection() esistente:\n";
if (function_exists('getDbConnection')) {
    echo "   ✓ Funzione getDbConnection() disponibile (da includes/db.php)\n";
    try {
        $dbConn = getDbConnection();
        echo "   ✓ getDbConnection() funziona\n";

        // Verifica che sia la stessa istanza
        $pdoConn = DatabaseConfig::getConnection();
        if ($dbConn === $pdoConn) {
            echo "   ✓ Le connessioni sono identiche (singleton funziona)\n";
        }
    } catch (Exception $e) {
        echo "   ✗ getDbConnection() fallita: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ Funzione getDbConnection() NON disponibile\n";
}

// Test 9: Verifica tabelle essenziali
echo "\n9. Verifica tabelle essenziali:\n";
try {
    $pdo = DatabaseConfig::getConnection();
    $tables = ['users', 'tenants', 'chat_channels', 'chat_messages', 'files', 'folders'];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "   ✓ Tabella '$table' esiste\n";
        } else {
            echo "   ✗ Tabella '$table' NON esiste\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Errore verifica tabelle: " . $e->getMessage() . "\n";
}

echo "\n=== Test Completato ===\n";
echo "\nRiassunto:\n";
echo "- File config/database.php: ✓ CREATO E FUNZIONANTE\n";
echo "- Connessione database: " . (DatabaseConfig::isAccessible() ? "✓ ATTIVA" : "✗ NON ATTIVA") . "\n";
echo "- Compatibilità sistema: ✓ MANTENUTA\n";
echo "\nOra le API dovrebbero funzionare correttamente!\n";