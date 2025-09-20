<?php declare(strict_types=1);

/**
 * API Endpoint per la gestione dei task
 * Supporta CRUD, assegnazioni, commenti e time tracking
 */

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

// Carica configurazione e dipendenze
require_once __DIR__ . '/../config_v2.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/SimpleAuth.php';
require_once __DIR__ . '/../includes/session_helper.php';
require_once __DIR__ . '/../includes/TaskManager.php';

use Collabora\Session\SessionHelper;
use Collabora\Tasks\TaskManager;

// Inizializza sessione
SessionHelper::init();

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

// Verifica autenticazione
if (!SessionHelper::isAuthenticated()) {
    sendError('Non autenticato', 401);
}

// Ottieni dati utente dalla sessione
$currentUser = SessionHelper::getCurrentUser();
$tenantId = $_SESSION['current_tenant_id'] ?? 0;
$userId = $currentUser['id'] ?? 0;

if (!$tenantId || !$userId) {
    sendError('Sessione non valida', 401);
}

try {
    // Inizializza connessione database e manager
    $db = getDbConnection();
    $taskManager = new TaskManager($db, $tenantId, $userId);

    // Ottieni metodo HTTP e percorso
    $method = $_SERVER['REQUEST_METHOD'];
    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    $segments = array_filter(explode('/', $pathInfo));
    $taskId = isset($segments[0]) ? (int) $segments[0] : null;
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
            if ($taskId) {
                // GET /api/tasks/{id} - Ottieni task specifico
                $task = $taskManager->getTask($taskId);

                if (!$task) {
                    sendError('Task non trovato', 404);
                }

                sendResponse([
                    'success' => true,
                    'data' => $task
                ]);
            } else {
                // GET /api/tasks - Lista task con filtri
                $filters = [
                    'task_list_id' => $query['list_id'] ?? null,
                    'status' => $query['status'] ?? null,
                    'assignee_id' => $query['assignee_id'] ?? null,
                    'priority' => $query['priority'] ?? null,
                    'due_date_from' => $query['due_date_from'] ?? null,
                    'due_date_to' => $query['due_date_to'] ?? null,
                    'overdue' => !empty($query['overdue']),
                    'search' => $query['search'] ?? null,
                    'root_only' => !empty($query['root_only']),
                    'include_assignees' => !empty($query['include_assignees']),
                    'include_subtask_count' => !empty($query['include_subtask_count']),
                    'sort_by' => $query['sort_by'] ?? 'position',
                    'sort_order' => $query['sort_order'] ?? 'ASC',
                    'limit' => isset($query['limit']) ? (int) $query['limit'] : 50,
                    'offset' => isset($query['offset']) ? (int) $query['offset'] : 0
                ];

                // Gestione stati multipli
                if (!empty($query['status']) && strpos($query['status'], ',') !== false) {
                    $filters['status'] = explode(',', $query['status']);
                }

                // Filtra parametri vuoti
                $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

                $tasks = $taskManager->getTasks($filters);

                // Statistiche se richieste
                $stats = null;
                if (!empty($query['include_stats'])) {
                    $totalTasks = count($tasks);
                    $completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'done'));
                    $overdueTasks = count(array_filter($tasks, fn($t) =>
                        $t['status'] !== 'done' &&
                        !empty($t['due_date']) &&
                        strtotime($t['due_date']) < time()
                    ));

                    $stats = [
                        'total' => $totalTasks,
                        'completed' => $completedTasks,
                        'overdue' => $overdueTasks,
                        'completion_rate' => $totalTasks > 0
                            ? round(($completedTasks / $totalTasks) * 100, 1)
                            : 0
                    ];
                }

                sendResponse([
                    'success' => true,
                    'data' => [
                        'tasks' => $tasks,
                        'total' => count($tasks),
                        'filters' => $filters,
                        'stats' => $stats
                    ]
                ]);
            }
            break;

        case 'POST':
            if ($taskId) {
                // Azioni specifiche per task esistente
                switch ($action) {
                    case 'comments':
                        // POST /api/tasks/{id}/comments - Aggiungi commento
                        if (empty($input['comment'])) {
                            sendError('Testo commento richiesto');
                        }

                        $comment = $taskManager->addComment(
                            $taskId,
                            $input['comment'],
                            $input['mentions'] ?? []
                        );

                        sendResponse([
                            'success' => true,
                            'message' => 'Commento aggiunto con successo',
                            'data' => $comment
                        ]);
                        break;

                    case 'assignments':
                        // POST /api/tasks/{id}/assignments - Assegna utenti
                        if (empty($input['user_ids'])) {
                            sendError('Lista ID utenti richiesta');
                        }

                        $result = $taskManager->assignTask($taskId, $input['user_ids']);

                        if ($result) {
                            sendResponse([
                                'success' => true,
                                'message' => 'Utenti assegnati con successo'
                            ]);
                        } else {
                            sendError('Errore durante l\'assegnazione');
                        }
                        break;

                    case 'time-logs':
                        // POST /api/tasks/{id}/time-logs - Registra tempo
                        if (empty($input['duration_minutes']) &&
                            (empty($input['started_at']) || empty($input['ended_at']))) {
                            sendError('Durata o intervallo temporale richiesto');
                        }

                        $timeLog = $taskManager->logTime($taskId, $input);

                        sendResponse([
                            'success' => true,
                            'message' => 'Tempo registrato con successo',
                            'data' => $timeLog
                        ]);
                        break;

                    case 'subtasks':
                        // POST /api/tasks/{id}/subtasks - Crea sottoattività
                        if (empty($input['title'])) {
                            sendError('Titolo sottoattività richiesto');
                        }

                        // Ottieni task padre per ereditare lista
                        $parentTask = $taskManager->getTask($taskId);
                        if (!$parentTask) {
                            sendError('Task padre non trovato', 404);
                        }

                        $subtaskData = array_merge($input, [
                            'parent_task_id' => $taskId,
                            'task_list_id' => $parentTask['task_list_id']
                        ]);

                        $subtask = $taskManager->createTask($subtaskData);

                        sendResponse([
                            'success' => true,
                            'message' => 'Sottoattività creata con successo',
                            'data' => $subtask
                        ], 201);
                        break;

                    case 'duplicate':
                        // POST /api/tasks/{id}/duplicate - Duplica task
                        $originalTask = $taskManager->getTask($taskId);
                        if (!$originalTask) {
                            sendError('Task originale non trovato', 404);
                        }

                        // Prepara dati per duplicazione
                        $newTaskData = $originalTask;
                        unset(
                            $newTaskData['id'],
                            $newTaskData['created_at'],
                            $newTaskData['updated_at'],
                            $newTaskData['completed_at'],
                            $newTaskData['actual_hours']
                        );

                        // Sovrascrivi con eventuali nuovi dati
                        $newTaskData = array_merge($newTaskData, $input);
                        if (empty($newTaskData['title'])) {
                            $newTaskData['title'] = $originalTask['title'] . ' (copia)';
                        }

                        $newTask = $taskManager->createTask($newTaskData);

                        sendResponse([
                            'success' => true,
                            'message' => 'Task duplicato con successo',
                            'data' => $newTask
                        ], 201);
                        break;

                    default:
                        sendError('Azione non valida', 400);
                }
            } else {
                // POST /api/tasks - Crea nuovo task
                // Validazione campi richiesti
                if (empty($input['task_list_id'])) {
                    sendError('ID lista task richiesto');
                }

                if (empty($input['title'])) {
                    sendError('Titolo task richiesto');
                }

                // Validazione date se fornite
                if (!empty($input['due_date'])) {
                    $dueDate = strtotime($input['due_date']);
                    if ($dueDate === false) {
                        sendError('Formato data scadenza non valido');
                    }

                    // Se c'è una data di inizio, verifica che sia precedente
                    if (!empty($input['start_date'])) {
                        $startDate = strtotime($input['start_date']);
                        if ($startDate === false) {
                            sendError('Formato data inizio non valido');
                        }
                        if ($startDate > $dueDate) {
                            sendError('La data di inizio deve precedere la data di scadenza');
                        }
                    }
                }

                $task = $taskManager->createTask($input);

                sendResponse([
                    'success' => true,
                    'message' => 'Task creato con successo',
                    'data' => $task
                ], 201);
            }
            break;

        case 'PUT':
        case 'PATCH':
            if (!$taskId) {
                sendError('ID task richiesto');
            }

            if ($action === 'move') {
                // PATCH /api/tasks/{id}/move - Sposta task (drag & drop)
                $moveData = [];

                if (isset($input['task_list_id'])) {
                    $moveData['task_list_id'] = $input['task_list_id'];
                }

                if (isset($input['position'])) {
                    $moveData['position'] = $input['position'];
                }

                if (isset($input['status'])) {
                    $moveData['status'] = $input['status'];
                }

                if (empty($moveData)) {
                    sendError('Nessun parametro di spostamento fornito');
                }

                $result = $taskManager->moveTask($taskId, $moveData);

                if ($result) {
                    sendResponse([
                        'success' => true,
                        'message' => 'Task spostato con successo'
                    ]);
                } else {
                    sendError('Errore durante lo spostamento del task');
                }
            } elseif ($action === 'status') {
                // PATCH /api/tasks/{id}/status - Cambia stato rapido
                if (empty($input['status'])) {
                    sendError('Nuovo stato richiesto');
                }

                $task = $taskManager->updateTask($taskId, [
                    'status' => $input['status']
                ]);

                sendResponse([
                    'success' => true,
                    'message' => 'Stato aggiornato con successo',
                    'data' => $task
                ]);
            } elseif ($action === 'complete') {
                // PATCH /api/tasks/{id}/complete - Marca come completato
                $task = $taskManager->updateTask($taskId, [
                    'status' => 'done',
                    'progress_percentage' => 100
                ]);

                sendResponse([
                    'success' => true,
                    'message' => 'Task completato con successo',
                    'data' => $task
                ]);
            } else {
                // PUT/PATCH /api/tasks/{id} - Aggiorna task
                // Validazione date se fornite
                if (isset($input['due_date']) && isset($input['start_date'])) {
                    $startDate = strtotime($input['start_date']);
                    $dueDate = strtotime($input['due_date']);

                    if ($startDate === false || $dueDate === false) {
                        sendError('Formato data non valido');
                    }

                    if ($startDate > $dueDate) {
                        sendError('La data di inizio deve precedere la data di scadenza');
                    }
                }

                $task = $taskManager->updateTask($taskId, $input);

                sendResponse([
                    'success' => true,
                    'message' => 'Task aggiornato con successo',
                    'data' => $task
                ]);
            }
            break;

        case 'DELETE':
            if (!$taskId) {
                sendError('ID task richiesto');
            }

            // DELETE /api/tasks/{id} - Elimina task
            $result = $taskManager->deleteTask($taskId);

            if ($result) {
                sendResponse([
                    'success' => true,
                    'message' => 'Task eliminato con successo'
                ]);
            } else {
                sendError('Errore durante l\'eliminazione del task');
            }
            break;

        default:
            sendError('Metodo non supportato', 405);
    }

} catch (Exception $e) {
    error_log('Tasks API Error: ' . $e->getMessage());
    sendError('Errore server: ' . $e->getMessage(), 500);
}