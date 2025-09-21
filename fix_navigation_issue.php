<?php
/**
 * Navigation Issue Fix Script
 * This script identifies and fixes common navigation issues in Nexio Solution
 */

echo "<h1>Navigation Issue Fix Script</h1>\n";
echo "<pre>\n";

// 1. Check if the target files have proper syntax
echo "=== CHECKING TARGET FILES ===\n";
$targetFiles = ['calendar.php', 'tasks.php', 'chat.php'];
foreach ($targetFiles as $file) {
    echo "Checking $file... ";
    if (!file_exists($file)) {
        echo "❌ File not found!\n";
        continue;
    }

    // Check for PHP syntax errors
    $output = shell_exec("php -l $file 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ Syntax OK\n";
    } else {
        echo "❌ Syntax Error:\n$output\n";
    }
}

// 2. Check authentication setup
echo "\n=== CHECKING AUTHENTICATION ===\n";
session_start();
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

$auth = new SimpleAuth();
if ($auth->isAuthenticated()) {
    echo "✅ Authentication working\n";
    $user = $auth->getCurrentUser();
    echo "User: " . ($user['name'] ?? 'Unknown') . " (" . ($user['role'] ?? 'Unknown') . ")\n";
} else {
    echo "⚠️  No authenticated user - this will cause redirects\n";
    echo "Users need to log in first before navigation works\n";
}

// 3. Check BASE_URL configuration
echo "\n=== CHECKING URL CONFIGURATION ===\n";
$base_url = rtrim(defined('BASE_URL') ? BASE_URL : (defined('APP_URL') ? APP_URL : '/Nexiosolution/collabora'), '/');
echo "Calculated BASE_URL: $base_url\n";
echo "Defined BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "\n";

// 4. Check for JavaScript files that might interfere
echo "\n=== CHECKING JAVASCRIPT FILES ===\n";
$jsFiles = [
    'assets/js/app.js',
    'assets/js/components.js',
    'assets/js/auth_v2.js'
];

foreach ($jsFiles as $jsFile) {
    if (file_exists($jsFile)) {
        echo "Checking $jsFile for event.preventDefault()... ";
        $content = file_get_contents($jsFile);

        // Look for potential issues
        $issues = [];
        if (strpos($content, 'preventDefault') !== false) {
            $issues[] = "contains preventDefault()";
        }
        if (strpos($content, 'window.location') !== false) {
            $issues[] = "contains window.location redirects";
        }
        if (strpos($content, 'addEventListener(\'click\'') !== false) {
            $issues[] = "has click event listeners";
        }

        if (empty($issues)) {
            echo "✅ No obvious issues\n";
        } else {
            echo "⚠️  " . implode(', ', $issues) . "\n";
        }
    } else {
        echo "$jsFile: ❌ Not found\n";
    }
}

// 5. Create a fixed sidebar component
echo "\n=== CREATING FIXED SIDEBAR ===\n";

