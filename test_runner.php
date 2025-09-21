<?php
/**
 * Nexio Collabora - Test Runner Unificato
 *
 * Questo script permette di eseguire tutti i test disponibili
 * sia da CLI che da browser con controlli di sicurezza
 *
 * @version 2.0
 * @author DevOps Platform Engineer
 */

// Configurazione ambiente
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 300); // 5 minuti per test completi

// Determinazione modalità di esecuzione
define('IS_CLI', php_sapi_name() === 'cli');
define('IS_DEV', $_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');

// Sicurezza: blocca in produzione
if (!IS_DEV && !IS_CLI) {
    header('HTTP/1.1 403 Forbidden');
    die('Test runner non disponibile in ambiente di produzione');
}

// Path base
define('BASE_PATH', __DIR__);
define('TESTS_PATH', BASE_PATH . '/tests');

/**
 * Classe TestRunner
 * Gestisce l'esecuzione e il reporting dei test
 */
class TestRunner {
    private $tests = [];
    private $results = [];
    private $startTime;
    private $categories = [
        'auth' => 'Autenticazione e Sessioni',
        'api' => 'Endpoint API',
        'db' => 'Database e Connettività',
        'ui' => 'Interfaccia Utente',
        'system' => 'Sistema e Configurazione',
        'security' => 'Sicurezza',
        'integration' => 'Integrazione',
        'migration' => 'Migrazione Dati'
    ];

    public function __construct() {
        $this->startTime = microtime(true);
        $this->discoverTests();
    }

    /**
     * Scopre tutti i test disponibili
     */
    private function discoverTests() {
        // Test nella root
        $rootTests = glob(BASE_PATH . '/test*.php');
        $verifyTests = glob(BASE_PATH . '/verify*.php');
        $checkTests = glob(BASE_PATH . '/check*.php');

        // Test nella directory tests
        $testDirTests = glob(TESTS_PATH . '/**/*.php');

        $allTests = array_merge($rootTests, $verifyTests, $checkTests, $testDirTests);

        foreach ($allTests as $testFile) {
            // Salta questo file stesso
            if (basename($testFile) === 'test_runner.php') continue;

            $this->tests[] = [
                'file' => $testFile,
                'name' => basename($testFile, '.php'),
                'path' => str_replace(BASE_PATH . '/', '', $testFile),
                'category' => $this->detectCategory($testFile),
                'description' => $this->extractDescription($testFile)
            ];
        }

        // Ordina per categoria e nome
        usort($this->tests, function($a, $b) {
            $catCmp = strcmp($a['category'], $b['category']);
            return $catCmp === 0 ? strcmp($a['name'], $b['name']) : $catCmp;
        });
    }

    /**
     * Rileva la categoria del test dal nome file
     */
    private function detectCategory($file) {
        $name = basename($file);

        if (strpos($name, 'auth') !== false || strpos($name, 'login') !== false) {
            return 'auth';
        } elseif (strpos($name, 'api') !== false || strpos($name, 'endpoint') !== false) {
            return 'api';
        } elseif (strpos($name, 'db') !== false || strpos($name, 'database') !== false) {
            return 'db';
        } elseif (strpos($name, 'ui') !== false || strpos($name, 'menu') !== false) {
            return 'ui';
        } elseif (strpos($name, 'security') !== false) {
            return 'security';
        } elseif (strpos($name, 'migration') !== false || strpos($name, 'part') !== false) {
            return 'migration';
        } elseif (strpos($name, 'integration') !== false || strpos($name, 'system') !== false) {
            return 'integration';
        }

        return 'system';
    }

    /**
     * Estrae descrizione dal file di test
     */
    private function extractDescription($file) {
        $content = @file_get_contents($file);
        if (!$content) return 'Nessuna descrizione disponibile';

        // Cerca commenti PHPDoc
        if (preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\n/s', $content, $matches)) {
            return trim($matches[1]);
        }

        // Cerca commento singola riga
        if (preg_match('/^\/\/\s*(.+?)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return 'Test script';
    }

    /**
     * Esegue un singolo test
     */
    public function runTest($testFile) {
        $startTime = microtime(true);
        $output = '';
        $success = false;
        $error = '';

        try {
            // Cattura output
            ob_start();
            $exitCode = 0;

            // Esegui test tramite CLI per isolamento
            $cmd = sprintf('php %s 2>&1', escapeshellarg($testFile));
            $output = shell_exec($cmd);

            // Analizza output per determinare successo
            $success = $this->analyzeTestOutput($output);

            $duration = microtime(true) - $startTime;

            return [
                'success' => $success,
                'output' => $output,
                'duration' => $duration,
                'error' => $error
            ];

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            return [
                'success' => false,
                'output' => ob_get_clean(),
                'duration' => $duration,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analizza output per determinare successo del test
     */
    private function analyzeTestOutput($output) {
        $outputLower = strtolower($output);

        // Indicatori di errore
        $errorIndicators = [
            'error', 'failed', 'failure', 'exception',
            'fatal', 'warning', 'deprecated'
        ];

        // Indicatori di successo
        $successIndicators = [
            'success', 'passed', 'ok', 'completed',
            'test completato', 'test superato'
        ];

        // Conta indicatori
        $errorCount = 0;
        $successCount = 0;

        foreach ($errorIndicators as $indicator) {
            $errorCount += substr_count($outputLower, $indicator);
        }

        foreach ($successIndicators as $indicator) {
            $successCount += substr_count($outputLower, $indicator);
        }

        // Se ci sono più errori che successi, è fallito
        if ($errorCount > $successCount) {
            return false;
        }

        // Se non ci sono indicatori, controlla se c'è output
        if ($errorCount === 0 && $successCount === 0) {
            return strlen(trim($output)) > 0;
        }

        return true;
    }

    /**
     * Esegue tutti i test
     */
    public function runAll() {
        foreach ($this->tests as &$test) {
            $result = $this->runTest($test['file']);
            $test['result'] = $result;
            $this->results[] = array_merge($test, $result);
        }
    }

    /**
     * Esegue test per categoria
     */
    public function runCategory($category) {
        foreach ($this->tests as &$test) {
            if ($test['category'] === $category) {
                $result = $this->runTest($test['file']);
                $test['result'] = $result;
                $this->results[] = array_merge($test, $result);
            }
        }
    }

    /**
     * Ottiene statistiche dei test
     */
    public function getStats() {
        $total = count($this->results);
        $passed = count(array_filter($this->results, function($r) { return $r['success']; }));
        $failed = $total - $passed;
        $duration = microtime(true) - $this->startTime;

        return [
            'total' => $total,
            'passed' => $passed,
            'failed' => $failed,
            'duration' => $duration,
            'success_rate' => $total > 0 ? ($passed / $total * 100) : 0
        ];
    }

    /**
     * Genera report HTML
     */
    public function generateHTMLReport() {
        $stats = $this->getStats();
        $html = <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nexio Collabora - Test Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .stat-card .label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }
        .stat-card.success .value { color: #10b981; }
        .stat-card.error .value { color: #ef4444; }
        .stat-card.info .value { color: #3b82f6; }
        .stat-card.warning .value { color: #f59e0b; }

        .controls {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .controls h2 {
            margin-bottom: 15px;
            color: #333;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-category {
            background: #f3f4f6;
            color: #333;
        }
        .btn-category:hover {
            background: #e5e7eb;
        }

        .test-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .test-list h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .test-category {
            margin-bottom: 30px;
        }
        .test-category h3 {
            color: #666;
            font-size: 16px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }
        .test-item {
            background: #f9fafb;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .test-item:hover {
            background: #f3f4f6;
        }
        .test-item.success {
            border-left: 4px solid #10b981;
        }
        .test-item.error {
            border-left: 4px solid #ef4444;
        }
        .test-item.pending {
            border-left: 4px solid #6b7280;
        }
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        .test-name {
            font-weight: bold;
            color: #333;
        }
        .test-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .test-status.success {
            background: #10b981;
            color: white;
        }
        .test-status.error {
            background: #ef4444;
            color: white;
        }
        .test-status.pending {
            background: #6b7280;
            color: white;
        }
        .test-details {
            color: #666;
            font-size: 13px;
        }
        .test-output {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #1f2937;
            color: #f3f4f6;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
        .test-output.show {
            display: block;
        }

        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e5e7eb;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            transition: width 0.5s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 Nexio Collabora - Test Report</h1>
            <div class="subtitle">Generato il {$this->formatDate()}</div>
        </div>

        <div class="stats">
            <div class="stat-card info">
                <div class="label">Test Totali</div>
                <div class="value">{$stats['total']}</div>
            </div>
            <div class="stat-card success">
                <div class="label">Superati</div>
                <div class="value">{$stats['passed']}</div>
            </div>
            <div class="stat-card error">
                <div class="label">Falliti</div>
                <div class="value">{$stats['failed']}</div>
            </div>
            <div class="stat-card warning">
                <div class="label">Tasso Successo</div>
                <div class="value">{$this->formatPercentage($stats['success_rate'])}</div>
            </div>
            <div class="stat-card info">
                <div class="label">Durata</div>
                <div class="value">{$this->formatDuration($stats['duration'])}</div>
            </div>
        </div>

        <div class="controls">
            <h2>Controlli Test</h2>
            <div class="btn-group">
                <button class="btn btn-primary" onclick="runAllTests()">
                    ▶️ Esegui Tutti i Test
                </button>
                <button class="btn btn-success" onclick="window.location.reload()">
                    🔄 Aggiorna Report
                </button>
HTML;

        // Aggiungi bottoni per categorie
        foreach ($this->categories as $cat => $label) {
            $html .= <<<HTML
                <button class="btn btn-category" onclick="runCategory('{$cat}')">
                    {$label}
                </button>
HTML;
        }

        $html .= <<<HTML
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: {$stats['success_rate']}%"></div>
            </div>
        </div>

        <div class="test-list">
            <h2>Dettagli Test</h2>
HTML;

        // Raggruppa test per categoria
        $testsByCategory = [];
        foreach ($this->tests as $test) {
            $cat = $test['category'];
            if (!isset($testsByCategory[$cat])) {
                $testsByCategory[$cat] = [];
            }
            $testsByCategory[$cat][] = $test;
        }

        // Genera HTML per ogni categoria
        foreach ($testsByCategory as $cat => $tests) {
            $catLabel = $this->categories[$cat] ?? ucfirst($cat);
            $html .= "<div class='test-category'>";
            $html .= "<h3>{$catLabel}</h3>";

            foreach ($tests as $test) {
                $hasResult = isset($test['result']);
                $status = $hasResult ? ($test['result']['success'] ? 'success' : 'error') : 'pending';
                $statusText = $hasResult ? ($test['result']['success'] ? 'PASS' : 'FAIL') : 'PENDING';

                $html .= <<<HTML
                <div class="test-item {$status}" onclick="toggleOutput('{$test['name']}')">
                    <div class="test-header">
                        <div class="test-name">{$test['name']}</div>
                        <div class="test-status {$status}">{$statusText}</div>
                    </div>
                    <div class="test-details">
                        <div>📁 {$test['path']}</div>
                        <div>📝 {$test['description']}</div>
HTML;

                if ($hasResult) {
                    $duration = $this->formatDuration($test['result']['duration']);
                    $html .= "<div>⏱️ {$duration}</div>";
                }

                $html .= "</div>";

                if ($hasResult && !empty($test['result']['output'])) {
                    $output = htmlspecialchars($test['result']['output']);
                    $html .= "<div class='test-output' id='output-{$test['name']}'>{$output}</div>";
                }

                $html .= "</div>";
            }

            $html .= "</div>";
        }

        $html .= <<<HTML
        </div>
    </div>

    <script>
        function toggleOutput(testName) {
            const output = document.getElementById('output-' + testName);
            if (output) {
                output.classList.toggle('show');
            }
        }

        function runAllTests() {
            if (confirm('Eseguire tutti i test? Questo potrebbe richiedere diversi minuti.')) {
                window.location.href = '?action=run_all';
            }
        }

        function runCategory(category) {
            if (confirm('Eseguire tutti i test della categoria ' + category + '?')) {
                window.location.href = '?action=run_category&category=' + category;
            }
        }
    </script>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Genera report CLI
     */
    public function generateCLIReport() {
        $stats = $this->getStats();
        $output = "\n";
        $output .= "╔══════════════════════════════════════════════════════════════╗\n";
        $output .= "║           NEXIO COLLABORA - TEST REPORT                       ║\n";
        $output .= "╚══════════════════════════════════════════════════════════════╝\n\n";

        $output .= "📊 STATISTICHE:\n";
        $output .= "├─ Test Totali: {$stats['total']}\n";
        $output .= "├─ Superati: \033[32m{$stats['passed']}\033[0m\n";
        $output .= "├─ Falliti: \033[31m{$stats['failed']}\033[0m\n";
        $output .= "├─ Tasso Successo: {$this->formatPercentage($stats['success_rate'])}\n";
        $output .= "└─ Durata: {$this->formatDuration($stats['duration'])}\n\n";

        $output .= "📋 DETTAGLI TEST:\n";
        $output .= str_repeat("─", 70) . "\n";

        foreach ($this->results as $result) {
            $status = $result['success'] ? "\033[32m✓ PASS\033[0m" : "\033[31m✗ FAIL\033[0m";
            $output .= sprintf("%-50s %s\n", $result['name'], $status);

            if (!$result['success'] && !empty($result['error'])) {
                $output .= "  └─ Errore: {$result['error']}\n";
            }
        }

        return $output;
    }

    /**
     * Utility: formatta data
     */
    private function formatDate() {
        return date('d/m/Y H:i:s');
    }

    /**
     * Utility: formatta durata
     */
    private function formatDuration($seconds) {
        if ($seconds < 1) {
            return round($seconds * 1000) . 'ms';
        } elseif ($seconds < 60) {
            return round($seconds, 2) . 's';
        } else {
            $minutes = floor($seconds / 60);
            $seconds = round($seconds % 60);
            return "{$minutes}m {$seconds}s";
        }
    }

    /**
     * Utility: formatta percentuale
     */
    private function formatPercentage($value) {
        return round($value, 1) . '%';
    }

    /**
     * Getter per i test
     */
    public function getTests() {
        return $this->tests;
    }

    /**
     * Getter per i risultati
     */
    public function getResults() {
        return $this->results;
    }
}

// ============================================================================
// ESECUZIONE PRINCIPALE
// ============================================================================

$runner = new TestRunner();

// Gestione richieste
if (IS_CLI) {
    // Modalità CLI
    $action = $argv[1] ?? 'list';

    switch ($action) {
        case 'run':
        case 'run_all':
            echo "Esecuzione di tutti i test...\n";
            $runner->runAll();
            echo $runner->generateCLIReport();
            break;

        case 'list':
            echo "\n📦 TEST DISPONIBILI:\n";
            echo str_repeat("─", 70) . "\n";
            foreach ($runner->getTests() as $test) {
                echo sprintf("%-30s %s\n", $test['name'], $test['path']);
            }
            echo "\n";
            echo "Usa: php test_runner.php run       per eseguire tutti i test\n";
            echo "Usa: php test_runner.php <category> per eseguire test per categoria\n";
            break;

        default:
            // Prova come categoria
            echo "Esecuzione test categoria: {$action}\n";
            $runner->runCategory($action);
            echo $runner->generateCLIReport();
    }
} else {
    // Modalità Web
    $action = $_GET['action'] ?? 'report';
    $category = $_GET['category'] ?? null;

    switch ($action) {
        case 'run_all':
            $runner->runAll();
            header('Location: test_runner.php');
            exit;

        case 'run_category':
            if ($category) {
                $runner->runCategory($category);
            }
            header('Location: test_runner.php');
            exit;

        case 'api':
            // Endpoint API per test AJAX
            header('Content-Type: application/json');
            $runner->runAll();
            echo json_encode([
                'success' => true,
                'stats' => $runner->getStats(),
                'results' => $runner->getResults()
            ], JSON_PRETTY_PRINT);
            break;

        default:
            // Mostra report HTML
            echo $runner->generateHTMLReport();
    }
}