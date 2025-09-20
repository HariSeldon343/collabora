<?php declare(strict_types=1);

/**
 * Gestione task e liste di attività
 * Supporta multi-tenancy, task gerarchici e time tracking
 */

namespace Collabora\Tasks;

use PDO;
use Exception;
use DateTime;

class TaskManager {
    private PDO $db;
    private int $tenantId;
    private int $userId;

    /**
     * Costruttore
     */
    public function __construct(PDO $db, int $tenantId, int $userId) {
        $this->db = $db;
        $this->tenantId = $tenantId;
        $this->userId = $userId;
    }

    /**
     * Crea una nuova lista di task
     */
    public function createTaskList(array $data): array {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO task_lists (
                    tenant_id, owner_id, name, description, color,
                    icon, board_type, default_view, workflow_states,
                    settings, position
                ) VALUES (
                    :tenant_id, :owner_id, :name, :description, :color,
                    :icon, :board_type, :default_view, :workflow_states,
                    :settings, :position
                )
            ");

            // Calcola posizione successiva
            $position = $this->getNextPosition('task_lists');

            // Stati workflow di default
            $defaultWorkflow = [
                ['id' => 'todo', 'name' => 'Da fare', 'color' => '#6B7280'],
                ['id' => 'in_progress', 'name' => 'In corso', 'color' => '#3B82F6'],
                ['id' => 'done', 'name' => 'Completato', 'color' => '#10B981']
            ];

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'owner_id' => $this->userId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#4F46E5',
                'icon' => $data['icon'] ?? 'clipboard-list',
                'board_type' => $data['board_type'] ?? 'kanban',
                'default_view' => $data['default_view'] ?? 'board',
                'workflow_states' => json_encode($data['workflow_states'] ?? $defaultWorkflow),
                'settings' => json_encode($data['settings'] ?? []),
                'position' => $data['position'] ?? $position
            ]);

            $listId = (int) $this->db->lastInsertId();

            $this->db->commit();

            return $this->getTaskList($listId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Errore creazione lista task: " . $e->getMessage());
        }
    }

    /**
     * Ottiene una lista di task specifica
     */
    public function getTaskList(int $listId): ?array {
        $stmt = $this->db->prepare("
            SELECT tl.*, u.first_name, u.last_name, u.email as owner_email,
                   COUNT(DISTINCT t.id) as task_count,
                   COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.id END) as completed_count,
                   COUNT(DISTINCT CASE WHEN t.status != 'done' AND t.due_date < NOW() THEN t.id END) as overdue_count
            FROM task_lists tl
            LEFT JOIN users u ON tl.owner_id = u.id
            LEFT JOIN tasks t ON tl.id = t.task_list_id AND t.deleted_at IS NULL
            WHERE tl.id = :list_id
            AND tl.tenant_id = :tenant_id
            AND tl.deleted_at IS NULL
            GROUP BY tl.id
        ");

        $stmt->execute([
            'list_id' => $listId,
            'tenant_id' => $this->tenantId
        ]);

        $list = $stmt->fetch();

        if ($list) {
            $list['workflow_states'] = json_decode($list['workflow_states'] ?? '[]', true);
            $list['settings'] = json_decode($list['settings'] ?? '{}', true);
            $list['owner_name'] = trim($list['first_name'] . ' ' . $list['last_name']);
            $list['stats'] = [
                'total' => (int) $list['task_count'],
                'completed' => (int) $list['completed_count'],
                'overdue' => (int) $list['overdue_count'],
                'completion_rate' => $list['task_count'] > 0
                    ? round(($list['completed_count'] / $list['task_count']) * 100, 1)
                    : 0
            ];
        }

        return $list ?: null;
    }

    /**
     * Lista task lists accessibili all'utente
     */
    public function getTaskLists(array $filters = []): array {
        $sql = "
            SELECT tl.*, u.first_name, u.last_name,
                   COUNT(DISTINCT t.id) as task_count,
                   COUNT(DISTINCT CASE WHEN t.status = 'done' THEN t.id END) as completed_count
            FROM task_lists tl
            LEFT JOIN users u ON tl.owner_id = u.id
            LEFT JOIN tasks t ON tl.id = t.task_list_id AND t.deleted_at IS NULL
            WHERE tl.tenant_id = :tenant_id
            AND tl.deleted_at IS NULL
        ";

        $params = ['tenant_id' => $this->tenantId];

        // Filtro per proprietario
        if (!empty($filters['owner_id'])) {
            $sql .= " AND tl.owner_id = :owner_id";
            $params['owner_id'] = $filters['owner_id'];
        }

        // Filtro per tipo board
        if (!empty($filters['board_type'])) {
            $sql .= " AND tl.board_type = :board_type";
            $params['board_type'] = $filters['board_type'];
        }

        $sql .= " GROUP BY tl.id ORDER BY tl.position ASC, tl.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $lists = $stmt->fetchAll();

        foreach ($lists as &$list) {
            $list['workflow_states'] = json_decode($list['workflow_states'] ?? '[]', true);
            $list['settings'] = json_decode($list['settings'] ?? '{}', true);
            $list['owner_name'] = trim(($list['first_name'] ?? '') . ' ' . ($list['last_name'] ?? ''));
            $list['stats'] = [
                'total' => (int) $list['task_count'],
                'completed' => (int) $list['completed_count']
            ];
        }

        return $lists;
    }

    /**
     * Crea un nuovo task
     */
    public function createTask(array $data): array {
        try {
            $this->db->beginTransaction();

            // Verifica permessi sulla lista
            if (!$this->canAddTask($data['task_list_id'])) {
                throw new Exception("Permessi insufficienti per aggiungere task");
            }

            $stmt = $this->db->prepare("
                INSERT INTO tasks (
                    tenant_id, task_list_id, parent_task_id, title, description,
                    status, priority, due_date, start_date, estimated_hours,
                    actual_hours, progress_percentage, tags, color,
                    is_recurring, recurrence_pattern, position, settings, created_by
                ) VALUES (
                    :tenant_id, :task_list_id, :parent_task_id, :title, :description,
                    :status, :priority, :due_date, :start_date, :estimated_hours,
                    :actual_hours, :progress_percentage, :tags, :color,
                    :is_recurring, :recurrence_pattern, :position, :settings, :created_by
                )
            ");

            // Calcola posizione nella lista
            $position = $this->getNextPosition('tasks', $data['task_list_id']);

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'task_list_id' => $data['task_list_id'],
                'parent_task_id' => $data['parent_task_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'priority' => $data['priority'] ?? 'medium',
                'due_date' => $data['due_date'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'estimated_hours' => $data['estimated_hours'] ?? null,
                'actual_hours' => 0,
                'progress_percentage' => 0,
                'tags' => json_encode($data['tags'] ?? []),
                'color' => $data['color'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
                'position' => $data['position'] ?? $position,
                'settings' => json_encode($data['settings'] ?? []),
                'created_by' => $this->userId
            ]);

            $taskId = (int) $this->db->lastInsertId();

            // Aggiungi assegnazioni
            if (!empty($data['assignees'])) {
                $this->assignTask($taskId, $data['assignees']);
            }

            // Aggiungi allegati se presenti
            if (!empty($data['attachments'])) {
                $this->addTaskAttachments($taskId, $data['attachments']);
            }

            $this->db->commit();

            return $this->getTask($taskId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Errore creazione task: " . $e->getMessage());
        }
    }

    /**
     * Ottiene un task specifico
     */
    public function getTask(int $taskId): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, tl.name as list_name, tl.color as list_color,
                   u.first_name, u.last_name, u.email as creator_email,
                   pt.title as parent_task_title
            FROM tasks t
            LEFT JOIN task_lists tl ON t.task_list_id = tl.id
            LEFT JOIN users u ON t.created_by = u.id
            LEFT JOIN tasks pt ON t.parent_task_id = pt.id
            WHERE t.id = :task_id
            AND t.tenant_id = :tenant_id
            AND t.deleted_at IS NULL
        ");

        $stmt->execute([
            'task_id' => $taskId,
            'tenant_id' => $this->tenantId
        ]);

        $task = $stmt->fetch();

        if ($task) {
            $task['tags'] = json_decode($task['tags'] ?? '[]', true);
            $task['settings'] = json_decode($task['settings'] ?? '{}', true);
            $task['creator_name'] = trim($task['first_name'] . ' ' . $task['last_name']);

            // Carica assegnatari
            $task['assignees'] = $this->getTaskAssignees($taskId);

            // Carica sottoattività
            $task['subtasks'] = $this->getSubtasks($taskId);

            // Carica commenti recenti
            $task['recent_comments'] = $this->getTaskComments($taskId, 5);

            // Carica time logs
            $task['time_logs'] = $this->getTaskTimeLogs($taskId);

            // Carica allegati
            $task['attachments'] = $this->getTaskAttachments($taskId);
        }

        return $task ?: null;
    }

    /**
     * Lista tasks con filtri
     */
    public function getTasks(array $filters = []): array {
        $sql = "
            SELECT t.*, tl.name as list_name, tl.color as list_color,
                   u.first_name, u.last_name,
                   GROUP_CONCAT(DISTINCT ta.user_id) as assignee_ids
            FROM tasks t
            LEFT JOIN task_lists tl ON t.task_list_id = tl.id
            LEFT JOIN users u ON t.created_by = u.id
            LEFT JOIN task_assignments ta ON t.id = ta.task_id
            WHERE t.tenant_id = :tenant_id
            AND t.deleted_at IS NULL
        ";

        $params = ['tenant_id' => $this->tenantId];

        // Filtro per lista
        if (!empty($filters['task_list_id'])) {
            $sql .= " AND t.task_list_id = :task_list_id";
            $params['task_list_id'] = $filters['task_list_id'];
        }

        // Filtro per stato
        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $placeholders = [];
                foreach ($filters['status'] as $i => $status) {
                    $key = 'status_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $status;
                }
                $sql .= " AND t.status IN (" . implode(',', $placeholders) . ")";
            } else {
                $sql .= " AND t.status = :status";
                $params['status'] = $filters['status'];
            }
        }

        // Filtro per assegnatario
        if (!empty($filters['assignee_id'])) {
            $sql .= " AND t.id IN (
                SELECT task_id FROM task_assignments
                WHERE user_id = :assignee_id
            )";
            $params['assignee_id'] = $filters['assignee_id'];
        }

        // Filtro per priorità
        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params['priority'] = $filters['priority'];
        }

        // Filtro per date
        if (!empty($filters['due_date_from'])) {
            $sql .= " AND t.due_date >= :due_date_from";
            $params['due_date_from'] = $filters['due_date_from'];
        }

        if (!empty($filters['due_date_to'])) {
            $sql .= " AND t.due_date <= :due_date_to";
            $params['due_date_to'] = $filters['due_date_to'];
        }

        // Filtro per task in ritardo
        if (!empty($filters['overdue'])) {
            $sql .= " AND t.due_date < NOW() AND t.status != 'done'";
        }

        // Filtro per ricerca testo
        if (!empty($filters['search'])) {
            $sql .= " AND (t.title LIKE :search OR t.description LIKE :search_desc)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search_desc'] = '%' . $filters['search'] . '%';
        }

        // Solo task root (non sottoattività)
        if (!empty($filters['root_only'])) {
            $sql .= " AND t.parent_task_id IS NULL";
        }

        $sql .= " GROUP BY t.id";

        // Ordinamento
        $orderBy = " ORDER BY ";
        switch ($filters['sort_by'] ?? 'position') {
            case 'due_date':
                $orderBy .= "t.due_date " . ($filters['sort_order'] ?? 'ASC');
                break;
            case 'priority':
                $orderBy .= "FIELD(t.priority, 'urgent', 'high', 'medium', 'low') " .
                           ($filters['sort_order'] ?? 'ASC');
                break;
            case 'created':
                $orderBy .= "t.created_at " . ($filters['sort_order'] ?? 'DESC');
                break;
            default:
                $orderBy .= "t.position ASC, t.created_at DESC";
        }
        $sql .= $orderBy;

        // Paginazione
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET " . (int) $filters['offset'];
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $tasks = $stmt->fetchAll();

        foreach ($tasks as &$task) {
            $task['tags'] = json_decode($task['tags'] ?? '[]', true);
            $task['settings'] = json_decode($task['settings'] ?? '{}', true);
            $task['creator_name'] = trim(($task['first_name'] ?? '') . ' ' . ($task['last_name'] ?? ''));

            // Carica assegnatari se richiesto
            if (!empty($filters['include_assignees'])) {
                $task['assignees'] = $this->getTaskAssignees($task['id']);
            }

            // Conta sottoattività
            if (!empty($filters['include_subtask_count'])) {
                $task['subtask_count'] = $this->countSubtasks($task['id']);
            }
        }

        return $tasks;
    }

    /**
     * Aggiorna un task
     */
    public function updateTask(int $taskId, array $data): array {
        if (!$this->canEditTask($taskId)) {
            throw new Exception("Permessi insufficienti per modificare il task");
        }

        $fields = [];
        $params = ['id' => $taskId, 'tenant_id' => $this->tenantId];

        $allowedFields = [
            'title', 'description', 'status', 'priority',
            'due_date', 'start_date', 'estimated_hours',
            'progress_percentage', 'color'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        // Campi JSON
        if (isset($data['tags'])) {
            $fields[] = "tags = :tags";
            $params['tags'] = json_encode($data['tags']);
        }

        if (isset($data['settings'])) {
            $fields[] = "settings = :settings";
            $params['settings'] = json_encode($data['settings']);
        }

        // Aggiorna tempo effettivo se stato cambia a completato
        if (isset($data['status']) && $data['status'] === 'done') {
            $fields[] = "completed_at = NOW()";
            $fields[] = "completed_by = :completed_by";
            $params['completed_by'] = $this->userId;
        }

        if (!empty($fields)) {
            $fields[] = "updated_by = :updated_by";
            $params['updated_by'] = $this->userId;

            $sql = "UPDATE tasks SET " . implode(', ', $fields) .
                   " WHERE id = :id AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        // Aggiorna assegnazioni se fornite
        if (isset($data['assignees'])) {
            $this->updateTaskAssignees($taskId, $data['assignees']);
        }

        // Registra attività nel log
        $this->logTaskActivity($taskId, 'updated', $data);

        return $this->getTask($taskId);
    }

    /**
     * Sposta un task in un'altra lista o posizione
     */
    public function moveTask(int $taskId, array $data): bool {
        if (!$this->canEditTask($taskId)) {
            throw new Exception("Permessi insufficienti per spostare il task");
        }

        $updates = [];
        $params = ['id' => $taskId, 'tenant_id' => $this->tenantId];

        // Cambio lista
        if (isset($data['task_list_id'])) {
            if (!$this->canAddTask($data['task_list_id'])) {
                throw new Exception("Permessi insufficienti sulla lista di destinazione");
            }
            $updates[] = "task_list_id = :task_list_id";
            $params['task_list_id'] = $data['task_list_id'];
        }

        // Cambio posizione
        if (isset($data['position'])) {
            $updates[] = "position = :position";
            $params['position'] = $data['position'];

            // Riordina altri task
            $this->reorderTasks($data['task_list_id'] ?? null, $taskId, $data['position']);
        }

        // Cambio stato (per drag&drop kanban)
        if (isset($data['status'])) {
            $updates[] = "status = :status";
            $params['status'] = $data['status'];
        }

        if (empty($updates)) {
            return true;
        }

        $sql = "UPDATE tasks SET " . implode(', ', $updates) .
               " WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina un task (soft delete)
     */
    public function deleteTask(int $taskId): bool {
        if (!$this->canEditTask($taskId)) {
            throw new Exception("Permessi insufficienti per eliminare il task");
        }

        $stmt = $this->db->prepare("
            UPDATE tasks
            SET deleted_at = NOW(), deleted_by = :deleted_by
            WHERE id = :id AND tenant_id = :tenant_id
        ");

        return $stmt->execute([
            'id' => $taskId,
            'tenant_id' => $this->tenantId,
            'deleted_by' => $this->userId
        ]);
    }

    /**
     * Assegna utenti a un task
     */
    public function assignTask(int $taskId, array $userIds): bool {
        $stmt = $this->db->prepare("
            INSERT INTO task_assignments (tenant_id, task_id, user_id, assigned_by)
            VALUES (:tenant_id, :task_id, :user_id, :assigned_by)
            ON DUPLICATE KEY UPDATE assigned_at = NOW()
        ");

        foreach ($userIds as $userId) {
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'task_id' => $taskId,
                'user_id' => $userId,
                'assigned_by' => $this->userId
            ]);
        }

        return true;
    }

    /**
     * Aggiunge un commento a un task
     */
    public function addComment(int $taskId, string $comment, array $mentions = []): array {
        $stmt = $this->db->prepare("
            INSERT INTO task_comments (
                tenant_id, task_id, user_id, comment, mentions
            ) VALUES (
                :tenant_id, :task_id, :user_id, :comment, :mentions
            )
        ");

        $stmt->execute([
            'tenant_id' => $this->tenantId,
            'task_id' => $taskId,
            'user_id' => $this->userId,
            'comment' => $comment,
            'mentions' => json_encode($mentions)
        ]);

        $commentId = (int) $this->db->lastInsertId();

        // Notifica utenti menzionati
        foreach ($mentions as $userId) {
            $this->notifyUser($userId, 'mention', $taskId, $commentId);
        }

        return $this->getComment($commentId);
    }

    /**
     * Registra tempo lavorato su un task
     */
    public function logTime(int $taskId, array $data): array {
        $stmt = $this->db->prepare("
            INSERT INTO task_time_logs (
                tenant_id, task_id, user_id, started_at, ended_at,
                duration_minutes, description, is_billable
            ) VALUES (
                :tenant_id, :task_id, :user_id, :started_at, :ended_at,
                :duration_minutes, :description, :is_billable
            )
        ");

        $duration = $data['duration_minutes'] ?? null;
        if (!$duration && isset($data['started_at']) && isset($data['ended_at'])) {
            $start = new DateTime($data['started_at']);
            $end = new DateTime($data['ended_at']);
            $duration = ($end->getTimestamp() - $start->getTimestamp()) / 60;
        }

        $stmt->execute([
            'tenant_id' => $this->tenantId,
            'task_id' => $taskId,
            'user_id' => $this->userId,
            'started_at' => $data['started_at'] ?? date('Y-m-d H:i:s'),
            'ended_at' => $data['ended_at'] ?? null,
            'duration_minutes' => $duration,
            'description' => $data['description'] ?? null,
            'is_billable' => $data['is_billable'] ?? false
        ]);

        // Aggiorna ore effettive del task
        $this->updateTaskActualHours($taskId);

        return ['id' => (int) $this->db->lastInsertId(), 'duration_minutes' => $duration];
    }

    // Metodi privati di supporto

    private function canAddTask(int $listId): bool {
        // Per ora tutti possono aggiungere task alle liste del proprio tenant
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM task_lists
            WHERE id = :list_id AND tenant_id = :tenant_id AND deleted_at IS NULL
        ");
        $stmt->execute(['list_id' => $listId, 'tenant_id' => $this->tenantId]);
        return $stmt->fetchColumn() > 0;
    }

    private function canEditTask(int $taskId): bool {
        $stmt = $this->db->prepare("
            SELECT created_by FROM tasks
            WHERE id = :task_id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['task_id' => $taskId, 'tenant_id' => $this->tenantId]);
        $task = $stmt->fetch();

        // Creatore o assegnatario può modificare
        if ($task && $task['created_by'] == $this->userId) {
            return true;
        }

        // Verifica se è assegnato
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM task_assignments
            WHERE task_id = :task_id AND user_id = :user_id
        ");
        $stmt->execute(['task_id' => $taskId, 'user_id' => $this->userId]);
        return $stmt->fetchColumn() > 0;
    }

    private function getTaskAssignees(int $taskId): array {
        $stmt = $this->db->prepare("
            SELECT ta.*, u.first_name, u.last_name, u.email, u.avatar_url
            FROM task_assignments ta
            JOIN users u ON ta.user_id = u.id
            WHERE ta.task_id = :task_id AND ta.tenant_id = :tenant_id
        ");
        $stmt->execute(['task_id' => $taskId, 'tenant_id' => $this->tenantId]);
        return $stmt->fetchAll();
    }

    private function updateTaskAssignees(int $taskId, array $userIds): void {
        // Rimuovi assegnazioni esistenti
        $stmt = $this->db->prepare("
            DELETE FROM task_assignments
            WHERE task_id = :task_id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['task_id' => $taskId, 'tenant_id' => $this->tenantId]);

        // Aggiungi nuove assegnazioni
        if (!empty($userIds)) {
            $this->assignTask($taskId, $userIds);
        }
    }

    private function getSubtasks(int $parentTaskId): array {
        $stmt = $this->db->prepare("
            SELECT id, title, status, priority, due_date, progress_percentage
            FROM tasks
            WHERE parent_task_id = :parent_id
            AND tenant_id = :tenant_id
            AND deleted_at IS NULL
            ORDER BY position ASC
        ");
        $stmt->execute(['parent_id' => $parentTaskId, 'tenant_id' => $this->tenantId]);
        return $stmt->fetchAll();
    }

    private function countSubtasks(int $parentTaskId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM tasks
            WHERE parent_task_id = :parent_id
            AND tenant_id = :tenant_id
            AND deleted_at IS NULL
        ");
        $stmt->execute(['parent_id' => $parentTaskId, 'tenant_id' => $this->tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private function getTaskComments(int $taskId, int $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT tc.*, u.first_name, u.last_name, u.email, u.avatar_url
            FROM task_comments tc
            JOIN users u ON tc.user_id = u.id
            WHERE tc.task_id = :task_id AND tc.tenant_id = :tenant_id
            ORDER BY tc.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':task_id', $taskId, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $this->tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $comments = $stmt->fetchAll();
        foreach ($comments as &$comment) {
            $comment['mentions'] = json_decode($comment['mentions'] ?? '[]', true);
            $comment['user_name'] = trim($comment['first_name'] . ' ' . $comment['last_name']);
        }
        return $comments;
    }

    private function getComment(int $commentId): array {
        $stmt = $this->db->prepare("
            SELECT tc.*, u.first_name, u.last_name, u.email
            FROM task_comments tc
            JOIN users u ON tc.user_id = u.id
            WHERE tc.id = :id
        ");
        $stmt->execute(['id' => $commentId]);
        $comment = $stmt->fetch();
        $comment['mentions'] = json_decode($comment['mentions'] ?? '[]', true);
        $comment['user_name'] = trim($comment['first_name'] . ' ' . $comment['last_name']);
        return $comment;
    }

    private function getTaskTimeLogs(int $taskId): array {
        $stmt = $this->db->prepare("
            SELECT ttl.*, u.first_name, u.last_name
            FROM task_time_logs ttl
            JOIN users u ON ttl.user_id = u.id
            WHERE ttl.task_id = :task_id AND ttl.tenant_id = :tenant_id
            ORDER BY ttl.started_at DESC
        ");
        $stmt->execute(['task_id' => $taskId, 'tenant_id' => $this->tenantId]);
        return $stmt->fetchAll();
    }

    private function getTaskAttachments(int $taskId): array {
        $stmt = $this->db->prepare("
            SELECT ta.*, f.name, f.mime_type, f.size
            FROM task_attachments ta
            JOIN files f ON ta.file_id = f.id
            WHERE ta.task_id = :task_id AND ta.tenant_id = :tenant_id
        ");
        $stmt->execute(['task_id' => $taskId, 'tenant_id' => $this->tenantId]);
        return $stmt->fetchAll();
    }

    private function addTaskAttachments(int $taskId, array $fileIds): void {
        $stmt = $this->db->prepare("
            INSERT INTO task_attachments (tenant_id, task_id, file_id, uploaded_by)
            VALUES (:tenant_id, :task_id, :file_id, :uploaded_by)
        ");

        foreach ($fileIds as $fileId) {
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'task_id' => $taskId,
                'file_id' => $fileId,
                'uploaded_by' => $this->userId
            ]);
        }
    }

    private function updateTaskActualHours(int $taskId): void {
        $stmt = $this->db->prepare("
            UPDATE tasks t
            SET t.actual_hours = (
                SELECT SUM(duration_minutes) / 60
                FROM task_time_logs
                WHERE task_id = :task_id AND tenant_id = :tenant_id
            )
            WHERE t.id = :update_task_id AND t.tenant_id = :update_tenant_id
        ");

        $stmt->execute([
            'task_id' => $taskId,
            'tenant_id' => $this->tenantId,
            'update_task_id' => $taskId,
            'update_tenant_id' => $this->tenantId
        ]);
    }

    private function getNextPosition(string $table, int $listId = null): int {
        if ($table === 'task_lists') {
            $stmt = $this->db->prepare("
                SELECT COALESCE(MAX(position), 0) + 1
                FROM task_lists
                WHERE tenant_id = :tenant_id
            ");
            $stmt->execute(['tenant_id' => $this->tenantId]);
        } else {
            $stmt = $this->db->prepare("
                SELECT COALESCE(MAX(position), 0) + 1
                FROM tasks
                WHERE task_list_id = :list_id AND tenant_id = :tenant_id
            ");
            $stmt->execute(['list_id' => $listId, 'tenant_id' => $this->tenantId]);
        }
        return (int) $stmt->fetchColumn();
    }

    private function reorderTasks(int $listId = null, int $taskId, int $newPosition): void {
        // Implementazione semplificata del riordinamento
        // In produzione, implementare algoritmo più sofisticato
        $stmt = $this->db->prepare("
            UPDATE tasks
            SET position = position + 1
            WHERE task_list_id = :list_id
            AND tenant_id = :tenant_id
            AND position >= :position
            AND id != :task_id
        ");

        $stmt->execute([
            'list_id' => $listId,
            'tenant_id' => $this->tenantId,
            'position' => $newPosition,
            'task_id' => $taskId
        ]);
    }

    private function logTaskActivity(int $taskId, string $action, array $changes): void {
        // Qui si potrebbe implementare un sistema di activity log
        // per tracciare tutte le modifiche ai task
    }

    private function notifyUser(int $userId, string $type, int $taskId, int $relatedId = null): void {
        // Implementazione notifiche (da integrare con sistema notifiche)
    }
}