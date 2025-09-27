<?php
/**
 * Test diretto per identificare errori di inclusione
 */

// Massimo error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<pre>";
echo "=== TEST INCLUSIONE FILE API ===\n\n";

// Test 1: config_v2.php
echo "1. Testing config_v2.php:\n";
$configPath = __DIR__ . '/config_v2.php';
if (file_exists($configPath)) {
    echo "   ✓ File exists\n";
    try {
        require_once $configPath;
        echo "   ✓ File included successfully\n";
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ File NOT FOUND at: $configPath\n";
}

// Test 2: autoload.php
echo "\n2. Testing includes/autoload.php:\n";
$autoloadPath = __DIR__ . '/includes/autoload.php';
if (file_exists($autoloadPath)) {
    echo "   ✓ File exists\n";
    try {
        require_once $autoloadPath;
        echo "   ✓ File included successfully\n";
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ File NOT FOUND at: $autoloadPath\n";
}

// Test 3: SimpleAuth.php
echo "\n3. Testing includes/SimpleAuth.php:\n";
$authPath = __DIR__ . '/includes/SimpleAuth.php';
if (file_exists($authPath)) {
    echo "   ✓ File exists\n";
    try {
        require_once $authPath;
        echo "   ✓ File included successfully\n";

        // Verifica se la classe esiste
        if (class_exists('SimpleAuth')) {
            echo "   ✓ Class SimpleAuth exists\n";

            // Prova a creare un'istanza
            try {
                $auth = new SimpleAuth();
                echo "   ✓ SimpleAuth instance created\n";
            } catch (Exception $e) {
                echo "   ✗ Cannot instantiate: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✗ Class SimpleAuth NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ File NOT FOUND at: $authPath\n";
}

// Test 4: ChatManager.php
echo "\n4. Testing includes/ChatManager.php:\n";
$chatPath = __DIR__ . '/includes/ChatManager.php';
if (file_exists($chatPath)) {
    echo "   ✓ File exists\n";
    try {
        require_once $chatPath;
        echo "   ✓ File included successfully\n";

        if (class_exists('ChatManager')) {
            echo "   ✓ Class ChatManager exists\n";
        } else {
            echo "   ✗ Class ChatManager NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ File NOT FOUND at: $chatPath\n";
}

// Test 5: db.php
echo "\n5. Testing includes/db.php:\n";
$dbPath = __DIR__ . '/includes/db.php';
if (file_exists($dbPath)) {
    echo "   ✓ File exists\n";
    try {
        require_once $dbPath;
        echo "   ✓ File included successfully\n";

        // Verifica funzione getDbConnection
        if (function_exists('getDbConnection')) {
            echo "   ✓ Function getDbConnection exists\n";

            // Prova a ottenere una connessione
            try {
                $conn = getDbConnection();
                if ($conn) {
                    echo "   ✓ Database connection successful\n";
                } else {
                    echo "   ✗ Database connection returned null\n";
                }
            } catch (Exception $e) {
                echo "   ✗ Database error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✗ Function getDbConnection NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ File NOT FOUND at: $dbPath\n";
}

// Test 6: Prova a includere un API completo
echo "\n6. Testing api/channels.php:\n";
$apiPath = __DIR__ . '/api/channels.php';
if (file_exists($apiPath)) {
    echo "   ✓ File exists\n";

    // Simula sessione
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['user_id'] = 1;
    $_SESSION['tenant_id'] = 1;
    $_SESSION['company_id'] = 1;
    $_SESSION['role'] = 'admin';

    // Simula richiesta AJAX
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';

    // Cattura output
    ob_start();
    $errorOccurred = false;
    $errorMsg = '';

    // Error handler
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorOccurred, &$errorMsg) {
        $errorOccurred = true;
        $errorMsg = "Error [$errno]: $errstr in $errfile on line $errline";
        return true;
    });

    try {
        include $apiPath;
        $output = ob_get_contents();

        if (!$errorOccurred) {
            echo "   ✓ File included without PHP errors\n";

            // Verifica output JSON
            $json = @json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "   ✓ Valid JSON response\n";
                echo "   Response: " . substr($output, 0, 100) . "...\n";
            } else {
                echo "   ✗ Invalid JSON response\n";
                echo "   Output: " . substr($output, 0, 200) . "...\n";
            }
        } else {
            echo "   ✗ PHP Error occurred:\n";
            echo "   " . $errorMsg . "\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
        echo "   File: " . $e->getFile() . "\n";
        echo "   Line: " . $e->getLine() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
        echo "   File: " . $e->getFile() . "\n";
        echo "   Line: " . $e->getLine() . "\n";
    } finally {
        ob_end_clean();
        restore_error_handler();
    }
} else {
    echo "   ✗ File NOT FOUND at: $apiPath\n";
}

// Test 7: Verifica config/database.php
echo "\n7. Testing config/database.php:\n";
$dbConfigPath = __DIR__ . '/config/database.php';
if (file_exists($dbConfigPath)) {
    echo "   ✓ File exists\n";
    try {
        require_once $dbConfigPath;
        echo "   ✓ File included successfully\n";

        // Verifica classe Database
        if (class_exists('Database')) {
            echo "   ✓ Class Database exists\n";

            // Prova a ottenere istanza
            try {
                $db = Database::getInstance();
                echo "   ✓ Database instance obtained\n";

                $conn = $db->getConnection();
                if ($conn) {
                    echo "   ✓ PDO connection successful\n";

                    // Test query
                    $stmt = $conn->query("SELECT DATABASE()");
                    $dbName = $stmt->fetchColumn();
                    echo "   ✓ Connected to database: $dbName\n";
                } else {
                    echo "   ✗ Connection is null\n";
                }
            } catch (Exception $e) {
                echo "   ✗ Database error: " . $e->getMessage() . "\n";
            }
        } else {
            echo "   ✗ Class Database NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Exception: " . $e->getMessage() . "\n";
    } catch (Error $e) {
        echo "   ✗ Fatal Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ File NOT FOUND at: $dbConfigPath\n";
}

echo "\n=== TEST COMPLETATO ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Error Reporting: " . error_reporting() . "\n";
echo "Display Errors: " . ini_get('display_errors') . "\n";

// Info sessione
echo "\nSession Info:\n";
if (isset($_SESSION)) {
    echo "Session ID: " . session_id() . "\n";
    echo "Session Data: " . print_r($_SESSION, true);
} else {
    echo "No session active\n";
}

echo "</pre>";
?>