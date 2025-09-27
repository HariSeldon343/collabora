<?php declare(strict_types=1);

/**
 * API Endpoint per la gestione dei calendari
 * Supporta CRUD completo con multi-tenancy
 */

// Carica configurazione PRIMA di qualsiasi header o output
require_once __DIR__ . '/../config_v2.php';

// Abilita CORS e headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Gestione preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error reporting per debugging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Carica altre dipendenze
require_once __DIR__ . '/../includes/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SimpleAuth.php';
require_once __DIR__ . '/../includes/session_helper.php';
require_once __DIR__ . '/../includes/CalendarManager.php';

use Collabora\Session\SessionHelper;
use Collabora\Calendar\CalendarManager;

// Funzione per inviare risposta JSON
function sendResponse(array $data, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Funzione per inviare errore
function sendError(string $message, int $httpCode = 400, array $extra = []): void {
    $response = array_merge([
        'success' => false,
        'message' => $message
    ], $extra);
    sendResponse($response, $httpCode);
}

try {
    // Verifica autenticazione usando SimpleAuth
    $auth = new SimpleAuth();
    if (!$auth->isAuthenticated()) {
        sendError('Non autenticato', 401);
    }

    // Ottieni dati utente dalla sessione
    $currentUser = $auth->getCurrentUser();
    $tenantId = $_SESSION['current_tenant_id'] ?? 0;
    $userId = $_SESSION['user_id'] ?? 0;

    if (!$tenantId || !$userId) {
        sendError('Sessione non valida', 401);
    }

    // Inizializza connessione database e manager
    $db = getDbConnection();
    $calendarManager = new CalendarManager($db, $tenantId, $userId);

    // Ottieni metodo HTTP e percorso
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $segments = array_filter(explode('/', $pathInfo));
    $calendarId = isset($segments[0]) ? (int) $segments[0] : null;
    $action = $segments[1] ?? null;

    // Ottieni dati input
    $input = [];
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $rawInput = file_get_contents('php://input');
        if ($rawInput) {
            $input = json_decode($rawInput, true) ?? [];
        }
    }

    // Ottieni parametri query
    $query = $_GET;

    // Router per le azioni
    switch ($method) {
        case 'GET':
            if ($calendarId) {
                // GET /api/calendars/{id} - Ottieni calendario specifico
                if ($action === 'events') {
                    // GET /api/calendars/{id}/events - Eventi del calendario
                    $filters = [
                        'calendar_id' => $calendarId,
                        'start_date' => $query['start_date'] ?? date('Y-m-d'),
                        'end_date' => $query['end_date'] ?? date('Y-m-d', strtotime('+1 month')),
                        'expand_recurring' => !empty($query['expand_recurring'])
                    ];

                    $events = $calendarManager->getEvents($filters);

                    sendResponse([
                        'success' => true,
                        'data' => [
                            'calendar_id' => $calendarId,
                            'events' => $events,
                            'total' => count($events)
                        ]
                    ]);
                } elseif ($action === 'shares') {
                    // GET /api/calendars/{id}/shares - Condivisioni calendario
                    // TODO: Implementare getCalendarShares
                    sendResponse([
                        'success' => true,
                        'data' => [
                            'calendar_id' => $calendarId,
                            'shares' => []
                        ]
                    ]);
                } else {
                    // Ottieni dettagli calendario
                    $calendar = $calendarManager->getCalendar($calendarId);

                    if (!$calendar) {
                        sendError('Calendario non trovato', 404);
                    }

                    sendResponse([
                        'success' => true,
                        'data' => $calendar
                    ]);
                }
            } else {
                // GET /api/calendars - Lista calendari
                $filters = [
                    'owned_only' => !empty($query['owned_only']),
                    'shared_with_me' => !empty($query['shared_with_me'])
                ];

                $calendars = $calendarManager->getCalendars($filters);

                sendResponse([
                    'success' => true,
                    'data' => [
                        'calendars' => $calendars,
                        'total' => count($calendars)
                    ]
                ]);
            }
            break;

        case 'POST':
            if ($calendarId && $action === 'share') {
                // POST /api/calendars/{id}/share - Condividi calendario
                if (empty($input['user_id'])) {
                    sendError('ID utente richiesto');
                }

                $permission = $input['permission'] ?? 'view';
                $result = $calendarManager->shareCalendar(
                    $calendarId,
                    (int) $input['user_id'],
                    $permission
                );

                if ($result) {
                    sendResponse([
                        'success' => true,
                        'message' => 'Calendario condiviso con successo'
                    ]);
                } else {
                    sendError('Errore durante la condivisione del calendario');
                }
            } else {
                // POST /api/calendars - Crea nuovo calendario
                if (empty($input['name'])) {
                    sendError('Nome calendario richiesto');
                }

                $calendar = $calendarManager->createCalendar($input);

                sendResponse([
                    'success' => true,
                    'message' => 'Calendario creato con successo',
                    'data' => $calendar
                ], 201);
            }
            break;

        case 'PUT':
        case 'PATCH':
            if (!$calendarId) {
                sendError('ID calendario richiesto');
            }

            // PUT/PATCH /api/calendars/{id} - Aggiorna calendario
            $calendar = $calendarManager->updateCalendar($calendarId, $input);

            sendResponse([
                'success' => true,
                'message' => 'Calendario aggiornato con successo',
                'data' => $calendar
            ]);
            break;

        case 'DELETE':
            if (!$calendarId) {
                sendError('ID calendario richiesto');
            }

            // DELETE /api/calendars/{id} - Elimina calendario
            $result = $calendarManager->deleteCalendar($calendarId);

            if ($result) {
                sendResponse([
                    'success' => true,
                    'message' => 'Calendario eliminato con successo'
                ]);
            } else {
                sendError('Errore durante l\'eliminazione del calendario');
            }
            break;

        default:
            sendError('Metodo non supportato', 405);
    }

} catch (Exception $e) {
    error_log('Calendar API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    sendError('Errore interno del server', 500, ['debug' => $e->getMessage()]);
}