<?php
/**
 * Script di verifica componenti Parte 2
 * Nexiosolution Calendar & Task Management
 *
 * @version 2.1.0
 * @date 2025-01-19
 */

header('Content-Type: text/html; charset=UTF-8');

// Configurazione
$components = [
    'database' => [
        'title' => 'Database Tables',
        'items' => [
            'calendars' => 'Tabella calendari',
            'events' => 'Tabella eventi',
            'event_participants' => 'Tabella partecipanti eventi',
            'event_reminders' => 'Tabella promemoria eventi',
            'calendar_shares' => 'Tabella condivisioni calendario',
            'event_attachments' => 'Tabella allegati eventi',
            'task_lists' => 'Tabella liste attività',
            'tasks' => 'Tabella attività',
            'task_assignments' => 'Tabella assegnazioni',
            'task_comments' => 'Tabella commenti',
            'task_time_logs' => 'Tabella time tracking',
            'task_attachments' => 'Tabella allegati task'
        ]
    ],
    'api' => [
        'title' => 'API Endpoints',
        'items' => [
            '/api/calendars.php' => 'Calendar API',
            '/api/events.php' => 'Events API',
            '/api/tasks.php' => 'Tasks API',
            '/api/task-lists.php' => 'Task Lists API'
        ]
    ],
    'frontend' => [
        'title' => 'Frontend Pages',
        'items' => [
            '/calendar.php' => 'Pagina Calendario',
            '/tasks.php' => 'Pagina Attività'
        ]
    ],
    'javascript' => [
        'title' => 'JavaScript Modules',
        'items' => [
            '/assets/js/calendar.js' => 'Calendar JS',
            '/assets/js/tasks.js' => 'Tasks JS'
        ]
    ],
    'css' => [
        'title' => 'Stylesheets',
        'items' => [
            '/assets/css/calendar.css' => 'Calendar CSS',
            '/assets/css/tasks.css' => 'Tasks CSS'
        ]
    ],
    'includes' => [
        'title' => 'PHP Classes',
        'items' => [
            '/includes/CalendarManager.php' => 'CalendarManager Class',
            '/includes/TaskManager.php' => 'TaskManager Class'
        ]
    ]
];

