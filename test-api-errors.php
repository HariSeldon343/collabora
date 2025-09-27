<?php
/**
 * Script di test per debug errori API
 * Identifica gli errori esatti che causano i 500 errors
 */

// Configurazione error reporting massimo
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);

// Carica configurazione base
define('ROOT_PATH', __DIR__);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug API Errors</title>
    <style>
        body {
            font-family: monospace;
            margin: 20px;
            background: #1a1a1a;
            color: #e0e0e0;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #444;
            background: #2a2a2a;
            border-radius: 5px;
        }
        .endpoint {
            color: #4CAF50;
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .success {
            color: #8BC34A;
            padding: 10px;
            background: #1b5e20;
            border-radius: 3px;
            margin: 5px 0;
        }
        .error {
            color: #ff5252;
            padding: 10px;
            background: #b71c1c;
            border-radius: 3px;
            margin: 5px 0;
            white-space: pre-wrap;
        }
        .warning {
            color: #FFC107;
            padding: 10px;
            background: #5d4037;
            border-radius: 3px;
            margin: 5px 0;
        }
        .info {
            color: #2196F3;
            padding: 10px;
            background: #0d47a1;
            border-radius: 3px;
            margin: 5px 0;
        }
        .code {
            background: #000;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            font-size: 12px;
            line-height: 1.4;
        }
        h1 {
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #2196F3;
            margin-top: 30px;
        }
    </style>
</head>
<body>
<h1>🔍 Debug API Errors - Nexiosolution</h1>
";

// Funzione per catturare errori
function testEndpoint($name, $path, $setup = null) {
    echo "<div class='test-section'>";
    echo "<div class='endpoint'>📍 Testing: $name</div>";
    echo "<div class='info'>Path: $path</div>";

    // Verifica se il file esiste
    if (!file_exists($path)) {
        echo "<div class='error'>❌ FILE NON TROVATO: $path</div>";
        echo "</div>";
        return;
    }

    echo "<div class='success'>✅ File exists</div>";

    // Cattura errori con output buffering
    ob_start();
    $errorOccurred = false;
    $errorMessage = '';

    // Custom error handler per catturare tutti gli errori
    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorOccurred, &$errorMessage) {
        $errorOccurred = true;
        $errorMessage .= "ERROR [$errno]: $errstr\n";
        $errorMessage .= "File: $errfile\n";
        $errorMessage .= "Line: $errline\n\n";
        return true; // Previene il default error handler
    });

    // Custom exception handler
    set_exception_handler(function($exception) use (&$errorOccurred, &$errorMessage) {
        $errorOccurred = true;
        $errorMessage .= "EXCEPTION: " . $exception->getMessage() . "\n";
        $errorMessage .= "File: " . $exception->getFile() . "\n";
        $errorMessage .= "Line: " . $exception->getLine() . "\n";
        $errorMessage .= "Trace:\n" . $exception->getTraceAsString() . "\n\n";
    });

    try {
        // Setup opzionale (es. simulare sessione)
        if ($setup && is_callable($setup)) {
            $setup();
        }

        // Prova a includere il file
        include $path;

        $output = ob_get_contents();

        if (!$errorOccurred) {
            echo "<div class='success'>✅ No PHP errors detected</div>";

            if (!empty($output)) {
                // Verifica se l'output è JSON valido
                $jsonData = @json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    echo "<div class='info'>📊 JSON Response:</div>";
                    echo "<div class='code'>" . htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT)) . "</div>";
                } else {
                    echo "<div class='warning'>⚠️ Output (non-JSON):</div>";
                    echo "<div class='code'>" . htmlspecialchars($output) . "</div>";
                }
            }
        }

    } catch (Exception $e) {
        $errorOccurred = true;
        $errorMessage .= "CAUGHT EXCEPTION: " . $e->getMessage() . "\n";
        $errorMessage .= "File: " . $e->getFile() . "\n";
        $errorMessage .= "Line: " . $e->getLine() . "\n";
    } catch (Error $e) {
        $errorOccurred = true;
        $errorMessage .= "FATAL ERROR: " . $e->getMessage() . "\n";
        $errorMessage .= "File: " . $e->getFile() . "\n";
        $errorMessage .= "Line: " . $e->getLine() . "\n";
    } finally {
        ob_end_clean();
        restore_error_handler();
        restore_exception_handler();
    }

    if ($errorOccurred) {
        echo "<div class='error'>❌ ERRORS FOUND:\n" . htmlspecialchars($errorMessage) . "</div>";
    }

    echo "</div>";
}

// Funzione per setup sessione simulata
function setupSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = 1;
    $_SESSION['tenant_id'] = 1;
    $_SESSION['company_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['permissions'] = ['view_all', 'edit_all'];

    // Simula richiesta AJAX
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
}

