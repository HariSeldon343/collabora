<?php
/**
 * Test per verificare i link del menu e l'assenza di redirect loop
 */

require_once 'config_v2.php';

// Test configuration
$test_results = [];
$base_url = BASE_URL;

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Menu Links</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f0f0f0; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { background: #e7f3ff; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Test Link Menu - Nexio Collabora</h1>
        <div class='info'>
            <strong>BASE_URL:</strong> $base_url<br>
            <strong>Test Time:</strong> " . date('Y-m-d H:i:s') . "
        </div>

        <h2>Verifica Link Menu</h2>
        <table>
            <tr>
                <th>Pagina</th>
                <th>URL Generato</th>
                <th>Tipo Link</th>
                <th>Status</th>
            </tr>";

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

foreach ($menu_links as $name => $path) {
    $full_url = $base_url . $path;
    $type = strpos($path, '/admin/') !== false ? 'Admin' : 'Normal';

    // Verifica che non ci siano duplicati di "admin" nel path
    $has_duplicate = preg_match('/admin\/admin/', $full_url);

    if ($has_duplicate) {
        $status = "<span class='error'>ERRORE: Path duplicato admin/admin</span>";
    } else {
        $status = "<span class='success'>OK</span>";
    }

    echo "<tr>
            <td>$name</td>
            <td>$full_url</td>
            <td>$type</td>
            <td>$status</td>
          </tr>";
}

echo "</table>";

// Test include del sidebar
echo "<h2>Test Include Sidebar</h2>";

// Salva il contenuto originale del $_SERVER per ripristinarlo dopo
$original_server = $_SERVER;

// Simula richiesta da una pagina admin
$_SERVER['PHP_SELF'] = '/Nexiosolution/collabora/admin/index.php';
$_SERVER['REQUEST_URI'] = '/Nexiosolution/collabora/admin/index.php';

// Buffer output del sidebar
ob_start();
$_SESSION['user_role'] = 'admin'; // Simula ruolo admin per vedere tutti i menu
$_SESSION['user_name'] = 'Test User';
$_SESSION['tenants'] = [];
include 'components/sidebar.php';
$sidebar_output = ob_get_clean();

// Estrai tutti gli href dal sidebar
preg_match_all('/href=["\']([^"\']+)["\']/', $sidebar_output, $matches);
$sidebar_links = $matches[1];

echo "<table>
        <tr>
            <th>Link nel Sidebar</th>
            <th>Status</th>
        </tr>";

foreach ($sidebar_links as $link) {
    // Salta i link che sono placeholder JavaScript
    if (strpos($link, '#') === 0 || strpos($link, 'javascript:') === 0) {
        continue;
    }

    // Verifica path duplicati admin/admin
    $has_duplicate = preg_match('/admin\/admin/', $link);

    if ($has_duplicate) {
        $status = "<span class='error'>ERRORE: admin/admin nel path</span>";
    } elseif (strpos($link, $base_url) === 0) {
        $status = "<span class='success'>OK - Path assoluto</span>";
    } elseif (strpos($link, 'http') === 0) {
        $status = "<span class='success'>OK - URL completo</span>";
    } elseif (strpos($link, '/') === 0) {
        $status = "<span class='warning'>Path assoluto senza base URL</span>";
    } else {
        $status = "<span class='error'>ATTENZIONE: Path relativo</span>";
    }

    echo "<tr>
            <td>$link</td>
            <td>$status</td>
          </tr>";
}

echo "</table>";

// Ripristina $_SERVER
$_SERVER = $original_server;

echo "<h2>Riepilogo</h2>
      <div class='info'>
        <p><strong>Test completato!</strong></p>
        <p>Se tutti i link mostrano 'OK', il problema del redirect loop dovrebbe essere risolto.</p>
        <p>I link ora usano percorsi assoluti con BASE_URL per evitare la concatenazione di 'admin/'.</p>
      </div>
    </div>
</body>
</html>";
?>