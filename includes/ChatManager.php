<?php
/**
 * ChatManager - Helper class for Chat & Communication operations
 * Part 4: Chat Module Implementation
 * Multi-tenant chat system with presence, reactions, and threading
 */

require_once __DIR__ . '/db.php';

class ChatManager {
    private $db;
    private $tenant_id;
    private $user_id;

    /**
     * Constructor
     *
     * @param int $tenant_id Current tenant ID
     * @param int|null $user_id Current user ID (optional)
     */
    public function __construct($tenant_id, $user_id = null) {
        $this->db = getDbConnection();
        $this->tenant_id = $tenant_id;
        $this->user_id = $user_id;
    }

    /**
     * Get channels for the current tenant
     *
     * @param array $filters Optional filters (type, archived, etc)
     * @return array List of channels with member counts
     */
    public function getChannels($filters = []) {
        $sql = "
            SELECT
                c.*,
                COUNT(DISTINCT cm.user_id) as member_count,
                MAX(m.created_at) as last_activity,
                u.name as creator_name,
                u.email as creator_email
            FROM chat_channels c
            LEFT JOIN chat_channel_members cm ON c.id = cm.channel_id
            LEFT JOIN chat_messages m ON c.id = m.channel_id AND m.deleted_at IS NULL
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.tenant_id = :tenant_id
        ";

        $params = ['tenant_id' => $this->tenant_id];

        if (isset($filters['type'])) {
            $sql .= " AND c.type = :type";
            $params['type'] = $filters['type'];
        }

        if (isset($filters['archived'])) {
            $sql .= " AND c.is_archived = :archived";
            $params['archived'] = $filters['archived'] ? 1 : 0;
        }

        if (isset($filters['user_id'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM chat_channel_members
                WHERE channel_id = c.id AND user_id = :user_id
            )";
            $params['user_id'] = $filters['user_id'];
        }

        $sql .= " GROUP BY c.id ORDER BY last_activity DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create a new channel
     *
     * @param array $data Channel data
     * @return array|false Created channel or false on failure
     */
    public function createChannel($data) {
        try {
            $this->db->beginTransaction();

            // Create the channel
            $sql = "INSERT INTO chat_channels
                    (tenant_id, type, name, description, created_by, metadata)
                    VALUES (:tenant_id, :type, :name, :description, :created_by, :metadata)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'tenant_id' => $this->tenant_id,
                'type' => $data['type'] ?? 'public',
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'created_by' => $this->user_id,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ]);

            $channel_id = $this->db->lastInsertId();

            // Add creator as owner
            $sql = "INSERT INTO chat_channel_members
                    (channel_id, user_id, role)
                    VALUES (:channel_id, :user_id, 'owner')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'channel_id' => $channel_id,
                'user_id' => $this->user_id
            ]);

            // Add additional members if provided
            if (!empty($data['members'])) {
                foreach ($data['members'] as $member_id) {
                    if ($member_id != $this->user_id) {
                        $this->addChannelMember($channel_id, $member_id, 'member');
                    }
                }
            }

            $this->db->commit();