// Funzioni di verifica
function checkDatabase($table) {
    try {
        $db = new PDO('mysql:host=localhost;dbname=nexio_collabora_v2', 'root', '');
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

function checkFile($path) {
    $basePath = dirname(__FILE__);
    return file_exists($basePath . $path);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function getFileInfo($path) {
    $basePath = dirname(__FILE__);
    $fullPath = $basePath . $path;
    if (file_exists($fullPath)) {
        return [
            'size' => formatFileSize(filesize($fullPath)),
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
        ];
    }
    return null;
}

// Calcola statistiche
$stats = [
    'total' => 0,
    'passed' => 0,
    'failed' => 0
];
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexiosolution Parte 2 - Verifica Componenti</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #1F2937;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #6B7280;
            font-size: 1rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-card .label {
            color: #6B7280;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-card.total .value { color: #3B82F6; }
        .stat-card.passed .value { color: #10B981; }
        .stat-card.failed .value { color: #EF4444; }

        .component-section {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .component-section h2 {
            color: #1F2937;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #E5E7EB;
        }

        .component-grid {
            display: grid;
            gap: 0.75rem;
        }

        .component-item {
            display: grid;
            grid-template-columns: 30px 1fr auto;
            align-items: center;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: #F9FAFB;
            transition: all 0.2s;
        }

        .component-item:hover {
            background: #F3F4F6;
            transform: translateX(5px);
        }

        .status-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        .status-icon.success {
            background: #10B981;
        }

        .status-icon.error {
            background: #EF4444;
        }

        .component-name {
            font-weight: 500;
            color: #374151;
            margin-left: 1rem;
        }

        .component-info {
            display: flex;
            gap: 1rem;
            align-items: center;
            color: #6B7280;
            font-size: 0.875rem;
        }

        .component-info .size {
            background: #EFF6FF;
            color: #3B82F6;
            padding: 0.25rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
        }

        .component-info .date {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }

        .btn {
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #3B82F6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563EB;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #E5E7EB;
        }

        .btn-secondary:hover {
            background: #F9FAFB;
            border-color: #D1D5DB;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #E5E7EB;
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10B981, #34D399);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        .footer {
            text-align: center;
            color: white;
            margin-top: 3rem;
            padding: 1rem;
        }

        @keyframes checkmark {
            0% { transform: scale(0) rotate(-45deg); }
            50% { transform: scale(1.2) rotate(-45deg); }
            100% { transform: scale(1) rotate(-45deg); }
        }

        .status-icon.success::after {
            content: '✓';
            animation: checkmark 0.3s ease;
        }

        .status-icon.error::after {
            content: '✗';
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Nexiosolution Parte 2 - Verifica Sistema</h1>
            <p>Calendar & Task Management - Verifica componenti installati</p>
        </div>

        <?php
        // Prima passa: calcola statistiche
        foreach ($components as $type => $section) {
            foreach ($section['items'] as $path => $name) {
                $stats['total']++;
                if ($type === 'database') {
                    if (checkDatabase($path)) {
                        $stats['passed']++;
                    } else {
                        $stats['failed']++;
                    }
                } else {
                    if (checkFile($path)) {
                        $stats['passed']++;
                    } else {
                        $stats['failed']++;
                    }
                }
            }
        }
        $successRate = $stats['total'] > 0 ? round(($stats['passed'] / $stats['total']) * 100) : 0;
        ?>

        <div class="stats">
            <div class="stat-card total">
                <div class="value"><?= $stats['total'] ?></div>
                <div class="label">Componenti Totali</div>
            </div>
            <div class="stat-card passed">
                <div class="value"><?= $stats['passed'] ?></div>
                <div class="label">Verificati</div>
            </div>
            <div class="stat-card failed">
                <div class="value"><?= $stats['failed'] ?></div>
                <div class="label">Mancanti</div>
            </div>
        </div>

        <div class="component-section">
            <h2>Progresso Installazione</h2>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $successRate ?>%"></div>
            </div>
            <p style="text-align: center; color: #6B7280; margin-top: 0.5rem;">
                <?= $successRate ?>% completato
            </p>
        </div>

        <?php foreach ($components as $type => $section): ?>
        <div class="component-section">
            <h2><?= $section['title'] ?></h2>
            <div class="component-grid">
                <?php foreach ($section['items'] as $path => $name): ?>
                    <?php
                    if ($type === 'database') {
                        $exists = checkDatabase($path);
                        $info = null;
                    } else {
                        $exists = checkFile($path);
                        $info = $exists ? getFileInfo($path) : null;
                    }
                    ?>
                    <div class="component-item">
                        <div class="status-icon <?= $exists ? 'success' : 'error' ?>"></div>
                        <div class="component-name"><?= $name ?></div>
                        <div class="component-info">
                            <?php if ($info): ?>
                                <span class="size"><?= $info['size'] ?></span>
                                <span class="date">
                                    📅 <?= $info['modified'] ?>
                                </span>
                            <?php elseif ($exists && $type === 'database'): ?>
                                <span class="size">Tabella OK</span>
                            <?php else: ?>
                                <span style="color: #EF4444;">Non trovato</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="action-buttons">
            <?php if ($stats['failed'] > 0): ?>
                <button class="btn btn-primary" onclick="window.location.href='deploy_part2.bat'">
                    🔧 Esegui Deployment
                </button>
            <?php else: ?>
                <a href="calendar.php" class="btn btn-primary">
                    📅 Apri Calendario
                </a>
                <a href="tasks.php" class="btn btn-primary">
                    ✅ Apri Attività
                </a>
            <?php endif; ?>
            <button class="btn btn-secondary" onclick="window.location.reload()">
                🔄 Aggiorna
            </button>
            <a href="test_part2_system.php" class="btn btn-secondary">
                🧪 Esegui Test
            </a>
        </div>

        <div class="footer">
            <p>Nexiosolution v2.1.0 - Calendar & Task Management</p>
            <p style="opacity: 0.8; font-size: 0.875rem;">Verificato: <?= date('Y-m-d H:i:s') ?></p>
        </div>
    </div>

    <script>
        // Auto-refresh ogni 5 secondi se ci sono componenti mancanti
        <?php if ($stats['failed'] > 0): ?>
        setTimeout(() => {
            window.location.reload();
        }, 5000);
        <?php endif; ?>

        // Animazione progress bar
        window.addEventListener('load', () => {
            const progressFill = document.querySelector('.progress-fill');
            progressFill.style.width = '0%';
            setTimeout(() => {
                progressFill.style.width = '<?= $successRate ?>%';
            }, 100);
        });
    </script>
</body>
</html>