-- Missing Tables Creation Script
-- Generated: 2025-09-20 19:58:30

USE nexio_collabora_v2;

-- Calendar containers

CREATE TABLE IF NOT EXISTS calendars (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#4F46E5',
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_owner (tenant_id, owner_id)
);

-- Calendar events

CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    calendar_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    start_datetime DATETIME NOT NULL,
    end_datetime DATETIME NOT NULL,
    all_day BOOLEAN DEFAULT FALSE,
    recurrence_rule VARCHAR(500),
    status ENUM('confirmed', 'tentative', 'cancelled') DEFAULT 'confirmed',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_calendar (calendar_id),
    INDEX idx_dates (start_datetime, end_datetime)
);

-- Event attendees

CREATE TABLE IF NOT EXISTS event_participants (
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    response ENUM('accepted', 'declined', 'tentative', 'needs-action') DEFAULT 'needs-action',
    responded_at TIMESTAMP NULL,
    PRIMARY KEY (event_id, user_id)
);

-- Task boards/lists

CREATE TABLE IF NOT EXISTS task_lists (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    position INT DEFAULT 0,
    view_type ENUM('kanban', 'list', 'calendar') DEFAULT 'kanban',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_position (position)
);

-- Task items

CREATE TABLE IF NOT EXISTS tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    task_list_id INT NOT NULL,
    parent_task_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('todo', 'in_progress', 'review', 'done', 'cancelled') DEFAULT 'todo',
    priority ENUM('urgent', 'high', 'medium', 'low') DEFAULT 'medium',
    position INT DEFAULT 0,
    due_date DATE NULL,
    estimated_hours DECIMAL(5,2),
    actual_hours DECIMAL(5,2),
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    INDEX idx_list_status (task_list_id, status),
    INDEX idx_parent (parent_task_id),
    INDEX idx_due_date (due_date)
);

-- Task assignees

CREATE TABLE IF NOT EXISTS task_assignments (
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (task_id, user_id)
);

-- Chat channels

CREATE TABLE IF NOT EXISTS chat_channels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('public', 'private', 'direct') DEFAULT 'public',
    description TEXT,
    created_by INT,
    is_archived BOOLEAN DEFAULT FALSE,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_type (type),
    INDEX idx_archived (is_archived)
);

-- Channel membership

CREATE TABLE IF NOT EXISTS chat_channel_members (
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM('owner', 'admin', 'member') DEFAULT 'member',
    notification_preference ENUM('all', 'mentions', 'none') DEFAULT 'all',
    muted_until TIMESTAMP NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (channel_id, user_id),
    INDEX idx_user (user_id)
);

-- Chat messages

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    channel_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT,
    message_type ENUM('text', 'system', 'file') DEFAULT 'text',
    parent_message_id INT NULL,
    attachment_id INT NULL,
    metadata JSON,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_channel (channel_id),
    INDEX idx_parent (parent_message_id),
    INDEX idx_created (created_at)
);

-- Read receipts

CREATE TABLE IF NOT EXISTS message_reads (
    user_id INT NOT NULL,
    channel_id INT NOT NULL,
    last_read_message_id INT,
    unread_count INT DEFAULT 0,
    unread_mentions INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, channel_id)
);

-- User presence

CREATE TABLE IF NOT EXISTS chat_presence (
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('online', 'away', 'busy', 'offline') DEFAULT 'offline',
    current_channel_id INT NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, user_id),
    INDEX idx_status (status),
    INDEX idx_last_seen (last_seen)
);

-- Folder structure
-- Definition not available for table: folders

-- File metadata
-- Definition not available for table: files

