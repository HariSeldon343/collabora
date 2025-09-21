<?php declare(strict_types=1);

/**
 * Authentication Endpoints Test Script
 * Tests authentication and user management endpoints
 *
 * @author System Architect
 * @version 1.0.0
 * @date 2025-01-21
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Colors for CLI output
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

class AuthEndpointTester {
    private string $baseUrl;
    private array $testResults = [];
    private ?string $sessionCookie = null;
    private int $userId;
    private int $tenantId;

    public function __construct() {
        // Determine base URL
        $this->baseUrl = 'http://localhost/Nexiosolution/collabora';

        echo COLOR_BLUE . "\n========================================\n";
        echo "   AUTH ENDPOINTS TEST SUITE v1.0\n";
        echo "========================================\n" . COLOR_RESET;
        echo "Base URL: " . $this->baseUrl . "\n\n";
    }

    /**
     * Run all tests
     */
    public function runTests(): void {
        $this->testLogin();
        $this->testUsersEndpoint();
        $this->testOtherAuthenticatedEndpoints();
        $this->testLogout();
        $this->displayResults();
    }

    /**
     * Test login endpoint
     */
    private function testLogin(): void {
        echo COLOR_YELLOW . "[TEST 1] Login Endpoint\n" . COLOR_RESET;
        echo "  Testing POST /api/auth_simple.php... ";

        $data = json_encode([
            'action' => 'login',
            'email' => 'asamodeo@fortibyte.it',
            'password' => 'Ricord@1991'
        ]);

        $ch = curl_init($this->baseUrl . '/api/auth_simple.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // Extract session cookie
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);
        foreach ($matches[1] as $cookie) {
            if (strpos($cookie, 'PHPSESSID') !== false || strpos($cookie, 'COLLABORA_SESSID') !== false) {
                $this->sessionCookie = $cookie;
                break;
            }
        }

        $result = json_decode($body, true);

        if ($httpCode === 200 && $result && isset($result['success']) && $result['success']) {
            echo COLOR_GREEN . "✓ SUCCESS\n" . COLOR_RESET;
            echo "  Session Cookie: " . ($this->sessionCookie ? COLOR_GREEN . "✓ Received" : COLOR_RED . "✗ Not received") . COLOR_RESET . "\n";

            if (isset($result['user'])) {
                $this->userId = $result['user']['id'];
                echo "  User ID: " . $this->userId . "\n";
                echo "  User Role: " . $result['user']['role'] . "\n";
                echo "  Is Admin: " . ($result['user']['is_admin'] ? 'Yes' : 'No') . "\n";
            }

            if (isset($result['current_tenant_id'])) {
                $this->tenantId = $result['current_tenant_id'];
                echo "  Current Tenant ID: " . $this->tenantId . "\n";
            }

            $this->testResults['login'] = true;
        } else {
            echo COLOR_RED . "✗ FAILED\n" . COLOR_RESET;
            echo "  HTTP Code: $httpCode\n";
            echo "  Response: " . substr($body, 0, 200) . "\n";
            $this->testResults['login'] = false;
        }

        echo "\n";
    }

    /**
     * Test /api/users.php endpoint
     */
    private function testUsersEndpoint(): void {
        echo COLOR_YELLOW . "[TEST 2] Users API Endpoint\n" . COLOR_RESET;

        if (!$this->sessionCookie) {
            echo "  " . COLOR_RED . "✗ SKIPPED - No session cookie\n" . COLOR_RESET;
            $this->testResults['users_api'] = 'skipped';
            echo "\n";
            return;
        }

        echo "  Testing GET /api/users.php... ";

        $ch = curl_init($this->baseUrl . '/api/users.php');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cookie: ' . $this->sessionCookie,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode === 200 && $result && isset($result['success'])) {
            echo COLOR_GREEN . "✓ SUCCESS (HTTP 200)\n" . COLOR_RESET;

            if (isset($result['data']['users']) && is_array($result['data']['users'])) {
                echo "  Users Found: " . count($result['data']['users']) . "\n";
            }

            $this->testResults['users_api'] = true;
        } else if ($httpCode === 403) {
            echo COLOR_YELLOW . "! HTTP 403 - Access Denied (Expected for non-admin)\n" . COLOR_RESET;
            $this->testResults['users_api'] = 'partial';
        } else if ($httpCode === 401) {
            echo COLOR_RED . "✗ HTTP 401 - Authentication Failed!\n" . COLOR_RESET;
            echo "  This indicates the session is not being recognized.\n";
            echo "  Response: " . substr($response, 0, 200) . "\n";
            $this->testResults['users_api'] = false;
        } else {
            echo COLOR_RED . "✗ FAILED (HTTP $httpCode)\n" . COLOR_RESET;
            echo "  Response: " . substr($response, 0, 200) . "\n";
            $this->testResults['users_api'] = false;
        }

        echo "\n";
    }

    /**
     * Test other authenticated endpoints
     */
    private function testOtherAuthenticatedEndpoints(): void {
        echo COLOR_YELLOW . "[TEST 3] Other Authenticated Endpoints\n" . COLOR_RESET;

        if (!$this->sessionCookie) {
            echo "  " . COLOR_RED . "✗ SKIPPED - No session cookie\n" . COLOR_RESET;
            $this->testResults['other_endpoints'] = 'skipped';
            echo "\n";
            return;
        }

        $endpoints = [
            '/api/files.php' => 'Files API',
            '/api/tenants.php' => 'Tenants API',
            '/api/calendars.php' => 'Calendars API',
            '/api/tasks.php' => 'Tasks API'
        ];

        $allSuccess = true;

        foreach ($endpoints as $endpoint => $name) {
            echo "  Testing $name... ";

            $ch = curl_init($this->baseUrl . $endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Cookie: ' . $this->sessionCookie,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                echo COLOR_GREEN . "✓ OK (200)\n" . COLOR_RESET;
            } else if ($httpCode === 403) {
                echo COLOR_YELLOW . "! Access Denied (403)\n" . COLOR_RESET;
            } else if ($httpCode === 401) {
                echo COLOR_RED . "✗ Unauthorized (401)\n" . COLOR_RESET;
                $allSuccess = false;
            } else if ($httpCode === 404) {
                echo COLOR_YELLOW . "- Not Found (404)\n" . COLOR_RESET;
            } else {
                echo COLOR_RED . "✗ Error ($httpCode)\n" . COLOR_RESET;
                $allSuccess = false;
            }
        }

        $this->testResults['other_endpoints'] = $allSuccess;
        echo "\n";
    }

    /**
     * Test logout
     */
    private function testLogout(): void {
        echo COLOR_YELLOW . "[TEST 4] Logout Endpoint\n" . COLOR_RESET;

        if (!$this->sessionCookie) {
            echo "  " . COLOR_RED . "✗ SKIPPED - No session cookie\n" . COLOR_RESET;
            $this->testResults['logout'] = 'skipped';
            echo "\n";
            return;
        }

        echo "  Testing POST /api/auth_simple.php (logout)... ";

        $data = json_encode(['action' => 'logout']);

        $ch = curl_init($this->baseUrl . '/api/auth_simple.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Cookie: ' . $this->sessionCookie
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode === 200 && $result && isset($result['success']) && $result['success']) {
            echo COLOR_GREEN . "✓ SUCCESS\n" . COLOR_RESET;
            $this->testResults['logout'] = true;
        } else {
            echo COLOR_RED . "✗ FAILED\n" . COLOR_RESET;
            echo "  Response: " . substr($response, 0, 200) . "\n";
            $this->testResults['logout'] = false;
        }

        echo "\n";
    }

    /**
     * Display test results summary
     */
    private function displayResults(): void {
        echo COLOR_BLUE . "========================================\n";
        echo "            TEST RESULTS SUMMARY\n";
        echo "========================================\n" . COLOR_RESET;

        $allPass = true;
        $hasFailure = false;

        foreach ($this->testResults as $test => $result) {
            $testName = str_replace('_', ' ', ucfirst($test));

            if ($result === true) {
                $status = COLOR_GREEN . '✓ PASS' . COLOR_RESET;
            } else if ($result === 'partial') {
                $status = COLOR_YELLOW . '! PARTIAL' . COLOR_RESET;
                $allPass = false;
            } else if ($result === 'skipped') {
                $status = COLOR_YELLOW . '- SKIPPED' . COLOR_RESET;
                $allPass = false;
            } else {
                $status = COLOR_RED . '✗ FAIL' . COLOR_RESET;
                $allPass = false;
                $hasFailure = true;
            }

            echo sprintf("  %-25s %s\n", $testName . ':', $status);
        }

        echo "\n";

        if ($allPass) {
            echo COLOR_GREEN . "🎉 ALL TESTS PASSED! 🎉\n" . COLOR_RESET;
            echo "\nYour authentication system is working perfectly!\n";
            echo "✓ Login/Logout working\n";
            echo "✓ Session management working\n";
            echo "✓ API authentication working\n";
        } else if ($hasFailure) {
            echo COLOR_RED . "⚠️  AUTHENTICATION ISSUES DETECTED ⚠️\n" . COLOR_RESET;
            echo "\nThe authentication system has issues that need attention.\n";

            if (isset($this->testResults['users_api']) && $this->testResults['users_api'] === false) {
                echo "\n" . COLOR_YELLOW . "Fix for 401 on /api/users.php:\n" . COLOR_RESET;
                echo "1. Ensure session_start() is called at the beginning of the file\n";
                echo "2. Verify SimpleAuth->isAuthenticated() checks \$_SESSION['user_id']\n";
                echo "3. Check that cookies are being sent with the request\n";
            }
        } else {
            echo COLOR_YELLOW . "✓ AUTHENTICATION WORKING WITH NOTES\n" . COLOR_RESET;
            echo "\nThe authentication system is functional but has some notes:\n";
            echo "- Some endpoints may require admin privileges\n";
            echo "- Some endpoints may not be implemented yet\n";
        }

        echo "\n" . COLOR_BLUE . "Test Completed at: " . date('Y-m-d H:i:s') . "\n" . COLOR_RESET;
    }
}

// Check if cURL is available
if (!function_exists('curl_init')) {
    echo COLOR_RED . "ERROR: cURL extension is not installed or enabled.\n" . COLOR_RESET;
    echo "Please enable the cURL extension in your PHP configuration.\n";
    exit(1);
}

// Run tests
try {
    $tester = new AuthEndpointTester();
    $tester->runTests();
} catch (Exception $e) {
    echo COLOR_RED . "\n❌ CRITICAL ERROR: " . $e->getMessage() . "\n" . COLOR_RESET;
    exit(1);
}