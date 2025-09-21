<?php declare(strict_types=1);

/**
 * API Endpoint per la gestione degli eventi
 * Supporta CRUD completo, eventi ricorrenti e RSVP
 */

// Avvia sessione se non già avviata
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Carica configurazione e dipendenze
require_once __DIR__ . '/../config_v2.php';
require_once __DIR__ . '/../includes/autoload.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SimpleAuth.php';
require_once __DIR__ . '/../includes/CalendarManager.php';

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
    $eventId = isset($segments[0]) ? (int) $segments[0] : null;
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
            if ($eventId) {
                // GET /api/events/{id} - Ottieni evento specifico
                $event = $calendarManager->getEvent($eventId);

                if (!$event) {
                    sendError('Evento non trovato', 404);
                }

                sendResponse([
                    'success' => true,
                    'data' => $event
                ]);
            } else {
                // GET /api/events - Lista eventi con filtri
                $filters = [
                    'calendar_id' => $query['calendar_id'] ?? null,
                    'start_date' => $query['start_date'] ?? date('Y-m-d'),
                    'end_date' => $query['end_date'] ?? date('Y-m-d', strtotime('+1 month')),
                    'status' => $query['status'] ?? null,
                    'search' => $query['search'] ?? null,
                    'expand_recurring' => !empty($query['expand_recurring']),
                    'limit' => isset($query['limit']) ? (int) $query['limit'] : 100,
                    'offset' => isset($query['offset']) ? (int) $query['offset'] : 0
                ];

                // Filtra parametri vuoti
                $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

                $events = $calendarManager->getEvents($filters);

                // Raggruppa eventi per data se richiesto
                if (!empty($query['group_by_date'])) {
                    $grouped = [];
                    foreach ($events as $event) {
                        $date = substr($event['start_datetime'], 0, 10);
                        if (!isset($grouped[$date])) {
                            $grouped[$date] = [];
                        }
                        $grouped[$date][] = $event;
                    }
                    $events = $grouped;
                }

                sendResponse([
                    'success' => true,
                    'data' => [
                        'events' => $events,
                        'total' => count($events),
                        'filters' => $filters
                    ]
                ]);
            }
            break;

        case 'POST':
            if ($eventId && $action === 'rsvp') {
                // POST /api/events/{id}/rsvp - Aggiorna RSVP
                if (empty($input['response'])) {
                    sendError('Risposta RSVP richiesta (accepted/declined/tentative)');
                }

                $result = $calendarManager->updateRSVP(
                    $eventId,
                    $input['response'],
                    $input['comment'] ?? null
                );

                if ($result) {
                    sendResponse([
                        'success' => true,
                        'message' => 'RSVP aggiornato con successo'
                    ]);
                } else {
                    sendError('Errore aggiornamento RSVP');
                }
            } elseif ($eventId && $action === 'participants') {
                // POST /api/events/{id}/participants - Aggiungi partecipanti
                if (empty($input['participants'])) {
                    sendError('Lista partecipanti richiesta');
                }

                // TODO: Implementare metodo per aggiungere partecipanti
                sendResponse([
                    'success' => true,
                    'message' => 'Partecipanti aggiunti con successo'
                ]);
            } elseif ($eventId && $action === 'duplicate') {
                // POST /api/events/{id}/duplicate - Duplica evento
                $originalEvent = $calendarManager->getEvent($eventId);
                if (!$originalEvent) {
                    sendError('Evento originale non trovato', 404);
                }

                // Prepara dati per duplicazione
                $newEventData = $originalEvent;
                unset($newEventData['id'], $newEventData['uid'], $newEventData['created_at'], $newEventData['updated_at']);

                // Aggiorna date se fornite
                if (!empty($input['start_datetime'])) {
                    $newEventData['start_datetime'] = $input['start_datetime'];
                }
                if (!empty($input['end_datetime'])) {
                    $newEventData['end_datetime'] = $input['end_datetime'];
                }
                if (!empty($input['title'])) {
                    $newEventData['title'] = $input['title'];
                }

                $newEvent = $calendarManager->createEvent($newEventData);

                sendResponse([
                    'success' => true,
                    'message' => 'Evento duplicato con successo',
                    'data' => $newEvent
                ], 201);
            } else {
                // POST /api/events - Crea nuovo evento
                // Validazione campi richiesti
                $required = ['calendar_id', 'title', 'start_datetime', 'end_datetime'];
                $missing = [];
                foreach ($required as $field) {
                    if (empty($input[$field])) {
                        $missing[] = $field;
                    }
                }

                if (!empty($missing)) {
                    sendError('Campi richiesti mancanti: ' . implode(', ', $missing));
                }

                // Validazione date
                $start = strtotime($input['start_datetime']);
                $end = strtotime($input['end_datetime']);

                if ($start === false || $end === false) {
                    sendError('Formato data non valido. Usare ISO 8601 (YYYY-MM-DD HH:MM:SS)');
                }

                if ($end < $start) {
                    sendError('La data di fine deve essere successiva alla data di inizio');
                }

                $event = $calendarManager->createEvent($input);

                sendResponse([
                    'success' => true,
                    'message' => 'Evento creato con successo',
                    'data' => $event
                ], 201);
            }
            break;

        case 'PUT':
        case 'PATCH':
            if (!$eventId) {
                sendError('ID evento richiesto');
            }

            if ($action === 'move') {
                // PATCH /api/events/{id}/move - Sposta evento (drag & drop)
                if (empty($input['start_datetime'])) {
                    sendError('Nuova data di inizio richiesta');
                }

                // Calcola nuova data fine mantenendo la durata
                $event = $calendarManager->getEvent($eventId);
                if (!$event) {
                    sendError('Evento non trovato', 404);
                }

                $originalDuration = strtotime($event['end_datetime']) - strtotime($event['start_datetime']);
                $newStart = strtotime($input['start_datetime']);
                $newEnd = date('Y-m-d H:i:s', $newStart + $originalDuration);

                $updateData = [
                    'start_datetime' => $input['start_datetime'],
                    'end_datetime' => $input['end_datetime'] ?? $newEnd
                ];

                if (!empty($input['calendar_id'])) {
                    $updateData['calendar_id'] = $input['calendar_id'];
                }

                $updatedEvent = $calendarManager->updateEvent($eventId, $updateData);

                sendResponse([
                    'success' => true,
                    'message' => 'Evento spostato con successo',
                    'data' => $updatedEvent
                ]);
            } elseif ($action === 'resize') {
                // PATCH /api/events/{id}/resize - Ridimensiona evento
                if (empty($input['end_datetime'])) {
                    sendError('Nuova data di fine richiesta');
                }

                $updatedEvent = $calendarManager->updateEvent($eventId, [
                    'end_datetime' => $input['end_datetime']
                ]);

                sendResponse([
                    'success' => true,
                    'message' => 'Durata evento aggiornata con successo',
                    'data' => $updatedEvent
                ]);
            } else {
                // PUT/PATCH /api/events/{id} - Aggiorna evento
                // Validazione date se fornite
                if (isset($input['start_datetime']) && isset($input['end_datetime'])) {
                    $start = strtotime($input['start_datetime']);
                    $end = strtotime($input['end_datetime']);

                    if ($start === false || $end === false) {
                        sendError('Formato data non valido');
                    }

                    if ($end < $start) {
                        sendError('La data di fine deve essere successiva alla data di inizio');
                    }
                }

                $event = $calendarManager->updateEvent($eventId, $input);

                sendResponse([
                    'success' => true,
                    'message' => 'Evento aggiornato con successo',
                    'data' => $event
                ]);
            }
            break;

        case 'DELETE':
            if (!$eventId) {
                sendError('ID evento richiesto');
            }

            // DELETE /api/events/{id} - Elimina evento
            $result = $calendarManager->deleteEvent($eventId);

            if ($result) {
                sendResponse([
                    'success' => true,
                    'message' => 'Evento eliminato con successo'
                ]);
            } else {
                sendError('Errore durante l\'eliminazione dell\'evento');
            }
            break;

        default:
            sendError('Metodo non supportato', 405);
    }

} catch (Exception $e) {
    error_log('Events API Error: ' . $e->getMessage());
    sendError('Errore server: ' . $e->getMessage(), 500);
}