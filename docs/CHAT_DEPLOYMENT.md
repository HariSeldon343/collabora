# NEXIOSOLUTION - CHAT MODULE DEPLOYMENT GUIDE
## Part 4: Chat & Communication System

### Table of Contents
1. [System Requirements](#system-requirements)
2. [Installation Steps](#installation-steps)
3. [Configuration Options](#configuration-options)
4. [Server Optimization](#server-optimization)
5. [Troubleshooting Guide](#troubleshooting-guide)
6. [Performance Tuning](#performance-tuning)
7. [Security Considerations](#security-considerations)
8. [API Reference](#api-reference)

---

## System Requirements

### Minimum Requirements
- **PHP**: 7.4+ (8.0+ recommended)
- **MySQL/MariaDB**: 5.7+ / 10.3+
- **Apache**: 2.4+
- **RAM**: 2GB minimum (4GB recommended)
- **Disk Space**: 1GB for application + storage for uploads

### PHP Extensions Required
```
- mysqli or PDO_MySQL
- json
- mbstring
- session
- fileinfo
- gd or imagick (for image processing)
- openssl (for encryption)
```

### Apache Modules Required
```
- mod_rewrite
- mod_headers
- mod_expires
- mod_php or php-fpm
```

### Browser Support
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

---

## Installation Steps

### 1. Quick Installation (Windows XAMPP)

```batch
# Run the deployment script
C:\xampp\htdocs\Nexiosolution\collabora\deploy_part4_chat.bat
```

### 2. Manual Installation

#### Step 1: Database Setup
```sql
-- Connect to MySQL
mysql -u root -p

-- Select database
USE nexiosolution;

-- Import chat module tables
SOURCE C:/xampp/htdocs/Nexiosolution/collabora/database/migrations_part4_chat.sql;
```

#### Step 2: Configure PHP Settings
Edit `C:\xampp\php\php.ini`:
```ini
; Long-polling support
max_execution_time = 60
max_input_time = 60
memory_limit = 256M

; Session settings
session.gc_maxlifetime = 3600
session.cookie_httponly = 1
session.use_only_cookies = 1

; File uploads
upload_max_filesize = 10M
post_max_size = 12M
max_file_uploads = 20
```

#### Step 3: Configure Apache
Edit `C:\xampp\apache\conf\extra\httpd-default.conf`:
```apache
# Keep-Alive settings for long-polling
KeepAlive On
KeepAliveTimeout 65
MaxKeepAliveRequests 100

# Timeout for long requests
Timeout 300
```

#### Step 4: Create Required Directories
```batch
mkdir C:\xampp\htdocs\Nexiosolution\collabora\uploads\chat
mkdir C:\xampp\htdocs\Nexiosolution\collabora\logs\chat
mkdir C:\xampp\htdocs\Nexiosolution\collabora\temp\chat
mkdir C:\xampp\htdocs\Nexiosolution\collabora\config
```

#### Step 5: Set Permissions (Windows)
```batch
icacls "C:\xampp\htdocs\Nexiosolution\collabora\uploads\chat" /grant Everyone:F /T
icacls "C:\xampp\htdocs\Nexiosolution\collabora\logs\chat" /grant Everyone:F /T
icacls "C:\xampp\htdocs\Nexiosolution\collabora\temp\chat" /grant Everyone:F /T
```

#### Step 6: Test Installation
```batch
# Run test suite
php C:\xampp\htdocs\Nexiosolution\collabora\test_part4_chat.php

# Access chat interface
start http://localhost/Nexiosolution/collabora/chat.php
```

---

## Configuration Options

### Chat Configuration File
Location: `/config/chat.config.php`

#### Key Configuration Parameters

##### Long-Polling Settings
```php
define('CHAT_POLL_TIMEOUT', 45);        // Seconds before timeout
define('CHAT_POLL_INTERVAL', 1);         // Check interval in seconds
define('CHAT_POLL_MAX_RETRIES', 3);      // Max connection retries
```

##### Message Settings
```php
define('CHAT_MAX_MESSAGE_LENGTH', 5000); // Max characters per message
define('CHAT_MESSAGES_PER_PAGE', 50);    // Messages per load
define('CHAT_ALLOW_MESSAGE_EDIT', true); // Enable message editing
define('CHAT_EDIT_TIME_LIMIT', 15);      // Edit window in minutes
```

##### File Upload Settings
```php
define('CHAT_MAX_FILE_SIZE', 10485760);  // 10MB in bytes
define('CHAT_ALLOWED_EXTENSIONS', [...]);// Allowed file types
define('CHAT_GENERATE_THUMBNAILS', true);// Auto-generate image thumbs
```

##### Presence Settings
```php
define('CHAT_PRESENCE_TIMEOUT', 120);    // Offline after X seconds
define('CHAT_PRESENCE_UPDATE_INTERVAL', 30); // Heartbeat interval
define('CHAT_TYPING_INDICATORS', true);  // Show typing status
```

##### Rate Limiting
```php
define('CHAT_RATE_LIMITING', true);      // Enable rate limits
define('CHAT_RATE_LIMIT', 30);           // Messages per minute
define('CHAT_RATE_LIMIT_COOLDOWN', 60);  // Cooldown in seconds
```

### Environment-Specific Configuration

#### Development
```php
define('CHAT_DEBUG_MODE', true);
define('CHAT_DEBUG_SQL', true);
define('CHAT_PROFILING', true);
```

#### Production
```php
define('CHAT_DEBUG_MODE', false);
define('CHAT_ENABLE_CACHE', true);
define('CHAT_E2E_ENCRYPTION', true);
```

---

## Server Optimization

### Apache Optimization

#### Enable Compression
Add to `.htaccess`:
```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>
```

#### Browser Caching
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 week"
    ExpiresByType image/png "access plus 1 week"
    ExpiresByType text/css "access plus 1 day"
    ExpiresByType application/javascript "access plus 1 day"
</IfModule>
```

### MySQL Optimization

#### Indexes for Performance
```sql
-- Add indexes for chat queries
ALTER TABLE chat_messages ADD INDEX idx_channel_created (channel_id, created_at);
ALTER TABLE chat_messages ADD INDEX idx_user_channel (user_id, channel_id);
ALTER TABLE chat_channel_members ADD INDEX idx_user_joined (user_id, joined_at);
ALTER TABLE chat_presence ADD INDEX idx_user_activity (user_id, last_activity);

-- Full-text search index
ALTER TABLE chat_messages ADD FULLTEXT(message);
```

#### MySQL Configuration
Add to `my.ini`:
```ini
[mysqld]
# Connection pool
max_connections = 200
max_user_connections = 50

# Query cache
query_cache_type = 1
query_cache_size = 32M
query_cache_limit = 2M

# InnoDB settings
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2
```

### PHP Optimization

#### OpCache Settings
```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

---

## Troubleshooting Guide

### Common Issues and Solutions

#### 1. Long-Polling Not Working
**Symptoms**: Messages don't update in real-time

**Solutions**:
```batch
# Check PHP timeout settings
php -i | findstr "max_execution_time"

# Verify Apache KeepAlive
httpd -t -D DUMP_MODULES | findstr keepalive

# Test long-polling endpoint
curl -X GET "http://localhost/Nexiosolution/collabora/api/chat-poll.php" -H "Cookie: PHPSESSID=your_session_id"
```

#### 2. File Uploads Failing
**Symptoms**: "File too large" or "Invalid file type" errors

**Solutions**:
```php
// Check PHP settings
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . PHP_EOL;
echo "post_max_size: " . ini_get('post_max_size') . PHP_EOL;
echo "max_file_uploads: " . ini_get('max_file_uploads') . PHP_EOL;

// Verify upload directory
$uploadDir = 'C:/xampp/htdocs/Nexiosolution/collabora/uploads/chat';
echo "Directory exists: " . (is_dir($uploadDir) ? 'Yes' : 'No') . PHP_EOL;
echo "Directory writable: " . (is_writable($uploadDir) ? 'Yes' : 'No') . PHP_EOL;
```

#### 3. Database Connection Errors
**Symptoms**: "Connection refused" or "Too many connections"

**Solutions**:
```sql
-- Check current connections
SHOW PROCESSLIST;
SHOW VARIABLES LIKE 'max_connections';

-- Increase connection limit
SET GLOBAL max_connections = 200;
```

#### 4. Session Issues
**Symptoms**: Users logged out unexpectedly

**Solutions**:
```php
// Check session configuration
session_start();
echo "Session save path: " . session_save_path() . PHP_EOL;
echo "Session GC maxlifetime: " . ini_get('session.gc_maxlifetime') . PHP_EOL;
echo "Session cookie lifetime: " . ini_get('session.cookie_lifetime') . PHP_EOL;

// Fix session directory permissions
chmod 0777 C:/xampp/tmp
```

#### 5. CORS Errors
**Symptoms**: "CORS policy" errors in browser console

**Solutions**:
Add to `.htaccess`:
```apache
<IfModule mod_headers.c>
    Header set Access-Control-Allow-Origin "http://localhost"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header set Access-Control-Allow-Headers "Content-Type, Authorization"
    Header set Access-Control-Allow-Credentials "true"
</IfModule>
```

### Debug Mode

Enable debug mode for detailed error information:
```php
// In config/chat.config.php
define('CHAT_DEBUG_MODE', true);
define('CHAT_DEBUG_SQL', true);

// Check logs
tail -f logs/chat/2025-01-20.log
```

### Testing Tools

#### Test API Endpoints
```batch
# Test channels API
curl -X GET http://localhost/Nexiosolution/collabora/api/channels.php -b cookies.txt

# Test messages API
curl -X POST http://localhost/Nexiosolution/collabora/api/messages.php ^
  -H "Content-Type: application/json" ^
  -b cookies.txt ^
  -d "{\"channel_id\":1,\"message\":\"Test message\"}"

# Test presence API
curl -X POST http://localhost/Nexiosolution/collabora/api/presence.php ^
  -H "Content-Type: application/json" ^
  -b cookies.txt ^
  -d "{\"status\":\"online\"}"
```

---

## Performance Tuning

### Message Loading Optimization

#### Lazy Loading
```javascript
// Load messages in chunks
const loadMessages = async (channelId, offset = 0) => {
    const response = await fetch(`/api/messages.php?channel_id=${channelId}&offset=${offset}&limit=50`);
    return response.json();
};
```

#### Virtual Scrolling
```javascript
// Implement virtual scrolling for large message lists
// Only render visible messages
```

### Database Query Optimization

#### Use Prepared Statements
```php
$stmt = $pdo->prepare("
    SELECT m.*, u.name, u.avatar
    FROM chat_messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.channel_id = ?
    AND m.created_at > ?
    ORDER BY m.created_at DESC
    LIMIT ?
");
$stmt->execute([$channelId, $lastCheck, $limit]);
```

#### Implement Caching
```php
// Cache frequently accessed data
$cacheKey = "channel_members_{$channelId}";
$members = $cache->get($cacheKey);

if (!$members) {
    $members = fetchMembersFromDB($channelId);
    $cache->set($cacheKey, $members, 300); // Cache for 5 minutes
}
```

### Network Optimization

#### Message Batching
```javascript
// Batch multiple operations
const batchOperations = [];
batchOperations.push(markAsRead(messageIds));
batchOperations.push(updatePresence('online'));
await Promise.all(batchOperations);
```

#### WebSocket Alternative (Future Enhancement)
```javascript
// Consider upgrading to WebSockets for real-time communication
const ws = new WebSocket('ws://localhost:8080/chat');
ws.onmessage = (event) => {
    const message = JSON.parse(event.data);
    displayMessage(message);
};
```

---

## Security Considerations

### Input Validation
```php
// Sanitize all user inputs
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

// Validate file uploads
if (!isAllowedFileType($_FILES['file']['name'])) {
    throw new Exception('Invalid file type');
}
```

### SQL Injection Prevention
```php
// Always use prepared statements
$stmt = $pdo->prepare("INSERT INTO chat_messages (channel_id, user_id, message) VALUES (?, ?, ?)");
$stmt->execute([$channelId, $userId, $message]);
```

### XSS Prevention
```javascript
// Escape HTML in messages
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}
```

### CSRF Protection
```php
// Verify CSRF token
session_start();
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('CSRF validation failed');
}
```

### Rate Limiting Implementation
```php
// Implement rate limiting
$rateLimiter = new RateLimiter($userId);
if (!$rateLimiter->allowRequest()) {
    http_response_code(429);
    die('Rate limit exceeded');
}
```

---

## API Reference

### Authentication
All API endpoints require an active session. Include session cookie in requests.

### Endpoints

#### GET /api/channels.php
Get user's channels
```json
Response:
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "General",
            "type": "public",
            "unread_count": 5
        }
    ]
}
```

#### POST /api/messages.php
Send a message
```json
Request:
{
    "channel_id": 1,
    "message": "Hello world",
    "type": "text"
}

Response:
{
    "success": true,
    "data": {
        "id": 123,
        "timestamp": "2025-01-20T10:00:00Z"
    }
}
```

#### GET /api/chat-poll.php
Long-polling for new messages
```json
Response:
{
    "success": true,
    "data": {
        "messages": [...],
        "presence_updates": [...],
        "typing": [...]
    }
}
```

#### POST /api/presence.php
Update user presence
```json
Request:
{
    "status": "online"
}
```

#### POST /api/reactions.php
Add reaction to message
```json
Request:
{
    "message_id": 123,
    "reaction": "👍"
}
```

---

## Maintenance

### Daily Tasks
- Monitor log files for errors
- Check disk usage for uploads
- Verify backup completion

### Weekly Tasks
- Review performance metrics
- Clean up old temp files
- Update user permissions

### Monthly Tasks
- Archive old messages
- Optimize database tables
- Review security logs
- Update dependencies

### Cleanup Scripts

#### Clean temp files
```batch
@echo off
forfiles /p "C:\xampp\htdocs\Nexiosolution\collabora\temp\chat" /s /m *.* /D -7 /C "cmd /c del @path"
echo Temp files older than 7 days deleted
```

#### Archive old logs
```batch
@echo off
set LOG_DIR=C:\xampp\htdocs\Nexiosolution\collabora\logs\chat
set ARCHIVE_DIR=%LOG_DIR%\archive

if not exist "%ARCHIVE_DIR%" mkdir "%ARCHIVE_DIR%"
forfiles /p "%LOG_DIR%" /m *.log /D -30 /C "cmd /c move @path %ARCHIVE_DIR%"
echo Logs older than 30 days archived
```

---

## Support

### Getting Help
1. Check this documentation
2. Review error logs in `/logs/chat/`
3. Run diagnostic script: `php test_part4_chat.php`
4. Check PARTE4-PROGRESS.md for implementation details

### Reporting Issues
When reporting issues, include:
- Error messages from logs
- Steps to reproduce
- System configuration
- Browser console output

### Resources
- [PHP Documentation](https://www.php.net/manual/)
- [Apache Documentation](https://httpd.apache.org/docs/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [MDN Web Docs](https://developer.mozilla.org/)

---

## Version History

### Version 1.0.0 (2025-01-20)
- Initial release of chat module
- Basic messaging functionality
- Channel management
- File uploads
- Presence tracking
- Long-polling support
- Multi-tenant isolation

### Planned Features
- WebSocket support
- Voice/video calls
- Screen sharing
- Message threading
- Advanced search
- Bot integration
- Mobile app support

---

*Document last updated: 2025-01-20*