// Funzione per verificare database
function checkDatabase() {
    echo "<div class='test-section'>";
    echo "<div class='endpoint'>🗄️ Database Connection Test</div>";

    try {
        require_once ROOT_PATH . '/config/database.php';

        $db = Database::getInstance();
        $conn = $db->getConnection();

        if ($conn) {
            echo "<div class='success'>✅ Database connected successfully</div>";

            // Test query
            $stmt = $conn->query("SELECT VERSION() as version");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<div class='info'>MySQL Version: " . $result['version'] . "</div>";

            // Verifica tabelle
            $stmt = $conn->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo "<div class='info'>Tables found: " . count($tables) . "</div>";

            // Verifica tabelle principali
            $requiredTables = ['users', 'companies', 'chat_rooms', 'chat_messages', 'calendars', 'calendar_events'];
            foreach ($requiredTables as $table) {
                if (in_array($table, $tables)) {
                    echo "<div class='success'>✅ Table '$table' exists</div>";
                } else {
                    echo "<div class='error'>❌ Table '$table' NOT FOUND</div>";
                }
            }

        } else {
            echo "<div class='error'>❌ Database connection failed</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    echo "</div>";
}

// Funzione per verificare file di configurazione
function checkConfig() {
    echo "<div class='test-section'>";
    echo "<div class='endpoint'>⚙️ Configuration Files Check</div>";

    $configFiles = [
        '/config/database.php' => 'Database Configuration',
        '/config/app.php' => 'App Configuration',
        '/includes/auth.php' => 'Auth Include',
        '/includes/cors.php' => 'CORS Include'
    ];

    foreach ($configFiles as $file => $name) {
        $path = ROOT_PATH . $file;
        if (file_exists($path)) {
            echo "<div class='success'>✅ $name: Found</div>";

            // Verifica sintassi PHP
            $output = [];
            $return = 0;
            exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $return);

            if ($return === 0) {
                echo "<div class='info'>✅ PHP Syntax OK</div>";
            } else {
                echo "<div class='error'>❌ PHP Syntax Error:\n" . implode("\n", $output) . "</div>";
            }
        } else {
            echo "<div class='error'>❌ $name: NOT FOUND at $path</div>";
        }
    }

    echo "</div>";
}

// ==========================================
// ESEGUI I TEST
// ==========================================

echo "<h2>1️⃣ Configuration & Database Check</h2>";
checkConfig();
checkDatabase();

echo "<h2>2️⃣ API Endpoints Test</h2>";

// Test degli endpoint problematici
$endpoints = [
    'Chat Channels' => '/api/channels.php',
    'Chat Poll' => '/api/chat-poll.php',
    'Calendars' => '/api/calendars.php',
    'Calendar Events' => '/api/events.php',
    'Chat Messages' => '/api/messages.php',
    'Users' => '/api/users.php',
    'Companies' => '/api/companies.php',
    'Tasks' => '/api/tasks.php'
];

foreach ($endpoints as $name => $path) {
    testEndpoint($name, ROOT_PATH . $path, 'setupSession');
}

// Test specifico per auth
echo "<h2>3️⃣ Authentication Test</h2>";
testEndpoint('Auth Login', ROOT_PATH . '/api/auth/login.php', function() {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['email' => 'admin@nexio.com', 'password' => 'Admin123!'];
});

echo "<h2>4️⃣ Session Status</h2>";
echo "<div class='test-section'>";
echo "<div class='endpoint'>🔐 Current Session</div>";
if (isset($_SESSION) && !empty($_SESSION)) {
    echo "<div class='info'>Session Data:</div>";
    echo "<div class='code'>" . htmlspecialchars(print_r($_SESSION, true)) . "</div>";
} else {
    echo "<div class='warning'>⚠️ No session data available</div>";
}
echo "</div>";

echo "<h2>5️⃣ PHP Configuration</h2>";
echo "<div class='test-section'>";
echo "<div class='endpoint'>⚙️ PHP Settings</div>";
echo "<div class='info'>PHP Version: " . PHP_VERSION . "</div>";
echo "<div class='info'>Error Reporting: " . error_reporting() . "</div>";
echo "<div class='info'>Display Errors: " . ini_get('display_errors') . "</div>";
echo "<div class='info'>Max Execution Time: " . ini_get('max_execution_time') . "s</div>";
echo "<div class='info'>Memory Limit: " . ini_get('memory_limit') . "</div>";
echo "<div class='info'>Session Save Path: " . session_save_path() . "</div>";

// Verifica estensioni richieste
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
echo "<div class='info'>Required Extensions:</div>";
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='success'>✅ $ext: Loaded</div>";
    } else {
        echo "<div class='error'>❌ $ext: NOT LOADED</div>";
    }
}
echo "</div>";

echo "
<script>
// Auto-scroll to errors
document.addEventListener('DOMContentLoaded', function() {
    const firstError = document.querySelector('.error');
    if (firstError) {
        console.log('Errors found in API tests');
    }
});
</script>
</body>
</html>";
?>