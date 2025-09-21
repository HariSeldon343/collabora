<?php
// Test per verificare che il fix delle sessioni funzioni correttamente
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Session Fix - Nexio Collabora</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 900px;
            width: 100%;
        }
        h1 {
            color: #111827;
            font-size: 2em;
            margin-bottom: 20px;
        }
        .test-section {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        .test-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .test-item:last-child {
            border-bottom: none;
        }
        .success {
            color: #10b981;
            font-weight: bold;
        }
        .error {
            color: #ef4444;
            font-weight: bold;
        }
        .warning {
            color: #f59e0b;
            font-weight: bold;
        }
        .code {
            background: #111827;
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .nav-test {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .nav-link {
            background: #2563eb;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .nav-link:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
        .alert {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }
        .success-banner {
            background: #d1fae5;
            border: 2px solid #10b981;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            font-weight: bold;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Test Session Fix - Verifica Correzione Sessioni</h1>

        <?php
        // Get current session info
        $session_name = session_name();
        $session_id = session_id();
        $expected_name = 'NEXIO_V2_SESSID';
        $session_correct = ($session_name === $expected_name);

        // Check authentication
        $auth = new SimpleAuth();
        $is_authenticated = $auth->isAuthenticated();
        $current_user = $is_authenticated ? $auth->getCurrentUser() : null;
        $current_tenant = $is_authenticated ? $auth->getCurrentTenant() : null;
        ?>

        <?php if ($session_correct && $is_authenticated): ?>
        <div class="success-banner">
            ✅ SESSIONE CONFIGURATA CORRETTAMENTE - SISTEMA PRONTO!
        </div>
        <?php elseif ($session_correct && !$is_authenticated): ?>
        <div class="alert">
            ⚠️ Sessione configurata correttamente ma non sei autenticato. <a href="index_v2.php">Vai al login</a>
        </div>
        <?php else: ?>
        <div class="alert">
            ❌ ERRORE: Nome sessione non corretto. Rilevato: <?php echo htmlspecialchars($session_name); ?>
        </div>
        <?php endif; ?>

        <div class="test-section">
            <h2>📊 Stato Sessione</h2>

            <div class="test-item">
                <span>Nome Sessione:</span>
                <span class="<?php echo $session_correct ? 'success' : 'error'; ?>">
                    <?php echo htmlspecialchars($session_name); ?>
                    <?php echo $session_correct ? '✅' : '❌ (Dovrebbe essere ' . $expected_name . ')'; ?>
                </span>
            </div>

            <div class="test-item">
                <span>ID Sessione:</span>
                <span><?php echo substr($session_id, 0, 20); ?>...</span>
            </div>

            <div class="test-item">
                <span>Autenticazione:</span>
                <span class="<?php echo $is_authenticated ? 'success' : 'warning'; ?>">
                    <?php echo $is_authenticated ? '✅ Autenticato' : '⚠️ Non autenticato'; ?>
                </span>
            </div>

            <?php if ($is_authenticated): ?>
            <div class="test-item">
                <span>Utente:</span>
                <span class="success"><?php echo htmlspecialchars($current_user['email'] ?? 'N/A'); ?></span>
            </div>

            <div class="test-item">
                <span>Tenant:</span>
                <span class="success"><?php echo htmlspecialchars($current_tenant['name'] ?? 'N/A'); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="test-section">
            <h2>🔍 Verifica File Corretti</h2>

            <?php
            $files_to_check = [
                'calendar.php' => 'Calendario',
                'tasks.php' => 'Attività',
                'chat.php' => 'Chat'
            ];

            foreach ($files_to_check as $file => $name) {
                if (file_exists($file)) {
                    $content = file_get_contents($file);
                    $has_session_start_first = (strpos($content, "session_start()") !== false &&
                                               strpos($content, "session_start()") < strpos($content, "require_once 'config_v2.php'"));

                    echo '<div class="test-item">';
                    echo '<span>' . $name . ' (' . $file . '):</span>';
                    if ($has_session_start_first) {
                        echo '<span class="error">❌ session_start() prima di config_v2.php</span>';
                    } else {
                        echo '<span class="success">✅ Ordine corretto</span>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="test-item">';
                    echo '<span>' . $name . ' (' . $file . '):</span>';
                    echo '<span class="error">❌ File non trovato</span>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <?php if ($is_authenticated): ?>
        <div class="test-section">
            <h2>🧪 Test Navigazione Diretta</h2>
            <p>Clicca sui link per verificare che la navigazione funzioni correttamente:</p>

            <div class="nav-test">
                <a href="calendar.php" class="nav-link">
                    📅 Calendario
                </a>
                <a href="tasks.php" class="nav-link">
                    ✅ Attività
                </a>
                <a href="chat.php" class="nav-link">
                    💬 Chat
                </a>
                <a href="dashboard.php" class="nav-link">
                    🏠 Dashboard
                </a>
            </div>
        </div>

        <div class="test-section">
            <h2>🔗 Test da Admin</h2>
            <p>Testa la navigazione dalla directory admin:</p>

            <div class="nav-test">
                <a href="admin/index.php" class="nav-link" style="background: #f59e0b;">
                    🔑 Admin Panel
                </a>
                <a href="admin/test_direct_links.html" class="nav-link" style="background: #8b5cf6;">
                    🧪 Test Links Admin
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="test-section">
            <h2>📝 Dettagli Tecnici</h2>
            <div class="code">
<?php
echo "=== CONFIGURAZIONE PHP ===\n";
echo "session.name (php.ini): " . ini_get('session.name') . "\n";
echo "session.name (current): " . session_name() . "\n";
echo "session.save_path: " . session_save_path() . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n\n";

echo "=== COSTANTI DEFINITE ===\n";
echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NON DEFINITO') . "\n";
echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NON DEFINITO') . "\n\n";

echo "=== VARIABILI SESSIONE ===\n";
if (!empty($_SESSION)) {
    foreach ($_SESSION as $key => $value) {
        if (is_array($value)) {
            echo "$key: [array with " . count($value) . " elements]\n";
        } else {
            echo "$key: " . (is_string($value) ? substr($value, 0, 50) : var_export($value, true)) . "\n";
        }
    }
} else {
    echo "Sessione vuota\n";
}
?>
            </div>
        </div>

        <div class="alert">
            <strong>🔧 Come risolvere problemi di sessione:</strong><br>
            1. Assicurati che config_v2.php sia incluso PRIMA di qualsiasi session_start()<br>
            2. Verifica che SimpleAuth gestisca la sessione automaticamente<br>
            3. Non chiamare session_start() manualmente se usi SimpleAuth<br>
            4. Pulisci i cookie del browser se persistono problemi
        </div>
    </div>
</body>
</html>