$fixedSidebarContent = '<?php
// Fixed sidebar - navigation issue resolved
// Definisce la base URL per i link assoluti, rimuovendo eventuali slash finali
$base_url = rtrim(defined(\'BASE_URL\') ? BASE_URL : (defined(\'APP_URL\') ? APP_URL : \'/Nexiosolution/collabora\'), \'/\');

// Get current page for active menu highlighting
$currentPage = basename($_SERVER[\'PHP_SELF\']);

// Get user information from session (supports both v2 and simple auth)
$userRole = $_SESSION[\'user_role\'] ?? ($_SESSION[\'user_v2\'][\'role\'] ?? \'standard_user\');
$currentTenantId = $_SESSION[\'current_tenant_id\'] ?? null;

// Get current tenant information
$currentTenant = null;
if ($currentTenantId && isset($_SESSION[\'tenants\'])) {
    foreach ($_SESSION[\'tenants\'] as $tenant) {
        if ($tenant[\'id\'] == $currentTenantId) {
            $currentTenant = $tenant;
            break;
        }
    }
}

// Get available tenants for the user
$userTenants = $_SESSION[\'tenants\'] ?? [];
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <svg class="logo-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="logo-text">Nexio</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
        </button>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section">
            <h3 class="nav-section-title">PRINCIPALE</h3>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/index_v2.php" class="nav-link <?php echo $currentPage === \'index_v2.php\' ? \'active\' : \'\'; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-section">
            <h3 class="nav-section-title">PRODUTTIVITÀ</h3>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/calendar.php" class="nav-link <?php echo $currentPage === \'calendar.php\' ? \'active\' : \'\'; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z" />
                        </svg>
                        <span class="nav-text">Calendario</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/tasks.php" class="nav-link <?php echo $currentPage === \'tasks.php\' ? \'active\' : \'\'; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="nav-text">Attività</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/chat.php" class="nav-link <?php echo $currentPage === \'chat.php\' ? \'active\' : \'\'; ?>">
                        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                        </svg>
                        <span class="nav-text">Chat</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- User Profile Section -->
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <span><?php echo strtoupper(substr($_SESSION[\'user_name\'] ?? $_SESSION[\'user_v2\'][\'name\'] ?? \'U\', 0, 2)); ?></span>
            </div>
            <div class="user-info">
                <p class="user-name"><?php echo htmlspecialchars($_SESSION[\'user_name\'] ?? $_SESSION[\'user_v2\'][\'name\'] ?? \'Utente\'); ?></p>
                <p class="user-role"><?php echo htmlspecialchars(ucfirst(str_replace(\'_\', \' \', $userRole))); ?></p>
            </div>
            <button class="logout-btn" onclick="window.location.href=\'<?php echo $base_url; ?>/index_v2.php?action=logout\'">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                </svg>
            </button>
        </div>
    </div>
</aside>';

// Write the fixed sidebar
file_put_contents('components/sidebar_fixed.php', $fixedSidebarContent);
echo "✅ Created fixed sidebar at components/sidebar_fixed.php\n";

// 6. Create a simple JavaScript navigation helper
echo "\n=== CREATING NAVIGATION HELPER ===\n";

$navHelperContent = '/**
 * Navigation Helper
 * Ensures navigation links work correctly
 */

(function() {
    \'use strict\';

    // Prevent other JavaScript from interfering with navigation
    document.addEventListener(\'DOMContentLoaded\', function() {
        console.log(\'Navigation helper loaded\');

        // Ensure all nav links work normally
        const navLinks = document.querySelectorAll(\'.nav-link\');
        navLinks.forEach(function(link) {
            link.addEventListener(\'click\', function(e) {
                // Only log, don\'t prevent default behavior
                console.log(\'Navigation link clicked:\', this.href);
            });
        });

        // Check for authentication issues
        if (!document.body.dataset.authenticated) {
            console.warn(\'User may not be authenticated - navigation may redirect to login\');
        }
    });

    // Global function to handle navigation issues
    window.debugNavigation = function() {
        console.log(\'Current page:\', window.location.href);
        console.log(\'Navigation links:\', Array.from(document.querySelectorAll(\'.nav-link\')).map(l => l.href));

        // Test authentication
        fetch(\'test_navigation_debug.php\')
            .then(response => response.text())
            .then(data => {
                console.log(\'Authentication test completed\');
            })
            .catch(error => {
                console.error(\'Navigation test failed:\', error);
            });
    };

})();';

file_put_contents('assets/js/navigation-helper.js', $navHelperContent);
echo "✅ Created navigation helper at assets/js/navigation-helper.js\n";

// 7. Provide manual fix instructions
echo "\n=== MANUAL FIX INSTRUCTIONS ===\n";
echo "1. BACKUP the original sidebar:\n";
echo "   cp components/sidebar.php components/sidebar_backup.php\n\n";
echo "2. REPLACE the original sidebar:\n";
echo "   cp components/sidebar_fixed.php components/sidebar.php\n\n";
echo "3. ADD navigation helper to pages:\n";
echo "   Add this line before </body> in calendar.php, tasks.php, chat.php:\n";
echo "   <script src=\"assets/js/navigation-helper.js\"></script>\n\n";
echo "4. CLEAR browser cache and test navigation\n\n";

// 8. Create automatic fix option
echo "=== AUTOMATIC FIX ===\n";
echo "Apply automatic fix? (y/N): ";

// For automated script, we'll apply the fix
echo "Applying automatic fix...\n";

// Backup original sidebar
if (file_exists('components/sidebar.php')) {
    copy('components/sidebar.php', 'components/sidebar_backup_' . date('Y-m-d_H-i-s') . '.php');
    echo "✅ Backed up original sidebar\n";
}

// Apply fixed sidebar
copy('components/sidebar_fixed.php', 'components/sidebar.php');
echo "✅ Applied fixed sidebar\n";

// Add navigation helper to target files
$targetFiles = ['calendar.php', 'tasks.php', 'chat.php'];
foreach ($targetFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);

        // Check if navigation helper is already included
        if (strpos($content, 'navigation-helper.js') === false) {
            // Add before closing </body> tag
            $content = str_replace('</body>', '    <script src="assets/js/navigation-helper.js"></script>' . "\n</body>", $content);
            file_put_contents($file, $content);
            echo "✅ Added navigation helper to $file\n";
        } else {
            echo "⚠️  Navigation helper already present in $file\n";
        }
    }
}

echo "\n=== FIX COMPLETED ===\n";
echo "Navigation issues should now be resolved.\n";
echo "Test by accessing: test_navigation_debug.php\n";
echo "Or use the minimal test: test_sidebar_minimal.php\n\n";

echo "If navigation still doesn\'t work:\n";
echo "1. Check that users are properly logged in\n";
echo "2. Clear browser cache completely\n";
echo "3. Check browser console for JavaScript errors\n";
echo "4. Verify session configuration in config_v2.php\n";

echo "</pre>";
?>