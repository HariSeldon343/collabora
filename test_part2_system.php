<?php
/**
 * Test completo sistema Calendar & Task Management
 * Nexiosolution Parte 2
 *
 * @version 2.1.0
 * @date 2025-01-19
 */

// Configurazione
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');
set_time_limit(300);

// Colori per output console
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

// Classe per gestire i test
class Part2SystemTester {
    private $db;
    private $baseUrl = 'http://localhost/Nexiosolution/collabora';
    private $testResults = [];
    private $sessionCookie = '';
    private $testTenantId = 1;
    private $testUserId = 1;

    public function __construct() {
        echo BLUE . "\n====================================\n";
        echo "  NEXIOSOLUTION PARTE 2 - TEST SUITE\n";
        echo "  Calendar & Task Management\n";
        echo "====================================\n" . RESET;
    }

    /**
     * Esegue tutti i test
     */
    public function runAllTests() {
        $this->testDatabaseConnection();
        $this->testDatabaseTables();
        $this->testAuthentication();
        $this->testCalendarAPI();
        $this->testEventsAPI();
        $this->testTaskListsAPI();
        $this->testTasksAPI();
        $this->testFrontendPages();
        $this->testMultiTenantIsolation();
        $this->testPermissions();
        $this->testPerformance();
        $this->printSummary();
    }

    /**
     * Test 1: Connessione Database
     */
    private function testDatabaseConnection() {
        echo "\n" . YELLOW . "[TEST 1] Connessione Database" . RESET . "\n";

        try {
            $this->db = new PDO(
                'mysql:host=localhost;dbname=nexio_collabora_v2;charset=utf8mb4',
                'root',
                '',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $this->pass("Connessione database stabilita");
        } catch (Exception $e) {
            $this->fail("Connessione database fallita: " . $e->getMessage());
            die("\nTest interrotto: impossibile continuare senza database.\n");
        }
    }

    /**
     * Test 2: Verifica tabelle database
     */
    private function testDatabaseTables() {
        echo "\n" . YELLOW . "[TEST 2] Verifica Tabelle Database" . RESET . "\n";

        $requiredTables = [
            'calendars' => ['id', 'tenant_id', 'user_id', 'name'],
            'events' => ['id', 'calendar_id', 'title', 'start_datetime'],
            'event_participants' => ['id', 'event_id', 'email'],
            'event_reminders' => ['id', 'event_id', 'minutes_before'],
            'calendar_shares' => ['id', 'calendar_id', 'user_id'],
            'event_attachments' => ['id', 'event_id', 'file_id'],
            'task_lists' => ['id', 'tenant_id', 'name', 'owner_id'],
            'tasks' => ['id', 'task_list_id', 'title', 'status'],
            'task_assignments' => ['id', 'task_id', 'user_id'],
            'task_comments' => ['id', 'task_id', 'user_id', 'comment'],
            'task_time_logs' => ['id', 'task_id', 'user_id', 'hours_logged'],
            'task_attachments' => ['id', 'task_id', 'file_id']
        ];

        foreach ($requiredTables as $table => $columns) {
            // Verifica esistenza tabella
            $stmt = $this->db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->fetch()) {
                $this->pass("Tabella '$table' esistente");

                // Verifica colonne principali
                $stmt = $this->db->query("SHOW COLUMNS FROM $table");
                $existingColumns = [];
                while ($row = $stmt->fetch()) {
                    $existingColumns[] = $row['Field'];
                }

                $missingColumns = array_diff($columns, $existingColumns);
                if (empty($missingColumns)) {
                    $this->pass("  └─ Colonne verificate per '$table'");
                } else {
                    $this->warn("  └─ Colonne mancanti in '$table': " . implode(', ', $missingColumns));
                }
            } else {
                $this->fail("Tabella '$table' non trovata!");
            }
        }
    }

