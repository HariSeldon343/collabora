<?php
/**
 * Quick API Test Script
 * Tests API endpoints directly with session
 */

// Initialize session properly
require_once __DIR__ . '/config_v2.php';
require_once __DIR__ . '/includes/SimpleAuth.php';

$auth = new SimpleAuth();

echo "=== Quick API Endpoint Test ===\n\n";

// Check if authenticated
if (!$auth->isAuthenticated()) {
    echo "⚠️ NOT AUTHENTICATED - API calls will return 401\n";
    echo "Please login first at: http://localhost/Nexiosolution/collabora/index_v2.php\n\n";
} else {
    $user = $auth->getCurrentUser();
    echo "✓ Authenticated as: " . ($user['email'] ?? 'Unknown') . "\n\n";
}

// Test endpoints
$endpoints = [
    '/api/channels.php' => 'Chat Channels',
    '/api/chat-poll.php?last_message_id=0' => 'Chat Polling',
    '/api/calendars.php' => 'Calendars',
    '/api/events.php' => 'Events',
    '/api/users.php' => 'Users',
    '/api/task-lists.php' => 'Task Lists'
];

foreach ($endpoints as $endpoint => $name) {
    echo "Testing: $name ($endpoint)\n";
    echo str_repeat('-', 50) . "\n";

    $url = 'http://localhost/Nexiosolution/collabora' . $endpoint;

    // Use cURL with session cookie
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Parse response
    if ($response) {
        $headerSize = strpos($response, "\r\n\r\n");
        if ($headerSize === false) {
            $headerSize = strpos($response, "\n\n");
        }

        if ($headerSize !== false) {
            $body = substr($response, $headerSize + 4);

            // Try to decode JSON
            $json = json_decode($body, true);

            echo "HTTP Code: $httpCode\n";

            if ($httpCode == 200) {
                echo "✅ SUCCESS\n";
                if ($json) {
                    echo "Response: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                } else {
                    echo "Body (first 200 chars): " . substr($body, 0, 200) . "\n";
                }
            } elseif ($httpCode == 401) {
                echo "🔒 UNAUTHORIZED (need to login)\n";
            } elseif ($httpCode == 404) {
                echo "❌ NOT FOUND (file missing)\n";
            } elseif ($httpCode == 500) {
                echo "❌ SERVER ERROR\n";
                if ($json && isset($json['message'])) {
                    echo "Error: " . $json['message'] . "\n";
                } else {
                    // Look for PHP errors in response
                    if (strpos($body, 'Fatal error') !== false || strpos($body, 'Parse error') !== false) {
                        echo "PHP Error detected:\n";
                        $lines = explode("\n", $body);
                        foreach ($lines as $line) {
                            if (strpos($line, 'error') !== false || strpos($line, 'Error') !== false) {
                                echo "  " . strip_tags(trim($line)) . "\n";
                            }
                        }
                    } else {
                        echo "Body (first 500 chars): " . substr(strip_tags($body), 0, 500) . "\n";
                    }
                }
            } else {
                echo "⚠️ HTTP $httpCode\n";
                if ($json) {
                    echo "Response: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                }
            }
        } else {
            echo "❌ Could not parse response\n";
        }
    } else {
        echo "❌ No response (connection failed)\n";
    }

    echo "\n";
}

echo "=== Test Complete ===\n";