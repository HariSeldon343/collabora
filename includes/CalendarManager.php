<?php declare(strict_types=1);

/**
 * Gestione calendari ed eventi
 * Supporta multi-tenancy, eventi ricorrenti e condivisione
 */

namespace Collabora\Calendar;

use PDO;
use Exception;
use DateTime;
use DateTimeZone;
use DateInterval;
use DatePeriod;

class CalendarManager {
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
     * Crea un nuovo calendario
     */
    public function createCalendar(array $data): array {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                INSERT INTO calendars (
                    tenant_id, user_id, name, description, color,
                    timezone, is_default, is_public, settings
                ) VALUES (
                    :tenant_id, :user_id, :name, :description, :color,
                    :timezone, :is_default, :is_public, :settings
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? '#4F46E5',
                'timezone' => $data['timezone'] ?? 'Europe/Rome',
                'is_default' => $data['is_default'] ?? false,
                'is_public' => $data['is_public'] ?? false,
                'settings' => json_encode($data['settings'] ?? [])
            ]);

            $calendarId = (int) $this->db->lastInsertId();

            // Se è il calendario di default, aggiorna gli altri
            if ($data['is_default'] ?? false) {
                $stmt = $this->db->prepare("
                    UPDATE calendars
                    SET is_default = 0
                    WHERE tenant_id = :tenant_id
                    AND user_id = :user_id
                    AND id != :calendar_id
                ");
                $stmt->execute([
                    'tenant_id' => $this->tenantId,
                    'user_id' => $this->userId,
                    'calendar_id' => $calendarId
                ]);
            }

            $this->db->commit();

            return $this->getCalendar($calendarId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Errore creazione calendario: " . $e->getMessage());
        }
    }

