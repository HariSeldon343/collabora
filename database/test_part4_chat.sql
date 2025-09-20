-- =====================================================
-- TEST QUERIES FOR PART 4: CHAT MODULE
-- =====================================================
-- Run these queries after applying migrations_part4_chat.sql
-- =====================================================

USE nexio_collabora_v2;

-- 1. Show all chat-related tables
SELECT TABLE_NAME, TABLE_COMMENT
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'nexio_collabora_v2'
AND TABLE_NAME LIKE 'chat_%'
ORDER BY TABLE_NAME;

-- 2. Verify channel structure
DESCRIBE chat_channels;

-- 3. Check sample channels
SELECT
    c.id,
    c.tenant_id,
    t.name as tenant_name,
    c.type,
    c.name,
    c.created_by,
    u.name as created_by_name,
    c.is_archived,
    c.created_at
FROM chat_channels c
JOIN tenants t ON c.tenant_id = t.id
JOIN users u ON c.created_by = u.id
ORDER BY c.tenant_id, c.id;

-- 4. Check channel membership
SELECT
    cm.channel_id,
    c.name as channel_name,
    cm.user_id,
    u.name as user_name,
    cm.role,
    cm.joined_at
FROM chat_channel_members cm
JOIN chat_channels c ON cm.channel_id = c.id
JOIN users u ON cm.user_id = u.id
WHERE c.type != 'direct'
ORDER BY cm.channel_id, cm.role, cm.user_id;

-- 5. View messages with reactions
SELECT
    m.id,
    c.name as channel,
    u.name as sender,
    m.content,
    m.message_type,
    m.created_at,
    GROUP_CONCAT(CONCAT(r.emoji, '(', ru.name, ')') SEPARATOR ', ') as reactions
FROM chat_messages m
JOIN chat_channels c ON m.channel_id = c.id
JOIN users u ON m.user_id = u.id
LEFT JOIN message_reactions r ON m.id = r.message_id
LEFT JOIN users ru ON r.user_id = ru.id
GROUP BY m.id
ORDER BY m.created_at DESC
LIMIT 10;

-- 6. Check unread messages view
SELECT * FROM v_unread_messages;

-- 7. Check active channels view
SELECT * FROM v_active_channels;

-- 8. Test direct message channel creation
CALL sp_create_direct_channel(1, 2, 3);

-- 9. Check user presence
SELECT
    p.user_id,
    u.name,
    p.status,
    p.status_message,
    p.last_activity
FROM chat_presence p
JOIN users u ON p.user_id = u.id
ORDER BY p.tenant_id, p.status;

-- 10. Analytics query - messages per channel
SELECT
    c.name as channel_name,
    c.type,
    COUNT(m.id) as message_count,
    COUNT(DISTINCT m.user_id) as unique_senders,
    MIN(m.created_at) as first_message,
    MAX(m.created_at) as last_message
FROM chat_channels c
LEFT JOIN chat_messages m ON c.id = m.channel_id
WHERE m.deleted_at IS NULL OR m.deleted_at IS NULL
GROUP BY c.id
ORDER BY message_count DESC;

-- 11. Check indexes
SELECT
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = 'nexio_collabora_v2'
AND TABLE_NAME LIKE 'chat_%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;

-- 12. Check foreign key constraints
SELECT
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'nexio_collabora_v2'
AND TABLE_NAME LIKE 'chat_%'
AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- 13. Test marking messages as read
CALL sp_mark_messages_read(3, 1, 4);

-- Check updated read status
SELECT * FROM message_reads WHERE user_id = 3;

-- 14. Find mentions
SELECT
    mm.message_id,
    m.content,
    mm.mentioned_user_id,
    u.name as mentioned_user,
    mm.mention_type,
    mm.read_at
FROM message_mentions mm
JOIN chat_messages m ON mm.message_id = m.id
JOIN users u ON mm.mentioned_user_id = u.id
ORDER BY mm.created_at DESC;

-- 15. Performance check - table sizes
SELECT
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(DATA_LENGTH/1024/1024, 2) as data_size_mb,
    ROUND(INDEX_LENGTH/1024/1024, 2) as index_size_mb
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'nexio_collabora_v2'
AND TABLE_NAME LIKE 'chat_%'
ORDER BY TABLE_ROWS DESC;

-- =====================================================
-- Expected Results:
-- - 11 chat tables created
-- - 2 views functioning
-- - 2 stored procedures working
-- - Sample data properly inserted
-- - All foreign keys valid
-- - Indexes optimized for queries
-- =====================================================