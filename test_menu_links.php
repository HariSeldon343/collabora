<?php
/**
 * Test per verificare i link del menu e l'assenza di redirect loop
 * Verifica spazi negli URL e pattern di concatenazione corretti
 */

require_once 'config_v2.php';

// Test configuration
$test_results = [];
$base_url = rtrim(defined('BASE_URL') ? BASE_URL : '/Nexiosolution/collabora', '/');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Menu Links - Verifica Spazi e Concatenazione</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 2px solid #2563EB; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; font-weight: bold; }
        .success { color: #10B981; font-weight: bold; }
        .error { color: #EF4444; font-weight: bold; }
        .warning { color: #F59E0B; font-weight: bold; }
        .info { background: #EFF6FF; padding: 15px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #2563EB; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
        .test-section { margin: 30px 0; padding: 20px; border: 1px solid #e5e5e5; border-radius: 8px; }
        .pattern-example { background: #f9f9f9; padding: 10px; margin: 5px 0; border-left: 3px solid #ccc; }
        .pattern-good { border-left-color: #10B981; }
        .pattern-bad { border-left-color: #EF4444; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Test Link Menu - Nexio Collabora</h1>

        <div class='info'>
            <strong>BASE_URL configurata:</strong> <code><?php echo htmlspecialchars($base_url); ?></code><br>
            <strong>BASE_URL (costante raw):</strong> <code><?php echo defined('BASE_URL') ? htmlspecialchars(BASE_URL) : 'NON DEFINITA'; ?></code><br>
            <strong>Test eseguito:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
            <strong>Controlli attivi:</strong> Spazi negli URL, doppi slash, duplicazioni admin/admin, pattern di concatenazione
        </div>

        <div class='test-section'>
            <h2>📋 Test 1: Verifica Link Generati</h2>
            <table>
                <tr>
                    <th>Pagina</th>
                    <th>URL Generato</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Problemi</th>
                </tr>
<?php
// Lista dei link da testare
$menu_links = [
    'Dashboard' => '/index_v2.php',
    'File Manager' => '/files.php',
    'Condivisi' => '/shared.php',
    'Calendario' => '/calendar.php',
    'Attività' => '/tasks.php',
    'Chat' => '/chat.php',
    'Admin Dashboard' => '/admin/index.php',
    'Gestione Utenti' => '/admin/users.php',
    'Gestione Tenant' => '/admin/tenants.php',
    'Log di Sistema' => '/admin/logs.php',
    'Profilo' => '/profile.php',
    'Impostazioni' => '/settings.php',
];

$total_errors = 0;
foreach ($menu_links as $name => $path) {
    $full_url = $base_url . $path;
    $type = strpos($path, '/admin/') !== false ? '<span style="color: #8B5CF6;">Admin</span>' : 'Normal';
    $errors = [];

    // Check 1: Duplicazione admin/admin
    if (preg_match('/admin\/admin/', $full_url)) {
        $errors[] = "Path duplicato admin/admin";
    }

    // Check 2: Spazi prima dello slash
    if (preg_match('/\s+\//', $full_url)) {
        $errors[] = "Spazio prima dello slash";
    }

    // Check 3: Doppi slash (eccetto dopo http:// o https://)
    if (preg_match('/(?<!:)\/\//', $full_url)) {
        $errors[] = "Doppio slash nell'URL";
    }

    // Check 4: Spazi nell'URL
    if (preg_match('/\s/', $full_url)) {
        $errors[] = "Presenza di spazi nell'URL";
    }

    if (empty($errors)) {
        $status = "<span class='success'>✓ OK</span>";
        $error_text = '-';
    } else {
        $status = "<span class='error'>✗ ERRORE</span>";
        $error_text = "<span class='error'>" . implode(', ', $errors) . "</span>";
        $total_errors++;
    }

    echo "<tr>
            <td>$name</td>
            <td><code>" . htmlspecialchars($full_url) . "</code></td>
            <td>$type</td>
            <td>$status</td>
            <td>$error_text</td>
          </tr>";
}
?>
            </table>
            <?php if ($total_errors > 0): ?>
            <p class='error'>⚠️ Trovati <?php echo $total_errors; ?> errori nei link generati</p>
            <?php else: ?>
            <p class='success'>✅ Tutti i link sono generati correttamente!</p>
            <?php endif; ?>
        </div>

        <div class='test-section'>
            <h2>📁 Test 2: Analisi File per Pattern Problematici</h2>
            <table>
                <tr>
                    <th>File</th>
                    <th>Pattern Cercato</th>
                    <th>Trovato</th>
                    <th>Status</th>
                </tr>
<?php
$files_to_check = [
    'admin/index.php' => 'Admin Dashboard',
    'dashboard.php' => 'Dashboard',
    'home_v2.php' => 'Home Page',
    'validate_session.php' => 'Session Validator',
    'components/sidebar.php' => 'Sidebar Component'
];

$file_errors = 0;
foreach ($files_to_check as $file => $description) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $problems = [];

        // Pattern problematici da cercare
        $bad_patterns = [
            '/echo\s+BASE_URL\s*;?\s*\?>\s+\//' => 'BASE_URL con spazio prima di /',
            '/echo\s+BASE_URL\s*\?>\s*\//' => 'BASE_URL senza punto e virgola',
            '/href="\s+\//' => 'href con spazio prima dello slash',
            '/\$base_url\s+\.\s*"\//' => 'Concatenazione con spazio extra',
        ];

        foreach ($bad_patterns as $pattern => $description_pattern) {
            if (preg_match($pattern, $content)) {
                $problems[] = $description_pattern;
            }
        }

        // Verifica pattern corretto
        $has_correct_pattern = false;
        if (preg_match('/\$baseUrl\s*\.\s*[\'"]\//', $content) ||
            preg_match('/\$base_url\s*\.\s*[\'"]\//', $content)) {
            $has_correct_pattern = true;
        }

        if (!empty($problems)) {
            $file_errors++;
            echo "<tr>
                    <td><code>$file</code><br><small>$description</small></td>
                    <td>" . implode('<br>', $problems) . "</td>
                    <td><span class='error'>Sì</span></td>
                    <td><span class='error'>✗ Richiede fix</span></td>
                  </tr>";
        } else {
            $pattern_info = $has_correct_pattern ? "Usa concatenazione corretta" : "Nessun pattern BASE_URL";
            echo "<tr>
                    <td><code>$file</code><br><small>$description</small></td>
                    <td>$pattern_info</td>
                    <td><span class='success'>No</span></td>
                    <td><span class='success'>✓ OK</span></td>
                  </tr>";
        }
    } else {
        echo "<tr>
                <td><code>$file</code><br><small>$description</small></td>
                <td colspan='3'><span class='warning'>File non trovato</span></td>
              </tr>";
    }
}
?>
            </table>
            <?php if ($file_errors > 0): ?>
            <p class='error'>⚠️ Trovati pattern problematici in <?php echo $file_errors; ?> file</p>
            <?php else: ?>
            <p class='success'>✅ Nessun pattern problematico trovato nei file!</p>
            <?php endif; ?>
        </div>

        <div class='test-section'>
            <h2>🔧 Test 3: Verifica Sidebar Runtime</h2>
<?php
// Salva stato originale
$original_server = $_SERVER;
$original_session = $_SESSION;

// Simula ambiente admin
$_SERVER['PHP_SELF'] = '/Nexiosolution/collabora/admin/index.php';
$_SERVER['REQUEST_URI'] = '/Nexiosolution/collabora/admin/index.php';
$_SESSION['user_role'] = 'admin';
$_SESSION['user_name'] = 'Test Admin';
$_SESSION['tenants'] = [];
$_SESSION['current_tenant_id'] = 1;

// Cattura output sidebar
ob_start();
@include 'components/sidebar.php';
$sidebar_output = ob_get_clean();

// Estrai link dal sidebar
preg_match_all('/href="([^"]+)"/', $sidebar_output, $matches);
$sidebar_links = $matches[1] ?? [];

// Ripristina stato
$_SERVER = $original_server;
$_SESSION = $original_session;
?>
            <table>
                <tr>
                    <th>Link Estratto</th>
                    <th>Tipo</th>
                    <th>Problemi</th>
                    <th>Status</th>
                </tr>
<?php
$sidebar_errors = 0;
foreach ($sidebar_links as $link) {
    // Skip JavaScript links
    if (strpos($link, '#') === 0 || strpos($link, 'javascript:') === 0) {
        continue;
    }

    $problems = [];
    $link_type = 'Normal';

    // Determina tipo di link
    if (strpos($link, '/admin/') !== false) {
        $link_type = '<span style="color: #8B5CF6;">Admin</span>';
    }

    if (strpos($link, 'http') === 0) {
        $link_type = '<span style="color: #2563EB;">Assoluto</span>';
    }

    // Verifica problemi
    if (preg_match('/\s+\//', $link)) {
        $problems[] = "Spazio prima dello slash";
    }

    if (preg_match('/admin\/admin/', $link)) {
        $problems[] = "Duplicazione admin/admin";
    }

    if (preg_match('/(?<!:)\/\//', $link)) {
        $problems[] = "Doppio slash";
    }

    if (preg_match('/\s/', $link)) {
        $problems[] = "Contiene spazi";
    }

    if (empty($problems)) {
        $status = "<span class='success'>✓ OK</span>";
        $problem_text = '-';
    } else {
        $status = "<span class='error'>✗ ERRORE</span>";
        $problem_text = "<span class='error'>" . implode(', ', $problems) . "</span>";
        $sidebar_errors++;
    }

    echo "<tr>
            <td><code>" . htmlspecialchars($link) . "</code></td>
            <td>$link_type</td>
            <td>$problem_text</td>
            <td>$status</td>
          </tr>";
}
?>
            </table>
            <?php if ($sidebar_errors > 0): ?>
            <p class='error'>⚠️ Trovati <?php echo $sidebar_errors; ?> problemi nei link del sidebar</p>
            <?php else: ?>
            <p class='success'>✅ Tutti i link del sidebar sono corretti!</p>
            <?php endif; ?>
        </div>

        <div class='test-section'>
            <h2>📚 Guida: Pattern Corretti vs Errati</h2>

            <h3>✅ Pattern CORRETTI (da usare):</h3>
            <div class='pattern-example pattern-good'>
                <code>&lt;?php $baseUrl = rtrim(BASE_URL, '/'); ?&gt;</code><br>
                <code>&lt;a href="&lt;?php echo $baseUrl . '/admin/users.php'; ?&gt;"&gt;</code>
            </div>

            <h3>❌ Pattern ERRATI (da evitare):</h3>
            <div class='pattern-example pattern-bad'>
                <code>&lt;a href="&lt;?php echo BASE_URL; ?&gt;/admin/users.php"&gt;</code><br>
                <small>Problema: Può creare spazi tra PHP tag e slash</small>
            </div>

            <div class='pattern-example pattern-bad'>
                <code>&lt;a href="&lt;?php echo BASE_URL ?&gt;/admin/users.php"&gt;</code><br>
                <small>Problema: Manca punto e virgola dopo BASE_URL</small>
            </div>

            <div class='pattern-example pattern-bad'>
                <code>&lt;a href=" /admin/users.php"&gt;</code><br>
                <small>Problema: Spazio prima dello slash</small>
            </div>
        </div>

        <div class='test-section'>
            <h2>📊 Riepilogo Finale</h2>
            <?php
            $total_issues = $total_errors + $file_errors + $sidebar_errors;
            ?>

            <div class='info'>
                <h3>Risultati del Test:</h3>
                <ul>
                    <li>Errori nei link generati: <strong><?php echo $total_errors; ?></strong></li>
                    <li>File con pattern problematici: <strong><?php echo $file_errors; ?></strong></li>
                    <li>Errori nei link del sidebar: <strong><?php echo $sidebar_errors; ?></strong></li>
                    <li><strong>Totale problemi: <?php echo $total_issues; ?></strong></li>
                </ul>

                <?php if ($total_issues === 0): ?>
                <p class='success' style='font-size: 1.2em; margin-top: 20px;'>
                    🎉 <strong>OTTIMO!</strong> Tutti i test sono passati. Non ci sono spazi o problemi di concatenazione negli URL.
                </p>
                <?php else: ?>
                <p class='error' style='font-size: 1.2em; margin-top: 20px;'>
                    ⚠️ <strong>ATTENZIONE!</strong> Sono stati trovati <?php echo $total_issues; ?> problemi che richiedono correzione.
                </p>
                <p>Per risolvere i problemi:</p>
                <ol>
                    <li>Aggiungi <code>$baseUrl = rtrim(BASE_URL, '/');</code> all'inizio dei file PHP</li>
                    <li>Usa sempre la concatenazione: <code>$baseUrl . '/path'</code></li>
                    <li>Non lasciare spazi tra i tag PHP e gli slash</li>
                    <li>Verifica che BASE_URL non termini con uno slash</li>
                </ol>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>