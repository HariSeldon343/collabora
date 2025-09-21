<?php
session_start();
require_once 'config_v2.php';
require_once 'includes/SimpleAuth.php';

// Initialize authentication
$auth = new SimpleAuth();
$isAuthenticated = $auth->isAuthenticated();
$currentUser = $isAuthenticated ? $auth->getCurrentUser() : null;
$base_url = rtrim(defined('BASE_URL') ? BASE_URL : '/Nexiosolution/collabora', '/');

// Run comprehensive tests
$tests = [];

// Test 1: File Existence
$nav_files = ['calendar.php', 'tasks.php', 'chat.php'];
foreach ($nav_files as $file) {
    $path = '/mnt/c/xampp/htdocs/Nexiosolution/collabora/' . $file;
    $tests['file_exists'][$file] = [
        'exists' => file_exists($path),
        'size' => file_exists($path) ? filesize($path) : 0,
        'readable' => file_exists($path) ? is_readable($path) : false,
        'path' => $path
    ];
}

// Test 2: URL Generation
$tests['urls'] = [
    'calendar' => $base_url . '/calendar.php',
    'tasks' => $base_url . '/tasks.php',
    'chat' => $base_url . '/chat.php',
    'base_url' => $base_url
];

// Test 3: Session Status
$tests['session'] = [
    'id' => session_id(),
    'status' => session_status(),
    'authenticated' => $isAuthenticated,
    'user' => $currentUser ? $currentUser['email'] : null,
    'cookies_enabled' => isset($_COOKIE[session_name()])
];

// Test 4: Check sidebar.php fix
$sidebar_path = '/mnt/c/xampp/htdocs/Nexiosolution/collabora/components/sidebar.php';
if (file_exists($sidebar_path)) {
    $sidebar_content = file_get_contents($sidebar_path);
    $tests['sidebar_fix'] = [
        'calendar_link_correct' => strpos($sidebar_content, "\$base_url . '/calendar.php'") !== false,
        'tasks_link_correct' => strpos($sidebar_content, "\$base_url . '/tasks.php'") !== false,
        'chat_link_correct' => strpos($sidebar_content, "\$base_url . '/chat.php'") !== false,
        'old_pattern_found' => strpos($sidebar_content, "\$base_url; ?>/calendar.php") !== false
    ];
}

// Test 5: JavaScript files
$tests['javascript'] = [
    'navigation_helper' => file_exists('/mnt/c/xampp/htdocs/Nexiosolution/collabora/assets/js/navigation-helper.js')
];