            return $this->getChannel($channel_id);

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creating channel: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get channel by ID
     *
     * @param int $channel_id Channel ID
     * @return array|false Channel data or false if not found
     */
    public function getChannel($channel_id) {
        $sql = "SELECT c.*, u.name as creator_name, u.email as creator_email
                FROM chat_channels c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE c.id = :channel_id AND c.tenant_id = :tenant_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'channel_id' => $channel_id,
            'tenant_id' => $this->tenant_id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Add member to channel
     *
     * @param int $channel_id Channel ID
     * @param int $user_id User ID to add
     * @param string $role Member role
     * @return bool Success status
     */
    public function addChannelMember($channel_id, $user_id, $role = 'member') {
        try {
            $sql = "INSERT INTO chat_channel_members
                    (channel_id, user_id, role)
                    VALUES (:channel_id, :user_id, :role)
                    ON DUPLICATE KEY UPDATE role = VALUES(role)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'channel_id' => $channel_id,
                'user_id' => $user_id,
                'role' => $role
            ]);
        } catch (Exception $e) {
            error_log("Error adding channel member: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is member of channel
     *
     * @param int $channel_id Channel ID
     * @param int $user_id User ID
     * @return array|false Member data or false if not member
     */
    public function isChannelMember($channel_id, $user_id) {
        $sql = "SELECT * FROM chat_channel_members
                WHERE channel_id = :channel_id AND user_id = :user_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'channel_id' => $channel_id,
            'user_id' => $user_id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get messages for a channel
     *
     * @param int $channel_id Channel ID
     * @param int $limit Number of messages to retrieve
     * @param int $offset Offset for pagination
     * @param int|null $parent_id Parent message ID for threads
     * @return array List of messages with user info and reactions
     */
    public function getMessages($channel_id, $limit = 50, $offset = 0, $parent_id = null) {
        $sql = "
            SELECT
                m.*,
                u.name as user_name,
                u.email as user_email,
                u.avatar as user_avatar,
                f.filename as attachment_name,
                f.file_size as attachment_size,
                f.mime_type as attachment_type,
                (SELECT COUNT(*) FROM chat_messages WHERE parent_message_id = m.id) as reply_count,
                (SELECT GROUP_CONCAT(
                    CONCAT(emoji, ':', COUNT(*))
                    ORDER BY COUNT(*) DESC
                    SEPARATOR ','
                ) FROM message_reactions
                WHERE message_id = m.id
                GROUP BY emoji) as reactions
            FROM chat_messages m
            LEFT JOIN users u ON m.user_id = u.id
            LEFT JOIN files f ON m.attachment_id = f.id
            WHERE m.channel_id = :channel_id
            AND m.deleted_at IS NULL
        ";

        $params = ['channel_id' => $channel_id];

        if ($parent_id !== null) {
            $sql .= " AND m.parent_message_id = :parent_id";
            $params['parent_id'] = $parent_id;
        } else {
            $sql .= " AND m.parent_message_id IS NULL";
        }

        $sql .= " ORDER BY m.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':channel_id', $channel_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        if ($parent_id !== null) {
            $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
        }

        $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process reactions into structured format
        foreach ($messages as &$message) {
            if ($message['reactions']) {
                $reaction_pairs = explode(',', $message['reactions']);
                $reactions = [];
                foreach ($reaction_pairs as $pair) {
                    list($emoji, $count) = explode(':', $pair);
                    $reactions[] = [
                        'emoji' => $emoji,
                        'count' => (int)$count
                    ];
                }
                $message['reactions'] = $reactions;
            } else {
                $message['reactions'] = [];
            }

            // Get mentions for this message
            $message['mentions'] = $this->getMessageMentions($message['id']);
        }

        return $messages;
    }

    /**
     * Send a new message
     *
     * @param array $data Message data
     * @return array|false Created message or false on failure
     */
    public function sendMessage($data) {
        try {
            $this->db->beginTransaction();

            // Insert the message
            $sql = "INSERT INTO chat_messages
                    (channel_id, user_id, parent_message_id, content, message_type, attachment_id, metadata)
                    VALUES (:channel_id, :user_id, :parent_message_id, :content, :message_type, :attachment_id, :metadata)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'channel_id' => $data['channel_id'],
                'user_id' => $this->user_id,
                'parent_message_id' => $data['parent_message_id'] ?? null,
                'content' => $data['content'],
                'message_type' => $data['message_type'] ?? 'text',
                'attachment_id' => $data['attachment_id'] ?? null,
                'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
            ]);

            $message_id = $this->db->lastInsertId();

            // Process mentions
            if (!empty($data['content'])) {
                $mentions = $this->extractMentions($data['content']);
                foreach ($mentions as $mentioned_user_id) {
                    $this->addMention($message_id, $mentioned_user_id);
                }
            }

            // Update user presence
            $this->updatePresence([
                'status' => 'online',
                'current_channel_id' => $data['channel_id']
            ]);

            // Update channel member last seen
            $sql = "UPDATE chat_channel_members
                    SET last_seen_at = CURRENT_TIMESTAMP
                    WHERE channel_id = :channel_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'channel_id' => $data['channel_id'],
                'user_id' => $this->user_id
            ]);

            $this->db->commit();

            // Fetch and return the complete message
            return $this->getMessage($message_id);

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error sending message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get single message by ID
     *
     * @param int $message_id Message ID
     * @return array|false Message data or false if not found
     */
    public function getMessage($message_id) {
        $sql = "
            SELECT
                m.*,
                u.name as user_name,
                u.email as user_email,
                u.avatar as user_avatar,
                f.filename as attachment_name,
                f.file_size as attachment_size,
                f.mime_type as attachment_type
            FROM chat_messages m
            LEFT JOIN users u ON m.user_id = u.id
            LEFT JOIN files f ON m.attachment_id = f.id
            WHERE m.id = :message_id
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['message_id' => $message_id]);

        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($message) {
            $message['reactions'] = $this->getMessageReactions($message_id);
            $message['mentions'] = $this->getMessageMentions($message_id);
        }