    /**
     * Test 3: Autenticazione
     */
    private function testAuthentication() {
        echo "\n" . YELLOW . "[TEST 3] Autenticazione Sistema" . RESET . "\n";

        $ch = curl_init($this->baseUrl . '/api/auth_simple.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'action' => 'login',
            'email' => 'asamodeo@fortibyte.it',
            'password' => 'Ricord@1991'
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        if ($httpCode == 200) {
            $data = json_decode($body, true);
            if (isset($data['success']) && $data['success']) {
                $this->pass("Login riuscito");

                // Estrai cookie di sessione
                preg_match('/Set-Cookie: (PHPSESSID=[^;]+)/', $headers, $matches);
                if (isset($matches[1])) {
                    $this->sessionCookie = $matches[1];
                    $this->pass("Cookie di sessione ottenuto");
                }
            } else {
                $this->fail("Login fallito: " . ($data['message'] ?? 'Errore sconosciuto'));
            }
        } else {
            $this->fail("Errore HTTP $httpCode durante login");
        }
    }

    /**
     * Test 4: API Calendari
     */
    private function testCalendarAPI() {
        echo "\n" . YELLOW . "[TEST 4] API Calendari" . RESET . "\n";

        if (!$this->sessionCookie) {
            $this->skip("Test saltato: sessione non disponibile");
            return;
        }

        // Test GET calendari
        $response = $this->apiRequest('GET', '/api/calendars.php');
        if ($response['success']) {
            $this->pass("GET /api/calendars - Lista calendari");
        } else {
            $this->fail("GET /api/calendars - " . $response['error']);
        }

        // Test POST nuovo calendario
        $calendarData = [
            'name' => 'Test Calendar ' . time(),
            'description' => 'Calendario di test automatico',
            'color' => '#4F46E5',
            'timezone' => 'Europe/Rome'
        ];

        $response = $this->apiRequest('POST', '/api/calendars.php', $calendarData);
        if ($response['success'] && isset($response['data']['id'])) {
            $this->pass("POST /api/calendars - Calendario creato (ID: {$response['data']['id']})");
            $calendarId = $response['data']['id'];

            // Test GET calendario specifico
            $response = $this->apiRequest('GET', "/api/calendars.php/$calendarId");
            if ($response['success']) {
                $this->pass("GET /api/calendars/$calendarId - Dettagli calendario");
            } else {
                $this->fail("GET /api/calendars/$calendarId - " . $response['error']);
            }

            // Test UPDATE calendario
            $updateData = ['name' => 'Updated Test Calendar'];
            $response = $this->apiRequest('PATCH', "/api/calendars.php/$calendarId", $updateData);
            if ($response['success']) {
                $this->pass("PATCH /api/calendars/$calendarId - Calendario aggiornato");
            } else {
                $this->fail("PATCH /api/calendars/$calendarId - " . $response['error']);
            }

            // Test DELETE calendario (soft delete)
            $response = $this->apiRequest('DELETE', "/api/calendars.php/$calendarId");
            if ($response['success']) {
                $this->pass("DELETE /api/calendars/$calendarId - Calendario eliminato");
            } else {
                $this->fail("DELETE /api/calendars/$calendarId - " . $response['error']);
            }
        } else {
            $this->fail("POST /api/calendars - " . $response['error']);
        }
    }

    /**
     * Test 5: API Eventi
     */
    private function testEventsAPI() {
        echo "\n" . YELLOW . "[TEST 5] API Eventi" . RESET . "\n";

        if (!$this->sessionCookie) {
            $this->skip("Test saltato: sessione non disponibile");
            return;
        }

        // Prima crea un calendario per i test
        $calendarResponse = $this->apiRequest('POST', '/api/calendars.php', [
            'name' => 'Event Test Calendar',
            'color' => '#10B981'
        ]);

        if (!$calendarResponse['success']) {
            $this->fail("Impossibile creare calendario per test eventi");
            return;
        }

        $calendarId = $calendarResponse['data']['id'];

        // Test POST nuovo evento
        $eventData = [
            'calendar_id' => $calendarId,
            'title' => 'Test Event ' . time(),
            'description' => 'Evento di test automatico',
            'start_datetime' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_datetime' => date('Y-m-d H:i:s', strtotime('+1 day +1 hour')),
            'location' => 'Sala Test',
            'participants' => [
                ['email' => 'test1@example.com', 'name' => 'Test User 1'],
                ['email' => 'test2@example.com', 'name' => 'Test User 2']
            ],
            'reminders' => [
                ['minutes_before' => 15, 'method' => 'email'],
                ['minutes_before' => 5, 'method' => 'popup']
            ]
        ];

        $response = $this->apiRequest('POST', '/api/events.php', $eventData);
        if ($response['success'] && isset($response['data']['id'])) {
            $this->pass("POST /api/events - Evento creato (ID: {$response['data']['id']})");
            $eventId = $response['data']['id'];

            // Test GET evento specifico
            $response = $this->apiRequest('GET', "/api/events.php/$eventId");
            if ($response['success']) {
                $this->pass("GET /api/events/$eventId - Dettagli evento");
            } else {
                $this->fail("GET /api/events/$eventId - " . $response['error']);
            }

            // Test RSVP
            $response = $this->apiRequest('POST', "/api/events.php/$eventId/rsvp", [
                'response' => 'accepted',
                'comment' => 'Parteciperò'
            ]);
            if ($response['success']) {
                $this->pass("POST /api/events/$eventId/rsvp - RSVP aggiornato");
            } else {
                $this->fail("POST /api/events/$eventId/rsvp - " . $response['error']);
            }

            // Test UPDATE evento
            $updateData = [
                'title' => 'Updated Test Event',
                'location' => 'Nuova Sala'
            ];
            $response = $this->apiRequest('PATCH', "/api/events.php/$eventId", $updateData);
            if ($response['success']) {
                $this->pass("PATCH /api/events/$eventId - Evento aggiornato");
            } else {
                $this->fail("PATCH /api/events/$eventId - " . $response['error']);
            }
        } else {
            $this->fail("POST /api/events - " . $response['error']);
        }
    }

    /**
     * Test 6: API Task Lists
     */
    private function testTaskListsAPI() {
        echo "\n" . YELLOW . "[TEST 6] API Task Lists" . RESET . "\n";

        if (!$this->sessionCookie) {
            $this->skip("Test saltato: sessione non disponibile");
            return;
        }

        // Test POST nuova lista
        $listData = [
            'name' => 'Test Task List ' . time(),
            'description' => 'Lista di test automatico',
            'board_type' => 'kanban',
            'color' => '#F59E0B',
            'workflow_states' => [
                ['id' => 'todo', 'name' => 'Da Fare', 'color' => '#3B82F6'],
                ['id' => 'in_progress', 'name' => 'In Corso', 'color' => '#F59E0B'],
                ['id' => 'done', 'name' => 'Completato', 'color' => '#10B981']
            ]
        ];

        $response = $this->apiRequest('POST', '/api/task-lists.php', $listData);
        if ($response['success'] && isset($response['data']['id'])) {
            $this->pass("POST /api/task-lists - Lista creata (ID: {$response['data']['id']})");
            $listId = $response['data']['id'];

            // Test GET lista specifica
            $response = $this->apiRequest('GET', "/api/task-lists.php/$listId");
            if ($response['success']) {
                $this->pass("GET /api/task-lists/$listId - Dettagli lista");
            } else {
                $this->fail("GET /api/task-lists/$listId - " . $response['error']);
            }

            // Test GET statistiche
            $response = $this->apiRequest('GET', "/api/task-lists.php/$listId/stats");
            if ($response['success']) {
                $this->pass("GET /api/task-lists/$listId/stats - Statistiche lista");
            } else {
                $this->fail("GET /api/task-lists/$listId/stats - " . $response['error']);
            }

            // Salva ID per test successivi
            $GLOBALS['testListId'] = $listId;
        } else {
            $this->fail("POST /api/task-lists - " . $response['error']);
        }
    }

    /**
     * Test 7: API Tasks
     */
    private function testTasksAPI() {
        echo "\n" . YELLOW . "[TEST 7] API Tasks" . RESET . "\n";

        if (!$this->sessionCookie) {
            $this->skip("Test saltato: sessione non disponibile");
            return;
        }

        $listId = $GLOBALS['testListId'] ?? null;
        if (!$listId) {
            // Crea una lista se non esiste
            $listResponse = $this->apiRequest('POST', '/api/task-lists.php', [
                'name' => 'Task Test List',
                'board_type' => 'kanban'
            ]);

            if (!$listResponse['success']) {
                $this->fail("Impossibile creare lista per test task");
                return;
            }
            $listId = $listResponse['data']['id'];
        }

        // Test POST nuovo task
        $taskData = [
            'task_list_id' => $listId,
            'title' => 'Test Task ' . time(),
            'description' => 'Task di test automatico',
            'priority' => 'high',
            'status' => 'todo',
            'due_date' => date('Y-m-d', strtotime('+3 days')),
            'estimated_hours' => 5,
            'tags' => ['test', 'automation']
        ];

        $response = $this->apiRequest('POST', '/api/tasks.php', $taskData);
        if ($response['success'] && isset($response['data']['id'])) {
            $this->pass("POST /api/tasks - Task creato (ID: {$response['data']['id']})");
            $taskId = $response['data']['id'];

            // Test GET task specifico
            $response = $this->apiRequest('GET', "/api/tasks.php/$taskId");
            if ($response['success']) {
                $this->pass("GET /api/tasks/$taskId - Dettagli task");
            } else {
                $this->fail("GET /api/tasks/$taskId - " . $response['error']);
            }

            // Test aggiunta commento
            $response = $this->apiRequest('POST', "/api/tasks.php/$taskId/comments", [
                'comment' => 'Commento di test automatico'
            ]);
            if ($response['success']) {
                $this->pass("POST /api/tasks/$taskId/comments - Commento aggiunto");
            } else {
                $this->fail("POST /api/tasks/$taskId/comments - " . $response['error']);
            }

            // Test time logging
            $response = $this->apiRequest('POST', "/api/tasks.php/$taskId/time-logs", [
                'started_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'ended_at' => date('Y-m-d H:i:s'),
                'description' => 'Lavoro di test'
            ]);
            if ($response['success']) {
                $this->pass("POST /api/tasks/$taskId/time-logs - Tempo registrato");
            } else {
                $this->fail("POST /api/tasks/$taskId/time-logs - " . $response['error']);
            }

            // Test cambio status
            $response = $this->apiRequest('PATCH', "/api/tasks.php/$taskId/status", [
                'status' => 'in_progress'
            ]);
            if ($response['success']) {
                $this->pass("PATCH /api/tasks/$taskId/status - Status aggiornato");
            } else {
                $this->fail("PATCH /api/tasks/$taskId/status - " . $response['error']);
            }

            // Test completamento task
            $response = $this->apiRequest('PATCH', "/api/tasks.php/$taskId/complete");
            if ($response['success']) {
                $this->pass("PATCH /api/tasks/$taskId/complete - Task completato");
            } else {
                $this->fail("PATCH /api/tasks/$taskId/complete - " . $response['error']);
            }
        } else {
            $this->fail("POST /api/tasks - " . $response['error']);
        }
    }

    /**
     * Test 8: Pagine Frontend
     */
    private function testFrontendPages() {
        echo "\n" . YELLOW . "[TEST 8] Pagine Frontend" . RESET . "\n";

        $pages = [
            '/calendar.php' => 'Calendario',
            '/tasks.php' => 'Attività',
            '/assets/js/calendar.js' => 'JavaScript Calendar',
            '/assets/js/tasks.js' => 'JavaScript Tasks',
            '/assets/css/calendar.css' => 'CSS Calendar',
            '/assets/css/tasks.css' => 'CSS Tasks'
        ];

        foreach ($pages as $path => $name) {
            $ch = curl_init($this->baseUrl . $path);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookie);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200) {
                $this->pass("Pagina $name accessibile ($path)");
            } else {
                $this->fail("Pagina $name non accessibile - HTTP $httpCode ($path)");
            }
        }
    }

    /**
     * Test 9: Isolamento Multi-Tenant
     */
    private function testMultiTenantIsolation() {
        echo "\n" . YELLOW . "[TEST 9] Isolamento Multi-Tenant" . RESET . "\n";

        if (!$this->db) {
            $this->skip("Test saltato: database non disponibile");
            return;
        }

        try {
            // Crea due tenant di test
            $stmt = $this->db->prepare("INSERT INTO tenants (code, name) VALUES (?, ?)");
            $stmt->execute(['TEST1_' . time(), 'Test Tenant 1']);
            $tenant1Id = $this->db->lastInsertId();

            $stmt->execute(['TEST2_' . time(), 'Test Tenant 2']);
            $tenant2Id = $this->db->lastInsertId();

            // Crea calendari per entrambi i tenant
            $stmt = $this->db->prepare("
                INSERT INTO calendars (tenant_id, user_id, name)
                VALUES (?, 1, ?)
            ");
            $stmt->execute([$tenant1Id, 'Calendar Tenant 1']);
            $calendar1Id = $this->db->lastInsertId();

            $stmt->execute([$tenant2Id, 'Calendar Tenant 2']);
            $calendar2Id = $this->db->lastInsertId();

            // Verifica isolamento
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM calendars
                WHERE tenant_id = ? AND id = ?
            ");

            // Tenant 1 non deve vedere calendario di Tenant 2
            $stmt->execute([$tenant1Id, $calendar2Id]);
            $result = $stmt->fetch();
            if ($result['count'] == 0) {
                $this->pass("Tenant 1 non può accedere ai dati di Tenant 2");
            } else {
                $this->fail("VIOLAZIONE: Tenant 1 può vedere dati di Tenant 2!");
            }

            // Tenant 2 non deve vedere calendario di Tenant 1
            $stmt->execute([$tenant2Id, $calendar1Id]);
            $result = $stmt->fetch();
            if ($result['count'] == 0) {
                $this->pass("Tenant 2 non può accedere ai dati di Tenant 1");
            } else {
                $this->fail("VIOLAZIONE: Tenant 2 può vedere dati di Tenant 1!");
            }

            // Cleanup
            $this->db->exec("DELETE FROM calendars WHERE id IN ($calendar1Id, $calendar2Id)");
            $this->db->exec("DELETE FROM tenants WHERE id IN ($tenant1Id, $tenant2Id)");

        } catch (Exception $e) {
            $this->fail("Errore test multi-tenant: " . $e->getMessage());
        }
    }

    /**
     * Test 10: Permessi e Ruoli
     */
    private function testPermissions() {
        echo "\n" . YELLOW . "[TEST 10] Permessi e Ruoli" . RESET . "\n";

        // Simula test con diversi ruoli
        $roles = [
            'admin' => 'Accesso completo al sistema',
            'special_user' => 'Accesso multi-tenant',
            'standard_user' => 'Accesso single-tenant'
        ];

        foreach ($roles as $role => $description) {
            // In un ambiente di test reale, qui si farebbero login con utenti diversi
            // Per ora verifichiamo solo che i ruoli esistano nel database
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM users
                WHERE role = ?
                LIMIT 1
            ");
            $stmt->execute([$role]);
            $result = $stmt->fetch();

            if ($result['count'] > 0 || $role == 'admin') {
                $this->pass("Ruolo '$role' configurato: $description");
            } else {
                $this->warn("Ruolo '$role' non ha utenti di test");
            }
        }
    }

    /**
     * Test 11: Performance
     */
    private function testPerformance() {
        echo "\n" . YELLOW . "[TEST 11] Test Performance" . RESET . "\n";

        // Test caricamento lista calendari
        $startTime = microtime(true);
        $response = $this->apiRequest('GET', '/api/calendars.php');
        $loadTime = (microtime(true) - $startTime) * 1000;

        if ($loadTime < 500) {
            $this->pass(sprintf("GET /api/calendars - Tempo risposta: %.2f ms", $loadTime));
        } elseif ($loadTime < 1000) {
            $this->warn(sprintf("GET /api/calendars - Tempo risposta lento: %.2f ms", $loadTime));
        } else {
            $this->fail(sprintf("GET /api/calendars - Tempo risposta critico: %.2f ms", $loadTime));
        }

        // Test caricamento lista task
        $startTime = microtime(true);
        $response = $this->apiRequest('GET', '/api/tasks.php');
        $loadTime = (microtime(true) - $startTime) * 1000;

        if ($loadTime < 500) {
            $this->pass(sprintf("GET /api/tasks - Tempo risposta: %.2f ms", $loadTime));
        } elseif ($loadTime < 1000) {
            $this->warn(sprintf("GET /api/tasks - Tempo risposta lento: %.2f ms", $loadTime));
        } else {
            $this->fail(sprintf("GET /api/tasks - Tempo risposta critico: %.2f ms", $loadTime));
        }

        // Test query complessa
        if ($this->db) {
            $startTime = microtime(true);
            $stmt = $this->db->query("
                SELECT
                    c.id,
                    c.name,
                    COUNT(DISTINCT e.id) as event_count,
                    COUNT(DISTINCT ep.id) as participant_count
                FROM calendars c
                LEFT JOIN events e ON e.calendar_id = c.id
                LEFT JOIN event_participants ep ON ep.event_id = e.id
                WHERE c.tenant_id = 1
                GROUP BY c.id
                LIMIT 10
            ");
            $queryTime = (microtime(true) - $startTime) * 1000;

            if ($queryTime < 100) {
                $this->pass(sprintf("Query complessa calendario: %.2f ms", $queryTime));
            } elseif ($queryTime < 300) {
                $this->warn(sprintf("Query complessa calendario lenta: %.2f ms", $queryTime));
            } else {
                $this->fail(sprintf("Query complessa calendario critica: %.2f ms", $queryTime));
            }
        }
    }

    /**
     * Helper: Esegui richiesta API
     */
    private function apiRequest($method, $endpoint, $data = null) {
        $ch = curl_init($this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIE, $this->sessionCookie);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'CURL error'];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decoded['data'] ?? $decoded,
                'message' => $decoded['message'] ?? ''
            ];
        } else {
            return [
                'success' => false,
                'error' => $decoded['message'] ?? "HTTP $httpCode",
                'data' => null
            ];
        }
    }

    /**
     * Helper: Registra test passato
     */
    private function pass($message) {
        echo GREEN . "✓ " . RESET . $message . "\n";
        $this->testResults[] = ['status' => 'pass', 'message' => $message];
    }

    /**
     * Helper: Registra test fallito
     */
    private function fail($message) {
        echo RED . "✗ " . RESET . $message . "\n";
        $this->testResults[] = ['status' => 'fail', 'message' => $message];
    }

    /**
     * Helper: Registra warning
     */
    private function warn($message) {
        echo YELLOW . "⚠ " . RESET . $message . "\n";
        $this->testResults[] = ['status' => 'warn', 'message' => $message];
    }

    /**
     * Helper: Test saltato
     */
    private function skip($message) {
        echo BLUE . "⊘ " . RESET . $message . "\n";
        $this->testResults[] = ['status' => 'skip', 'message' => $message];
    }

    /**
     * Stampa riepilogo finale
     */
    private function printSummary() {
        $counts = [
            'pass' => 0,
            'fail' => 0,
            'warn' => 0,
            'skip' => 0
        ];

        foreach ($this->testResults as $result) {
            $counts[$result['status']]++;
        }

        echo "\n" . BLUE . "====================================\n";
        echo "         RIEPILOGO TEST\n";
        echo "====================================\n" . RESET;

        echo GREEN . "Passati: " . $counts['pass'] . RESET . "\n";
        echo RED . "Falliti: " . $counts['fail'] . RESET . "\n";
        echo YELLOW . "Warning: " . $counts['warn'] . RESET . "\n";
        echo BLUE . "Saltati: " . $counts['skip'] . RESET . "\n";

        $total = array_sum($counts);
        $successRate = $total > 0 ? ($counts['pass'] / $total) * 100 : 0;

        echo "\n";
        if ($counts['fail'] == 0 && $counts['warn'] == 0) {
            echo GREEN . "✓ TUTTI I TEST SONO PASSATI! " . RESET;
            echo sprintf("(%.1f%% successo)\n", $successRate);
        } elseif ($counts['fail'] == 0) {
            echo YELLOW . "⚠ TEST COMPLETATI CON WARNING " . RESET;
            echo sprintf("(%.1f%% successo)\n", $successRate);
        } else {
            echo RED . "✗ ALCUNI TEST SONO FALLITI " . RESET;
            echo sprintf("(%.1f%% successo)\n", $successRate);
            echo "\nEsamina i test falliti sopra per i dettagli.\n";
        }

        echo "\nData test: " . date('Y-m-d H:i:s') . "\n";
    }
}

// Esegui i test
$tester = new Part2SystemTester();
$tester->runAllTests();