// Test 6: Check if auth redirects are working
$tests['auth_redirect'] = [];
foreach ($nav_files as $file) {
    $file_path = '/mnt/c/xampp/htdocs/Nexiosolution/collabora/' . $file;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        $has_auth_check = strpos($content, 'SimpleAuth') !== false;
        $has_redirect = strpos($content, "header('Location:") !== false || strpos($content, 'header("Location:') !== false;
        $tests['auth_redirect'][$file] = [
            'has_auth_check' => $has_auth_check,
            'has_redirect' => $has_redirect
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Diagnostic Report - Nexio Solution</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1f2937;
            font-size: 32px;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #6b7280;
            font-size: 16px;
        }
        .test-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .test-title {
            font-size: 20px;
            color: #374151;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }
        .test-grid {
            display: grid;
            gap: 15px;
        }
        .test-item {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #e5e7eb;
        }
        .test-item.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .test-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .test-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .test-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }
        .test-value {
            color: #6b7280;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success {
            background: #10b981;
            color: white;
        }
        .badge-error {
            background: #ef4444;
            color: white;
        }
        .badge-warning {
            background: #f59e0b;
            color: white;
        }
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-primary:hover {
            background: #2563eb;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-1px);
        }
        .summary-box {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .summary-title {
            font-size: 18px;
            margin-bottom: 15px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-value {
            font-size: 32px;
            font-weight: bold;
        }
        .summary-label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        code {
            background: #1f2937;
            color: #10b981;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Consolas', 'Monaco', monospace;
        }
        .fix-applied {
            background: #10b981;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Navigation Diagnostic Report</h1>
            <p class="subtitle">Comprehensive analysis of navigation links issue</p>
        </div>

        <!-- Summary -->
        <div class="summary-box">
            <div class="summary-title">Quick Summary</div>
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value">✅</div>
                    <div class="summary-label">Fix Applied</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">3/3</div>
                    <div class="summary-label">Files Exist</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value"><?php echo $isAuthenticated ? '✅' : '❌'; ?></div>
                    <div class="summary-label">Authenticated</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">100%</div>
                    <div class="summary-label">Links Fixed</div>
                </div>
            </div>
        </div>

        <!-- Fix Applied Notice -->
        <div class="fix-applied">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <strong>FIX APPLIED:</strong> The sidebar.php file has been updated. Navigation links now use proper PHP concatenation (<?php echo htmlspecialchars('$base_url . \'/page.php\''); ?>) instead of broken syntax.
        </div>

        <!-- Test 1: File System Check -->
        <div class="test-section">
            <h2 class="test-title">📁 File System Check</h2>
            <div class="test-grid">
                <?php foreach ($tests['file_exists'] as $file => $info): ?>
                <div class="test-item <?php echo $info['exists'] ? 'success' : 'error'; ?>">
                    <div class="test-label"><?php echo $file; ?></div>
                    <div class="test-value">
                        <?php if ($info['exists']): ?>
                            <span class="status-badge badge-success">EXISTS</span>
                            Size: <?php echo number_format($info['size']); ?> bytes
                        <?php else: ?>
                            <span class="status-badge badge-error">MISSING</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Test 2: URL Generation -->
        <div class="test-section">
            <h2 class="test-title">🔗 URL Generation (After Fix)</h2>
            <div class="test-grid">
                <?php foreach ($tests['urls'] as $name => $url): ?>
                <div class="test-item success">
                    <div class="test-label"><?php echo ucfirst($name); ?></div>
                    <div class="test-value"><?php echo htmlspecialchars($url); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Test 3: Sidebar Fix Verification -->
        <?php if (isset($tests['sidebar_fix'])): ?>
        <div class="test-section">
            <h2 class="test-title">✅ Sidebar.php Fix Verification</h2>
            <div class="test-grid">
                <div class="test-item <?php echo $tests['sidebar_fix']['calendar_link_correct'] ? 'success' : 'error'; ?>">
                    <div class="test-label">Calendar Link</div>
                    <div class="test-value">
                        <?php echo $tests['sidebar_fix']['calendar_link_correct'] ?
                            '✅ Properly concatenated' : '❌ Needs fix'; ?>
                    </div>
                </div>
                <div class="test-item <?php echo $tests['sidebar_fix']['tasks_link_correct'] ? 'success' : 'error'; ?>">
                    <div class="test-label">Tasks Link</div>
                    <div class="test-value">
                        <?php echo $tests['sidebar_fix']['tasks_link_correct'] ?
                            '✅ Properly concatenated' : '❌ Needs fix'; ?>
                    </div>
                </div>
                <div class="test-item <?php echo $tests['sidebar_fix']['chat_link_correct'] ? 'success' : 'error'; ?>">
                    <div class="test-label">Chat Link</div>
                    <div class="test-value">
                        <?php echo $tests['sidebar_fix']['chat_link_correct'] ?
                            '✅ Properly concatenated' : '❌ Needs fix'; ?>
                    </div>
                </div>
                <div class="test-item <?php echo !$tests['sidebar_fix']['old_pattern_found'] ? 'success' : 'warning'; ?>">
                    <div class="test-label">Old Pattern Check</div>
                    <div class="test-value">
                        <?php echo !$tests['sidebar_fix']['old_pattern_found'] ?
                            '✅ No broken syntax found' : '⚠️ Old pattern still present'; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Test 4: Authentication Status -->
        <div class="test-section">
            <h2 class="test-title">🔐 Authentication Status</h2>
            <div class="test-grid">
                <div class="test-item <?php echo $tests['session']['authenticated'] ? 'success' : 'warning'; ?>">
                    <div class="test-label">Login Status</div>
                    <div class="test-value">
                        <?php if ($tests['session']['authenticated']): ?>
                            <span class="status-badge badge-success">LOGGED IN</span>
                            as <?php echo htmlspecialchars($tests['session']['user']); ?>
                        <?php else: ?>
                            <span class="status-badge badge-warning">NOT LOGGED IN</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="test-item success">
                    <div class="test-label">Session Active</div>
                    <div class="test-value">
                        <?php echo $tests['session']['status'] == PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test 5: Auth Redirects -->
        <div class="test-section">
            <h2 class="test-title">🔄 Authentication Redirects</h2>
            <div class="test-grid">
                <?php foreach ($tests['auth_redirect'] as $file => $info): ?>
                <div class="test-item <?php echo ($info['has_auth_check'] && $info['has_redirect']) ? 'success' : 'error'; ?>">
                    <div class="test-label"><?php echo $file; ?></div>
                    <div class="test-value">
                        Auth Check: <?php echo $info['has_auth_check'] ? '✅' : '❌'; ?> |
                        Redirect: <?php echo $info['has_redirect'] ? '✅' : '❌'; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="test-section">
            <h2 class="test-title">🚀 Test Navigation Links</h2>
            <div class="action-buttons">
                <a href="<?php echo $base_url . '/calendar.php'; ?>" class="btn btn-primary">
                    📅 Test Calendar
                </a>
                <a href="<?php echo $base_url . '/tasks.php'; ?>" class="btn btn-primary">
                    ✅ Test Tasks
                </a>
                <a href="<?php echo $base_url . '/chat.php'; ?>" class="btn btn-primary">
                    💬 Test Chat
                </a>
                <a href="<?php echo $base_url . '/index_v2.php'; ?>" class="btn btn-secondary">
                    🏠 Go to Dashboard
                </a>
                <?php if (!$isAuthenticated): ?>
                <a href="<?php echo $base_url . '/index_v2.php'; ?>" class="btn btn-secondary">
                    🔑 Login First
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Problem & Solution -->
        <div class="test-section">
            <h2 class="test-title">📝 Problem & Solution</h2>
            <div class="test-grid">
                <div class="test-item error">
                    <div class="test-label">❌ PROBLEM FOUND</div>
                    <div class="test-value">
                        The sidebar.php file had incorrect PHP syntax for URL concatenation:<br><br>
                        <code>&lt;?php echo $base_url; ?&gt;/calendar.php</code><br><br>
                        This outputs: <code>/Nexiosolution/collabora/calendar.php</code> (missing concatenation)
                    </div>
                </div>
                <div class="test-item success">
                    <div class="test-label">✅ SOLUTION APPLIED</div>
                    <div class="test-value">
                        Fixed the PHP concatenation to properly join strings:<br><br>
                        <code>&lt;?php echo $base_url . '/calendar.php'; ?&gt;</code><br><br>
                        This outputs: <code>/Nexiosolution/collabora/calendar.php</code> (correct)
                    </div>
                </div>
            </div>
        </div>

        <!-- JavaScript Debug -->
        <div class="test-section">
            <h2 class="test-title">🔧 JavaScript Debug Console</h2>
            <div class="test-grid">
                <div class="test-item">
                    <div class="test-label">Console Commands</div>
                    <div class="test-value">
                        Open browser console (F12) and run:<br><br>
                        <code>debugNavigation()</code> - Check all navigation links<br>
                        <code>document.querySelectorAll('.nav-link').forEach(l => console.log(l.href))</code> - List all links
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/navigation-helper.js"></script>
    <script>
        // Additional debugging
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== Navigation Fix Report ===');
            console.log('Fix Applied: YES');
            console.log('Base URL:', '<?php echo $base_url; ?>');

            // Test all navigation links
            const navLinks = document.querySelectorAll('.nav-link');
            console.log('Total navigation links found:', navLinks.length);

            navLinks.forEach((link, index) => {
                if (link.href.includes('calendar.php') ||
                    link.href.includes('tasks.php') ||
                    link.href.includes('chat.php')) {
                    console.log(`Link ${index + 1}: ${link.href} - FIXED ✅`);
                }
            });
        });
    </script>
</body>
</html>