    /**
     * Ottiene un calendario specifico
     */
    public function getCalendar(int $calendarId): ?array {
        $stmt = $this->db->prepare("
            SELECT c.*, u.first_name, u.last_name, u.email as owner_email,
                   COUNT(DISTINCT e.id) as event_count,
                   COUNT(DISTINCT cs.id) as share_count
            FROM calendars c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN events e ON c.id = e.calendar_id AND e.deleted_at IS NULL
            LEFT JOIN calendar_shares cs ON c.id = cs.calendar_id
            WHERE c.id = :calendar_id
            AND c.tenant_id = :tenant_id
            AND c.deleted_at IS NULL
            GROUP BY c.id
        ");

        $stmt->execute([
            'calendar_id' => $calendarId,
            'tenant_id' => $this->tenantId
        ]);

        $calendar = $stmt->fetch();

        if ($calendar) {
            $calendar['settings'] = json_decode($calendar['settings'] ?? '{}', true);
            $calendar['owner_name'] = trim($calendar['first_name'] . ' ' . $calendar['last_name']);
        }

        return $calendar ?: null;
    }

    /**
     * Lista calendari accessibili all'utente
     */
    public function getCalendars(array $filters = []): array {
        $sql = "
            SELECT c.*, u.first_name, u.last_name, u.email as owner_email,
                   COUNT(DISTINCT e.id) as event_count,
                   CASE
                       WHEN c.user_id = :user_id THEN 'owner'
                       WHEN cs.user_id IS NOT NULL THEN cs.permission
                       WHEN c.is_public = 1 THEN 'view'
                       ELSE NULL
                   END as user_permission
            FROM calendars c
            LEFT JOIN users u ON c.user_id = u.id
            LEFT JOIN events e ON c.id = e.calendar_id AND e.deleted_at IS NULL
            LEFT JOIN calendar_shares cs ON c.id = cs.calendar_id AND cs.user_id = :user_id
            WHERE c.tenant_id = :tenant_id
            AND c.deleted_at IS NULL
            AND (
                c.user_id = :user_id OR
                c.is_public = 1 OR
                cs.user_id IS NOT NULL
            )
        ";

        $params = [
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId
        ];

        // Applica filtri
        if (!empty($filters['owned_only'])) {
            $sql .= " AND c.user_id = :owner_id";
            $params['owner_id'] = $this->userId;
        }

        if (!empty($filters['shared_with_me'])) {
            $sql .= " AND cs.user_id IS NOT NULL";
        }

        $sql .= " GROUP BY c.id ORDER BY c.is_default DESC, c.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $calendars = $stmt->fetchAll();

        foreach ($calendars as &$calendar) {
            $calendar['settings'] = json_decode($calendar['settings'] ?? '{}', true);
            $calendar['owner_name'] = trim($calendar['first_name'] . ' ' . $calendar['last_name']);
        }

        return $calendars;
    }

    /**
     * Aggiorna un calendario
     */
    public function updateCalendar(int $calendarId, array $data): array {
        // Verifica permessi
        if (!$this->canEditCalendar($calendarId)) {
            throw new Exception("Permessi insufficienti per modificare il calendario");
        }

        $fields = [];
        $params = ['id' => $calendarId, 'tenant_id' => $this->tenantId];

        foreach (['name', 'description', 'color', 'timezone', 'is_public'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (isset($data['settings'])) {
            $fields[] = "settings = :settings";
            $params['settings'] = json_encode($data['settings']);
        }

        if (empty($fields)) {
            return $this->getCalendar($calendarId);
        }

        $sql = "UPDATE calendars SET " . implode(', ', $fields) .
               " WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getCalendar($calendarId);
    }

    /**
     * Elimina un calendario (soft delete)
     */
    public function deleteCalendar(int $calendarId): bool {
        if (!$this->canEditCalendar($calendarId)) {
            throw new Exception("Permessi insufficienti per eliminare il calendario");
        }

        $stmt = $this->db->prepare("
            UPDATE calendars
            SET deleted_at = NOW(), deleted_by = :deleted_by
            WHERE id = :id AND tenant_id = :tenant_id
        ");

        return $stmt->execute([
            'id' => $calendarId,
            'tenant_id' => $this->tenantId,
            'deleted_by' => $this->userId
        ]);
    }

    /**
     * Crea un nuovo evento
     */
    public function createEvent(array $data): array {
        try {
            $this->db->beginTransaction();

            // Verifica permessi sul calendario
            if (!$this->canAddEvent($data['calendar_id'])) {
                throw new Exception("Permessi insufficienti per aggiungere eventi");
            }

            // Genera UID univoco per CalDAV
            $uid = $this->generateUID();

            $stmt = $this->db->prepare("
                INSERT INTO events (
                    tenant_id, calendar_id, uid, title, description,
                    location, start_datetime, end_datetime, all_day,
                    recurrence_rule, recurrence_end, timezone, color,
                    status, visibility, reminders, metadata, created_by
                ) VALUES (
                    :tenant_id, :calendar_id, :uid, :title, :description,
                    :location, :start_datetime, :end_datetime, :all_day,
                    :recurrence_rule, :recurrence_end, :timezone, :color,
                    :status, :visibility, :reminders, :metadata, :created_by
                )
            ");

            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'calendar_id' => $data['calendar_id'],
                'uid' => $uid,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'location' => $data['location'] ?? null,
                'start_datetime' => $data['start_datetime'],
                'end_datetime' => $data['end_datetime'],
                'all_day' => $data['all_day'] ?? false,
                'recurrence_rule' => $data['recurrence_rule'] ?? null,
                'recurrence_end' => $data['recurrence_end'] ?? null,
                'timezone' => $data['timezone'] ?? 'Europe/Rome',
                'color' => $data['color'] ?? null,
                'status' => $data['status'] ?? 'confirmed',
                'visibility' => $data['visibility'] ?? 'private',
                'reminders' => json_encode($data['reminders'] ?? []),
                'metadata' => json_encode($data['metadata'] ?? []),
                'created_by' => $this->userId
            ]);

            $eventId = (int) $this->db->lastInsertId();

            // Aggiungi partecipanti
            if (!empty($data['participants'])) {
                $this->addEventParticipants($eventId, $data['participants']);
            }

            // Aggiungi promemoria
            if (!empty($data['reminders'])) {
                $this->addEventReminders($eventId, $data['reminders']);
            }

            $this->db->commit();

            return $this->getEvent($eventId);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw new Exception("Errore creazione evento: " . $e->getMessage());
        }
    }

    /**
     * Ottiene un evento specifico
     */
    public function getEvent(int $eventId): ?array {
        $stmt = $this->db->prepare("
            SELECT e.*, c.name as calendar_name, c.color as calendar_color,
                   u.first_name, u.last_name, u.email as creator_email
            FROM events e
            LEFT JOIN calendars c ON e.calendar_id = c.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.id = :event_id
            AND e.tenant_id = :tenant_id
            AND e.deleted_at IS NULL
        ");

        $stmt->execute([
            'event_id' => $eventId,
            'tenant_id' => $this->tenantId
        ]);

        $event = $stmt->fetch();

        if ($event) {
            $event['reminders'] = json_decode($event['reminders'] ?? '[]', true);
            $event['metadata'] = json_decode($event['metadata'] ?? '{}', true);
            $event['creator_name'] = trim($event['first_name'] . ' ' . $event['last_name']);

            // Carica partecipanti
            $event['participants'] = $this->getEventParticipants($eventId);

            // Carica allegati se presenti
            $event['attachments'] = $this->getEventAttachments($eventId);
        }

        return $event ?: null;
    }

    /**
     * Lista eventi con filtri
     */
    public function getEvents(array $filters = []): array {
        $sql = "
            SELECT e.*, c.name as calendar_name, c.color as calendar_color,
                   u.first_name, u.last_name
            FROM events e
            LEFT JOIN calendars c ON e.calendar_id = c.id
            LEFT JOIN users u ON e.created_by = u.id
            WHERE e.tenant_id = :tenant_id
            AND e.deleted_at IS NULL
        ";

        $params = ['tenant_id' => $this->tenantId];

        // Filtra per calendari accessibili
        if (!empty($filters['calendar_id'])) {
            $sql .= " AND e.calendar_id = :calendar_id";
            $params['calendar_id'] = $filters['calendar_id'];
        } else {
            $sql .= " AND e.calendar_id IN (
                SELECT id FROM calendars
                WHERE tenant_id = :tenant_check
                AND (user_id = :user_id OR is_public = 1 OR id IN (
                    SELECT calendar_id FROM calendar_shares WHERE user_id = :share_user_id
                ))
            )";
            $params['tenant_check'] = $this->tenantId;
            $params['user_id'] = $this->userId;
            $params['share_user_id'] = $this->userId;
        }

        // Filtro per range di date
        if (!empty($filters['start_date'])) {
            $sql .= " AND e.end_datetime >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND e.start_datetime <= :end_date";
            $params['end_date'] = $filters['end_date'];
        }

        // Filtro per stato
        if (!empty($filters['status'])) {
            $sql .= " AND e.status = :status";
            $params['status'] = $filters['status'];
        }

        // Filtro ricerca testo
        if (!empty($filters['search'])) {
            $sql .= " AND (e.title LIKE :search OR e.description LIKE :search_desc)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search_desc'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY e.start_datetime ASC";

        // Limite risultati
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET :offset";
            }
        }

        $stmt = $this->db->prepare($sql);

        // Bind dei parametri con tipo corretto
        foreach ($params as $key => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }

        $stmt->execute();
        $events = $stmt->fetchAll();

        // Processa eventi ricorrenti se necessario
        if (!empty($filters['expand_recurring'])) {
            $events = $this->expandRecurringEvents($events, $filters);
        }

        foreach ($events as &$event) {
            $event['reminders'] = json_decode($event['reminders'] ?? '[]', true);
            $event['metadata'] = json_decode($event['metadata'] ?? '{}', true);
            $event['creator_name'] = trim(($event['first_name'] ?? '') . ' ' . ($event['last_name'] ?? ''));
        }

        return $events;
    }

    /**
     * Aggiorna un evento
     */
    public function updateEvent(int $eventId, array $data): array {
        if (!$this->canEditEvent($eventId)) {
            throw new Exception("Permessi insufficienti per modificare l'evento");
        }

        $fields = [];
        $params = ['id' => $eventId, 'tenant_id' => $this->tenantId];

        $allowedFields = [
            'title', 'description', 'location', 'start_datetime', 'end_datetime',
            'all_day', 'recurrence_rule', 'recurrence_end', 'timezone', 'color',
            'status', 'visibility'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (isset($data['reminders'])) {
            $fields[] = "reminders = :reminders";
            $params['reminders'] = json_encode($data['reminders']);
        }

        if (isset($data['metadata'])) {
            $fields[] = "metadata = :metadata";
            $params['metadata'] = json_encode($data['metadata']);
        }

        if (!empty($fields)) {
            $fields[] = "updated_by = :updated_by";
            $params['updated_by'] = $this->userId;

            $sql = "UPDATE events SET " . implode(', ', $fields) .
                   " WHERE id = :id AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        // Aggiorna partecipanti se forniti
        if (isset($data['participants'])) {
            $this->updateEventParticipants($eventId, $data['participants']);
        }

        return $this->getEvent($eventId);
    }

    /**
     * Elimina un evento (soft delete)
     */
    public function deleteEvent(int $eventId): bool {
        if (!$this->canEditEvent($eventId)) {
            throw new Exception("Permessi insufficienti per eliminare l'evento");
        }

        $stmt = $this->db->prepare("
            UPDATE events
            SET deleted_at = NOW(), deleted_by = :deleted_by
            WHERE id = :id AND tenant_id = :tenant_id
        ");

        return $stmt->execute([
            'id' => $eventId,
            'tenant_id' => $this->tenantId,
            'deleted_by' => $this->userId
        ]);
    }

    /**
     * Condivide un calendario con un altro utente
     */
    public function shareCalendar(int $calendarId, int $shareWithUserId, string $permission = 'view'): bool {
        if (!$this->canEditCalendar($calendarId)) {
            throw new Exception("Permessi insufficienti per condividere il calendario");
        }

        $stmt = $this->db->prepare("
            INSERT INTO calendar_shares (tenant_id, calendar_id, user_id, permission, shared_by)
            VALUES (:tenant_id, :calendar_id, :user_id, :permission, :shared_by)
            ON DUPLICATE KEY UPDATE permission = VALUES(permission)
        ");

        return $stmt->execute([
            'tenant_id' => $this->tenantId,
            'calendar_id' => $calendarId,
            'user_id' => $shareWithUserId,
            'permission' => $permission,
            'shared_by' => $this->userId
        ]);
    }

    /**
     * Aggiorna RSVP per un partecipante
     */
    public function updateRSVP(int $eventId, string $response, string $comment = null): bool {
        $stmt = $this->db->prepare("
            UPDATE event_participants
            SET response_status = :status, response_comment = :comment, responded_at = NOW()
            WHERE event_id = :event_id AND user_id = :user_id
        ");

        return $stmt->execute([
            'status' => $response,
            'comment' => $comment,
            'event_id' => $eventId,
            'user_id' => $this->userId
        ]);
    }

    // Metodi privati di supporto

    private function canEditCalendar(int $calendarId): bool {
        $stmt = $this->db->prepare("
            SELECT user_id FROM calendars
            WHERE id = :id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['id' => $calendarId, 'tenant_id' => $this->tenantId]);
        $calendar = $stmt->fetch();

        return $calendar && $calendar['user_id'] == $this->userId;
    }

    private function canAddEvent(int $calendarId): bool {
        $stmt = $this->db->prepare("
            SELECT c.user_id, cs.permission
            FROM calendars c
            LEFT JOIN calendar_shares cs ON c.id = cs.calendar_id AND cs.user_id = :user_id
            WHERE c.id = :calendar_id AND c.tenant_id = :tenant_id
        ");

        $stmt->execute([
            'calendar_id' => $calendarId,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId
        ]);

        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        // Proprietario o ha permesso di modifica
        return $result['user_id'] == $this->userId ||
               in_array($result['permission'], ['edit', 'manage']);
    }

    private function canEditEvent(int $eventId): bool {
        $stmt = $this->db->prepare("
            SELECT e.created_by, c.user_id
            FROM events e
            JOIN calendars c ON e.calendar_id = c.id
            WHERE e.id = :event_id AND e.tenant_id = :tenant_id
        ");

        $stmt->execute(['event_id' => $eventId, 'tenant_id' => $this->tenantId]);
        $result = $stmt->fetch();

        if (!$result) {
            return false;
        }

        // Creatore evento o proprietario calendario
        return $result['created_by'] == $this->userId ||
               $result['user_id'] == $this->userId;
    }

    private function addEventParticipants(int $eventId, array $participants): void {
        $stmt = $this->db->prepare("
            INSERT INTO event_participants (
                tenant_id, event_id, user_id, email, name, role,
                is_required, response_status
            ) VALUES (
                :tenant_id, :event_id, :user_id, :email, :name, :role,
                :is_required, :response_status
            )
        ");

        foreach ($participants as $participant) {
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'event_id' => $eventId,
                'user_id' => $participant['user_id'] ?? null,
                'email' => $participant['email'],
                'name' => $participant['name'] ?? null,
                'role' => $participant['role'] ?? 'attendee',
                'is_required' => $participant['is_required'] ?? false,
                'response_status' => 'needs-action'
            ]);
        }
    }

    private function updateEventParticipants(int $eventId, array $participants): void {
        // Rimuovi partecipanti esistenti
        $stmt = $this->db->prepare("
            DELETE FROM event_participants
            WHERE event_id = :event_id AND tenant_id = :tenant_id
        ");
        $stmt->execute(['event_id' => $eventId, 'tenant_id' => $this->tenantId]);

        // Aggiungi nuovi partecipanti
        if (!empty($participants)) {
            $this->addEventParticipants($eventId, $participants);
        }
    }

    private function getEventParticipants(int $eventId): array {
        $stmt = $this->db->prepare("
            SELECT ep.*, u.first_name, u.last_name, u.email as user_email
            FROM event_participants ep
            LEFT JOIN users u ON ep.user_id = u.id
            WHERE ep.event_id = :event_id AND ep.tenant_id = :tenant_id
        ");

        $stmt->execute(['event_id' => $eventId, 'tenant_id' => $this->tenantId]);
        return $stmt->fetchAll();
    }

    private function addEventReminders(int $eventId, array $reminders): void {
        $stmt = $this->db->prepare("
            INSERT INTO event_reminders (
                tenant_id, event_id, type, minutes_before, method, settings
            ) VALUES (
                :tenant_id, :event_id, :type, :minutes_before, :method, :settings
            )
        ");

        foreach ($reminders as $reminder) {
            $stmt->execute([
                'tenant_id' => $this->tenantId,
                'event_id' => $eventId,
                'type' => $reminder['type'] ?? 'notification',
                'minutes_before' => $reminder['minutes_before'] ?? 15,
                'method' => $reminder['method'] ?? 'email',
                'settings' => json_encode($reminder['settings'] ?? [])
            ]);
        }
    }

    private function getEventAttachments(int $eventId): array {
        $stmt = $this->db->prepare("
            SELECT ea.*, f.name, f.mime_type, f.size
            FROM event_attachments ea
            JOIN files f ON ea.file_id = f.id
            WHERE ea.event_id = :event_id AND ea.tenant_id = :tenant_id
        ");

        $stmt->execute(['event_id' => $eventId, 'tenant_id' => $this->tenantId]);
        return $stmt->fetchAll();
    }

    private function expandRecurringEvents(array $events, array $filters): array {
        $expandedEvents = [];
        $startDate = new DateTime($filters['start_date'] ?? 'now');
        $endDate = new DateTime($filters['end_date'] ?? '+1 month');

        foreach ($events as $event) {
            if (empty($event['recurrence_rule'])) {
                $expandedEvents[] = $event;
                continue;
            }

            // Qui andrebbero implementate le regole di ricorrenza RFC 5545
            // Per ora aggiungiamo solo l'evento originale
            $expandedEvents[] = $event;

            // TODO: Implementare parser RRULE completo
            // Suggerimento: usare libreria come simshaun/recurr
        }

        return $expandedEvents;
    }

    private function generateUID(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        ) . '@nexiosolution.local';
    }
}