        return $message;
    }

    /**
     * Extract @mentions from message content
     *
     * @param string $content Message content
     * @return array Array of user IDs mentioned
     */
    private function extractMentions($content) {
        $mentions = [];

        // Pattern to match @username or @email
        if (preg_match_all('/@([a-zA-Z0-9._-]+)/', $content, $matches)) {
            foreach ($matches[1] as $mention) {
                // Try to find user by username or email
                $sql = "SELECT id FROM users
                        WHERE (email LIKE :mention OR name LIKE :mention)
                        AND EXISTS (
                            SELECT 1 FROM user_tenant_associations
                            WHERE user_id = users.id AND tenant_id = :tenant_id
                        )";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'mention' => $mention . '%',
                    'tenant_id' => $this->tenant_id
                ]);

                if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $mentions[] = $user['id'];
                }
            }
        }

        return array_unique($mentions);
    }

    /**
     * Add a mention record
     *
     * @param int $message_id Message ID
     * @param int $user_id Mentioned user ID
     * @return bool Success status
     */
    private function addMention($message_id, $user_id) {
        try {
            $sql = "INSERT INTO message_mentions
                    (message_id, mentioned_user_id, mention_type)
                    VALUES (:message_id, :user_id, 'user')
                    ON DUPLICATE KEY UPDATE mention_type = VALUES(mention_type)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'message_id' => $message_id,
                'user_id' => $user_id
            ]);
        } catch (Exception $e) {
            error_log("Error adding mention: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get mentions for a message
     *
     * @param int $message_id Message ID
     * @return array List of mentioned users
     */
    private function getMessageMentions($message_id) {
        $sql = "SELECT m.*, u.name, u.email
                FROM message_mentions m
                LEFT JOIN users u ON m.mentioned_user_id = u.id
                WHERE m.message_id = :message_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['message_id' => $message_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get reactions for a message
     *
     * @param int $message_id Message ID
     * @return array List of reactions grouped by emoji
     */
    private function getMessageReactions($message_id) {
        $sql = "SELECT emoji, COUNT(*) as count,
                GROUP_CONCAT(u.name SEPARATOR ', ') as users
                FROM message_reactions r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.message_id = :message_id
                GROUP BY emoji";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['message_id' => $message_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add reaction to a message
     *
     * @param int $message_id Message ID
     * @param string $emoji Emoji character or code
     * @return bool Success status
     */
    public function addReaction($message_id, $emoji) {
        try {
            $sql = "INSERT INTO message_reactions
                    (message_id, user_id, emoji)
                    VALUES (:message_id, :user_id, :emoji)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'message_id' => $message_id,
                'user_id' => $this->user_id,
                'emoji' => $emoji
            ]);
        } catch (PDOException $e) {
            // If duplicate, it means user already reacted with this emoji
            if ($e->getCode() == 23000) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Remove reaction from a message
     *
     * @param int $message_id Message ID
     * @param string $emoji Emoji character or code
     * @return bool Success status
     */
    public function removeReaction($message_id, $emoji) {
        $sql = "DELETE FROM message_reactions
                WHERE message_id = :message_id
                AND user_id = :user_id
                AND emoji = :emoji";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'message_id' => $message_id,
            'user_id' => $this->user_id,
            'emoji' => $emoji
        ]);
    }

    /**
     * Poll for new messages (for long-polling)
     *
     * @param int $last_message_id Last known message ID
     * @param int|null $channel_id Optional channel filter
     * @param int $timeout Maximum wait time in seconds
     * @return array New messages
     */
    public function pollMessages($last_message_id, $channel_id = null, $timeout = 30) {
        $start_time = time();
        $messages = [];

        while (time() - $start_time < $timeout) {
            $sql = "
                SELECT
                    m.*,
                    c.tenant_id,
                    u.name as user_name,
                    u.email as user_email,
                    u.avatar as user_avatar
                FROM chat_messages m
                JOIN chat_channels c ON m.channel_id = c.id
                LEFT JOIN users u ON m.user_id = u.id
                WHERE m.id > :last_message_id
                AND c.tenant_id = :tenant_id
                AND m.deleted_at IS NULL
            ";

            $params = [
                'last_message_id' => $last_message_id,
                'tenant_id' => $this->tenant_id
            ];

            if ($channel_id) {
                $sql .= " AND m.channel_id = :channel_id";
                $params['channel_id'] = $channel_id;
            } else {
                // Only get messages from channels user is member of
                $sql .= " AND EXISTS (
                    SELECT 1 FROM chat_channel_members
                    WHERE channel_id = m.channel_id AND user_id = :user_id
                )";
                $params['user_id'] = $this->user_id;
            }

            $sql .= " ORDER BY m.created_at ASC LIMIT 100";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($messages)) {
                // Process each message
                foreach ($messages as &$message) {
                    $message['reactions'] = $this->getMessageReactions($message['id']);
                    $message['mentions'] = $this->getMessageMentions($message['id']);
                }
                break;
            }

            // Sleep for 1 second before checking again
            sleep(1);
        }

        return $messages;
    }

    /**
     * Update user presence
     *
     * @param array $data Presence data
     * @return bool Success status
     */
    public function updatePresence($data) {
        try {
            $sql = "INSERT INTO chat_presence
                    (tenant_id, user_id, status, status_message, current_channel_id, device_info)
                    VALUES (:tenant_id, :user_id, :status, :status_message, :current_channel_id, :device_info)
                    ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    status_message = VALUES(status_message),
                    current_channel_id = VALUES(current_channel_id),
                    device_info = VALUES(device_info),
                    last_activity = CURRENT_TIMESTAMP";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'tenant_id' => $this->tenant_id,
                'user_id' => $this->user_id,
                'status' => $data['status'] ?? 'online',
                'status_message' => $data['status_message'] ?? null,
                'current_channel_id' => $data['current_channel_id'] ?? null,
                'device_info' => isset($data['device_info']) ? json_encode($data['device_info']) : null
            ]);
        } catch (Exception $e) {
            error_log("Error updating presence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get presence status for users
     *
     * @param int|null $channel_id Optional channel filter
     * @return array List of user presence data
     */
    public function getPresence($channel_id = null) {
        $sql = "
            SELECT
                p.*,
                u.name,
                u.email,
                u.avatar,
                c.name as current_channel_name
            FROM chat_presence p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN chat_channels c ON p.current_channel_id = c.id
            WHERE p.tenant_id = :tenant_id
        ";

        $params = ['tenant_id' => $this->tenant_id];

        if ($channel_id) {
            // Get users who are members of this channel
            $sql .= " AND p.user_id IN (
                SELECT user_id FROM chat_channel_members
                WHERE channel_id = :channel_id
            )";
            $params['channel_id'] = $channel_id;
        }

        // Consider users offline if no activity for 5 minutes
        $sql .= " AND (
            p.status = 'offline'
            OR p.last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        )";

        $sql .= " ORDER BY
            CASE p.status
                WHEN 'online' THEN 1
                WHEN 'busy' THEN 2
                WHEN 'away' THEN 3
                WHEN 'do_not_disturb' THEN 4
                ELSE 5
            END,
            u.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark messages as read
     *
     * @param int $channel_id Channel ID
     * @param int $message_id Last read message ID
     * @return bool Success status
     */
    public function markAsRead($channel_id, $message_id) {
        try {
            $sql = "INSERT INTO message_reads
                    (user_id, channel_id, last_read_message_id, unread_count, unread_mentions)
                    VALUES (:user_id, :channel_id, :message_id, 0, 0)
                    ON DUPLICATE KEY UPDATE
                    last_read_message_id = VALUES(last_read_message_id),
                    unread_count = 0,
                    unread_mentions = 0";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $this->user_id,
                'channel_id' => $channel_id,
                'message_id' => $message_id
            ]);
        } catch (Exception $e) {
            error_log("Error marking as read: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get unread counts for user
     *
     * @return array Unread counts per channel
     */
    public function getUnreadCounts() {
        $sql = "
            SELECT
                c.id as channel_id,
                c.name as channel_name,
                c.type as channel_type,
                COALESCE(r.unread_count,
                    (SELECT COUNT(*) FROM chat_messages
                     WHERE channel_id = c.id
                     AND id > COALESCE(r.last_read_message_id, 0)
                     AND deleted_at IS NULL)
                ) as unread_count,
                COALESCE(r.unread_mentions, 0) as unread_mentions
            FROM chat_channels c
            JOIN chat_channel_members cm ON c.id = cm.channel_id
            LEFT JOIN message_reads r ON c.id = r.channel_id AND r.user_id = :user_id
            WHERE cm.user_id = :user_id
            AND c.tenant_id = :tenant_id
            AND c.is_archived = 0
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $this->user_id,
            'tenant_id' => $this->tenant_id
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>