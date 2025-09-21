<?php
/**
 * Script di verifica accesso test
 * Controlla se i test sono accessibili dopo le modifiche
 */

header('Content-Type: text/plain');
echo "=== NEXIO COLLABORA - TEST ACCESS CHECK ===\n\n";

// Test configurazione ambiente
echo "1. CONFIGURAZIONE AMBIENTE:\n";
echo "   Server: " . ($_SERVER['SERVER_NAME'] ?? 'N/A') . "\n";
echo "   IP: " . ($_SERVER['SERVER_ADDR'] ?? 'N/A') . "\n";
echo "   Ambiente: " . (($_SERVER['SERVER_NAME'] ?? '') === 'localhost' ? 'DEVELOPMENT' : 'PRODUCTION') . "\n\n";

// Verifica file test
echo "2. FILE TEST TROVATI:\n";
$testFiles = glob(__DIR__ . '/test*.php');
echo "   Totale: " . count($testFiles) . " file\n";
foreach (array_slice($testFiles, 0, 5) as $file) {
    echo "   - " . basename($file) . "\n";
}
echo "   ...\n\n";

// Test accesso diretto
echo "3. TEST ACCESSO FILE:\n";
$testFile = __DIR__ . '/test_auth.php';
if (file_exists($testFile)) {
    echo "   ✓ test_auth.php esiste\n";
    echo "   ✓ Permessi: " . substr(sprintf('%o', fileperms($testFile)), -4) . "\n";
    echo "   ✓ Leggibile: " . (is_readable($testFile) ? 'SI' : 'NO') . "\n";
} else {
    echo "   ✗ test_auth.php non trovato\n";
}
echo "\n";

// Test .htaccess
echo "4. CONFIGURAZIONE .HTACCESS:\n";
$htaccess = file_get_contents(__DIR__ . '/.htaccess');
if (strpos($htaccess, 'test).*\.php$ - [F,L]') !== false) {
    echo "   ✗ BLOCCO TEST ANCORA ATTIVO!\n";
} else {
    echo "   ✓ Blocco test rimosso\n";
}
if (strpos($htaccess, 'Permetti accesso ai test solo in development') !== false) {
    echo "   ✓ Regola development presente\n";
}
echo "\n";

// Test runner check
echo "5. TEST RUNNER:\n";
if (file_exists(__DIR__ . '/test_runner.php')) {
    echo "   ✓ test_runner.php presente\n";
    echo "   ✓ URL: http://localhost/Nexiosolution/collabora/test_runner.php\n";
}
if (file_exists(__DIR__ . '/RUN_TESTS.bat')) {
    echo "   ✓ RUN_TESTS.bat presente\n";
}
echo "\n";

// Test config
echo "6. CONFIGURAZIONE TEST:\n";
if (file_exists(__DIR__ . '/config/test.config.php')) {
    echo "   ✓ config/test.config.php presente\n";
    require_once __DIR__ . '/config/test.config.php';
    echo "   ✓ Accesso browser: " . (TEST_ALLOW_BROWSER ? 'PERMESSO' : 'NEGATO') . "\n";
    echo "   ✓ Ambiente dev: " . (TEST_ENV_IS_DEV ? 'SI' : 'NO') . "\n";
}
echo "\n";

echo "=== RISULTATO FINALE ===\n";
echo "I test dovrebbero essere accessibili da:\n";
echo "- Browser: http://localhost/Nexiosolution/collabora/test_runner.php\n";
echo "- CLI: C:\\xampp\\htdocs\\Nexiosolution\\collabora\\RUN_TESTS.bat\n";
echo "\nSe ancora non funziona, prova a:\n";
echo "1. Riavviare Apache da XAMPP Control Panel\n";
echo "2. Pulire la cache del browser\n";
echo "3. Verificare che non ci siano altri .htaccess nelle directory superiori\n";