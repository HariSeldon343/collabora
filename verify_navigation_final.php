<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifica Finale Navigazione - Nexiosolution Collabora</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
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
            max-width: 800px;
            width: 100%;
        }
        h1 {
            color: #111827;
            font-size: 2.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .status-card {
            background: #f9fafb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .status-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        .status-title {
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }
        .status-value {
            font-size: 0.9em;
            color: #6b7280;
        }
        .exists {
            color: #10b981;
        }
        .missing {
            color: #ef4444;
        }
        .test-section {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
        }
        .test-section h2 {
            color: #111827;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .button-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 1.1em;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 500;
        }
        .btn:hover {
            background: #1d4ed8;
            transform: scale(1.05);
        }
        .btn-success {
            background: #10b981;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-warning {
            background: #f59e0b;
        }
        .btn-warning:hover {
            background: #d97706;
        }
        .results {
            background: #111827;
            color: #10b981;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        .alert {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-icon {
            font-size: 1.5em;
        }
        .success-banner {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.2em;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Verifica Finale Sistema Navigazione</h1>
        <p class="subtitle">Nexiosolution Collabora - Test Completo</p>

        <?php
        $calendar_exists = file_exists('calendar.php');
        $tasks_exists = file_exists('tasks.php');
        $chat_exists = file_exists('chat.php');
        $all_exists = $calendar_exists && $tasks_exists && $chat_exists;
        ?>

        <?php if ($all_exists): ?>
        <div class="success-banner">
            ✅ TUTTI I FILE SONO PRESENTI - IL SISTEMA È PRONTO!
        </div>
        <?php else: ?>
        <div class="alert">
            <span class="alert-icon">⚠️</span>
            <div>
                <strong>Attenzione:</strong> Alcuni file non sono stati trovati.
                Assicurati di essere nella directory corretta.
            </div>
        </div>
        <?php endif; ?>

        <div class="status-grid">
            <div class="status-card">
                <div class="status-icon <?php echo $calendar_exists ? 'exists' : 'missing'; ?>">
                    <?php echo $calendar_exists ? '✅' : '❌'; ?>
                </div>
                <div class="status-title">calendar.php</div>
                <div class="status-value">
                    <?php echo $calendar_exists ? 'File presente' : 'File mancante'; ?>
                </div>
            </div>

            <div class="status-card">
                <div class="status-icon <?php echo $tasks_exists ? 'exists' : 'missing'; ?>">
                    <?php echo $tasks_exists ? '✅' : '❌'; ?>
                </div>
                <div class="status-title">tasks.php</div>
                <div class="status-value">
                    <?php echo $tasks_exists ? 'File presente' : 'File mancante'; ?>
                </div>
            </div>

            <div class="status-card">
                <div class="status-icon <?php echo $chat_exists ? 'exists' : 'missing'; ?>">
                    <?php echo $chat_exists ? '✅' : '❌'; ?>
                </div>
                <div class="status-title">chat.php</div>
                <div class="status-value">
                    <?php echo $chat_exists ? 'File presente' : 'File mancante'; ?>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2>🧪 Test Navigazione Diretta</h2>
            <div class="button-grid">
                <a href="calendar.php" class="btn">
                    📅 Apri Calendario
                </a>
                <a href="tasks.php" class="btn">
                    ✅ Apri Attività
                </a>
                <a href="chat.php" class="btn">
                    💬 Apri Chat
                </a>
            </div>
        </div>

        <div class="test-section">
            <h2>🔑 Accesso al Sistema</h2>
            <div class="button-grid">
                <a href="index_v2.php" class="btn btn-warning">
                    🔐 Vai al Login
                </a>
                <a href="dashboard.php" class="btn btn-success">
                    🏠 Dashboard (richiede login)
                </a>
            </div>
        </div>

        <div class="test-section">
            <h2>📊 Informazioni Sistema</h2>
            <div class="results">
<?php
// File check details
echo "=== FILE SYSTEM CHECK ===\n\n";

$files = ['calendar.php', 'tasks.php', 'chat.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "✅ $file\n";
        echo "   Dimensione: " . number_format($size) . " bytes\n";
        echo "   Modificato: $modified\n\n";
    } else {
        echo "❌ $file - NON TROVATO\n\n";
    }
}

// JavaScript files check
echo "=== JAVASCRIPT FILES ===\n\n";
$js_files = [
    'assets/js/calendar.js',
    'assets/js/tasks.js',
    'assets/js/chat.js'
];

foreach ($js_files as $js) {
    if (file_exists($js)) {
        $size = filesize($js);
        echo "✅ $js (" . number_format($size) . " bytes)\n";
    } else {
        echo "❌ $js - MANCANTE\n";
    }
}

// CSS files check
echo "\n=== CSS FILES ===\n\n";
$css_files = [
    'assets/css/calendar.css',
    'assets/css/tasks.css',
    'assets/css/chat.css'
];

foreach ($css_files as $css) {
    if (file_exists($css)) {
        $size = filesize($css);
        echo "✅ $css (" . number_format($size) . " bytes)\n";
    } else {
        echo "❌ $css - MANCANTE\n";
    }
}

// Session check
echo "\n=== SESSIONE PHP ===\n\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . (isset($_SESSION['user_id']) ? "✅ Loggato (User ID: {$_SESSION['user_id']})" : "⚠️ Non loggato") . "\n";

// Base URL
echo "\n=== CONFIGURAZIONE ===\n\n";
if (defined('BASE_URL')) {
    echo "BASE_URL: " . BASE_URL . "\n";
} else {
    echo "BASE_URL: Non definito (usando default)\n";
}
echo "Current Directory: " . __DIR__ . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
?>
            </div>
        </div>

        <div class="alert">
            <span class="alert-icon">💡</span>
            <div>
                <strong>Suggerimento:</strong> Se i link non funzionano, assicurati di essere loggato.
                Le pagine richiedono autenticazione e reindirizzeranno al login se non sei autenticato.
            </div>
        </div>
    </div>
</body>
</html>