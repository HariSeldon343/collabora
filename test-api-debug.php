<?php
/**
 * Debug diretto degli errori API
 * Accedi a questo file dal browser per vedere gli errori
 */

// Abilita tutti gli errori
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Output HTML
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug API Errors - Nexiosolution</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #4CAF50;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }
        .test-card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        .test-title {
            font-size: 18px;
            color: #2196F3;
            font-weight: 600;
        }
        .status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status.success {
            background: #1b5e20;
            color: #8BC34A;
        }
        .status.error {
            background: #b71c1c;
            color: #ff5252;
        }
        .status.warning {
            background: #5d4037;
            color: #FFC107;
        }
        .test-content {
            margin-top: 15px;
        }
        .code-block {
            background: #000;
            color: #0f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.4;
            margin: 10px 0;
            border: 1px solid #222;
        }
        .error-block {
            background: #2a0000;
            color: #ff6b6b;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #ff0000;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 12px;
        }
        .success-block {
            background: #0a2a0a;
            color: #4CAF50;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #4CAF50;
        }
        .info-block {
            background: #0a1a2a;
            color: #2196F3;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #2196F3;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .summary {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .summary h2 {
            color: #FFC107;
            margin-bottom: 15px;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-box {
            background: #0a0a0a;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            border: 1px solid #333;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
        }
        .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            margin-top: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Debug API Errors - Nexiosolution</h1>

    <?php
    // Statistiche globali
    $totalTests = 0;
    $successTests = 0;
    $errorTests = 0;
    $warningTests = 0;

    // Funzione per testare un endpoint
    function testEndpoint($name, $path, $method = 'GET', $data = null) {
        global $totalTests, $successTests, $errorTests, $warningTests;
        $totalTests++;

        echo '<div class="test-card">';
        echo '<div class="test-header">';
        echo '<div class="test-title">📍 ' . htmlspecialchars($name) . '</div>';

        $fullPath = __DIR__ . $path;

        if (!file_exists($fullPath)) {
            echo '<span class="status error">File Not Found</span>';
            echo '</div>';
            echo '<div class="error-block">❌ File non trovato: ' . htmlspecialchars($fullPath) . '</div>';
            echo '</div>';
            $errorTests++;
            return;
        }

        // Test sintassi PHP
        $syntaxCheck = shell_exec('php -l ' . escapeshellarg($fullPath) . ' 2>&1');
        if (strpos($syntaxCheck, 'No syntax errors') === false) {
            echo '<span class="status error">Syntax Error</span>';
            echo '</div>';
            echo '<div class="error-block">❌ Errore di sintassi PHP:' . "\n" . htmlspecialchars($syntaxCheck) . '</div>';
            echo '</div>';
            $errorTests++;
            return;
        }

        // Simula sessione per test
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['user_id'] = 1;
        $_SESSION['tenant_id'] = 1;
        $_SESSION['company_id'] = 1;
        $_SESSION['role'] = 'admin';

        // Backup delle variabili globali
        $backupServer = $_SERVER;
        $backupPost = $_POST;
        $backupGet = $_GET;

        // Configura ambiente per test
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        if ($data && $method === 'POST') {
            $_POST = $data;
        }

        // Cattura output e errori
        ob_start();
        $errorOccurred = false;
        $errorMessages = [];

        // Error handler personalizzato
        set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorOccurred, &$errorMessages) {
            $errorOccurred = true;
            $errorMessages[] = [
                'type' => 'PHP Error',
                'level' => $errno,
                'message' => $errstr,
                'file' => str_replace(__DIR__, '.', $errfile),
                'line' => $errline
            ];
            return true;
        });

        try {
            // Includi il file
            include $fullPath;
            $output = ob_get_contents();

        } catch (Exception $e) {
            $errorOccurred = true;
            $errorMessages[] = [
                'type' => 'Exception',
                'message' => $e->getMessage(),
                'file' => str_replace(__DIR__, '.', $e->getFile()),
                'line' => $e->getLine()
            ];
        } catch (Error $e) {
            $errorOccurred = true;
            $errorMessages[] = [
                'type' => 'Fatal Error',
                'message' => $e->getMessage(),
                'file' => str_replace(__DIR__, '.', $e->getFile()),
                'line' => $e->getLine()
            ];
        } finally {
            ob_end_clean();
            restore_error_handler();

            // Ripristina variabili globali
            $_SERVER = $backupServer;
            $_POST = $backupPost;
            $_GET = $backupGet;
        }

        // Mostra risultato
        if ($errorOccurred) {
            echo '<span class="status error">Errors Found</span>';
            echo '</div>';
            echo '<div class="test-content">';

            foreach ($errorMessages as $error) {
                echo '<div class="error-block">';
                echo '❌ <strong>' . $error['type'] . '</strong>' . "\n";
                echo 'Message: ' . htmlspecialchars($error['message']) . "\n";
                echo 'File: ' . htmlspecialchars($error['file']) . "\n";
                echo 'Line: ' . $error['line'];
                echo '</div>';
            }
            $errorTests++;
        } else {
            // Verifica se l'output è JSON valido
            $jsonData = @json_decode($output, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($jsonData['success']) && $jsonData['success']) {
                    echo '<span class="status success">Success</span>';
                    $successTests++;
                } else if (isset($jsonData['error'])) {
                    echo '<span class="status warning">API Error</span>';
                    $warningTests++;
                } else {
                    echo '<span class="status success">Valid JSON</span>';
                    $successTests++;
                }

                echo '</div>';
                echo '<div class="test-content">';
                echo '<div class="info-block">✅ Nessun errore PHP rilevato</div>';

                if (!empty($output)) {
                    echo '<div class="code-block">';
                    echo 'JSON Response:' . "\n";
                    echo htmlspecialchars(json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    echo '</div>';
                }
            } else {
                echo '<span class="status warning">Non-JSON Output</span>';
                echo '</div>';
                echo '<div class="test-content">';

                if (!empty($output)) {
                    echo '<div class="code-block">';
                    echo 'Raw Output:' . "\n";
                    echo htmlspecialchars($output);
                    echo '</div>';
                }
                $warningTests++;
            }
        }

        echo '</div>';
        echo '</div>';
    }

    // Test configurazione database
    echo '<div class="test-card">';
    echo '<div class="test-header">';
    echo '<div class="test-title">🗄️ Database Configuration</div>';

    try {
        require_once __DIR__ . '/config/database.php';

        $db = Database::getInstance();
        $conn = $db->getConnection();

        if ($conn) {
            echo '<span class="status success">Connected</span>';
            echo '</div>';
            echo '<div class="test-content">';

            // Info database
            $stmt = $conn->query("SELECT VERSION() as version, DATABASE() as db_name");
            $info = $stmt->fetch(PDO::FETCH_ASSOC);

            echo '<div class="success-block">';
            echo '✅ Database connesso con successo' . "\n";
            echo 'MySQL Version: ' . $info['version'] . "\n";
            echo 'Database: ' . ($info['db_name'] ?: 'Non selezionato');
            echo '</div>';

            // Verifica tabelle
            $tables = ['users', 'companies', 'chat_rooms', 'chat_messages', 'calendars', 'calendar_events'];
            $tablesFound = 0;
            $tablesMissing = [];

            foreach ($tables as $table) {
                try {
                    $stmt = $conn->query("SELECT 1 FROM $table LIMIT 1");
                    $tablesFound++;
                } catch (PDOException $e) {
                    $tablesMissing[] = $table;
                }
            }

            if ($tablesFound > 0) {
                echo '<div class="info-block">📊 Tabelle trovate: ' . $tablesFound . '/' . count($tables) . '</div>';
            }

            if (!empty($tablesMissing)) {
                echo '<div class="error-block">⚠️ Tabelle mancanti: ' . implode(', ', $tablesMissing) . '</div>';
            }

        } else {
            echo '<span class="status error">Connection Failed</span>';
            echo '</div>';
            echo '<div class="error-block">❌ Impossibile connettersi al database</div>';
        }
    } catch (Exception $e) {
        echo '<span class="status error">Error</span>';
        echo '</div>';
        echo '<div class="error-block">❌ ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    echo '</div></div>';

    // Test degli endpoint problematici
    echo '<h2 style="color: #2196F3; margin: 30px 0 20px 0;">📡 Test API Endpoints</h2>';

    echo '<div class="grid">';

    // Test endpoint principali
    $endpoints = [
        ['Chat Channels', '/api/channels.php'],
        ['Chat Poll', '/api/chat-poll.php'],
        ['Calendars', '/api/calendars.php'],
        ['Events', '/api/events.php'],
        ['Messages', '/api/messages.php'],
        ['Users', '/api/users.php'],
        ['Companies', '/api/companies.php'],
        ['Tasks', '/api/tasks.php'],
        ['Presence', '/api/presence.php'],
        ['Reactions', '/api/reactions.php']
    ];

    foreach ($endpoints as $endpoint) {
        testEndpoint($endpoint[0], $endpoint[1]);
    }

    echo '</div>';

    // Riepilogo
    echo '<div class="summary">';
    echo '<h2>📊 Riepilogo Test</h2>';
    echo '<div class="stat-grid">';

    echo '<div class="stat-box">';
    echo '<div class="stat-value">' . $totalTests . '</div>';
    echo '<div class="stat-label">Test Totali</div>';
    echo '</div>';

    echo '<div class="stat-box" style="border-color: #4CAF50;">';
    echo '<div class="stat-value" style="color: #4CAF50;">' . $successTests . '</div>';
    echo '<div class="stat-label">Successi</div>';
    echo '</div>';

    echo '<div class="stat-box" style="border-color: #FFC107;">';
    echo '<div class="stat-value" style="color: #FFC107;">' . $warningTests . '</div>';
    echo '<div class="stat-label">Warning</div>';
    echo '</div>';

    echo '<div class="stat-box" style="border-color: #ff5252;">';
    echo '<div class="stat-value" style="color: #ff5252;">' . $errorTests . '</div>';
    echo '<div class="stat-label">Errori</div>';
    echo '</div>';

    echo '</div>';

    // Percentuale successo
    $successRate = $totalTests > 0 ? round(($successTests / $totalTests) * 100) : 0;
    echo '<div style="margin-top: 20px; text-align: center;">';
    echo '<div style="font-size: 36px; font-weight: bold; color: ';
    echo $successRate >= 80 ? '#4CAF50' : ($successRate >= 50 ? '#FFC107' : '#ff5252');
    echo ';">' . $successRate . '%</div>';
    echo '<div style="color: #888; text-transform: uppercase; font-size: 12px;">Tasso di Successo</div>';
    echo '</div>';

    echo '</div>';

    // Suggerimenti
    if ($errorTests > 0) {
        echo '<div class="test-card" style="border-color: #ff5252; background: #2a0a0a;">';
        echo '<h2 style="color: #ff5252;">🔧 Azioni Consigliate</h2>';
        echo '<ul style="margin-left: 20px; line-height: 2;">';
        echo '<li>Verifica che tutti i file di include esistano nel percorso corretto</li>';
        echo '<li>Controlla che il database sia configurato correttamente in config/database.php</li>';
        echo '<li>Assicurati che le tabelle del database siano state create</li>';
        echo '<li>Verifica i permessi dei file (devono essere leggibili da Apache/PHP)</li>';
        echo '<li>Controlla i log di Apache per errori aggiuntivi</li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div class="test-card" style="border-color: #4CAF50; background: #0a2a0a;">';
        echo '<h2 style="color: #4CAF50;">✅ Sistema Operativo</h2>';
        echo '<p>Tutti gli endpoint API sono funzionanti correttamente!</p>';
        echo '</div>';
    }
    ?>

    <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #333; text-align: center; color: #666;">
        <p>Test eseguito: <?php echo date('Y-m-d H:i:s'); ?></p>
        <p>PHP Version: <?php echo PHP_VERSION; ?></p>
    </div>
</div>
</body>
</html>