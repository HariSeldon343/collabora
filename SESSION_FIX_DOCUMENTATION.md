# Session Fix Documentation - Critical Bug Resolution

## Date: 2025-09-21
## Issue: API 401 Authentication Errors Due to Incorrect Session Handling

---

## Problem Summary

All API endpoints were returning 401 Unauthorized errors because they were calling `session_start()` BEFORE including `config_v2.php`. This caused the API endpoints to use the default PHP session name (`PHPSESSID`) instead of our configured session name (`NEXIO_V2_SESSID`).

### Root Cause
1. API endpoints were starting sessions with default PHP settings
2. The browser was sending cookies with `NEXIO_V2_SESSID` name
3. API endpoints were looking for `PHPSESSID` cookies
4. Session appeared empty, causing authentication to fail

### Impact
- Calendar functionality broken (401 errors)
- Task management broken (401 errors)
- Chat/messaging broken (401 errors)
- All authenticated API calls failing

---

## Files Fixed

The following API files have been corrected to include `config_v2.php` BEFORE any session handling or output:

### Core Authentication APIs
1. **`/api/auth_simple.php`** - Simple authentication endpoint
2. **`/api/auth_v2.php`** - V2 authentication endpoint
3. **`/api/me.php`** - User session status endpoint
4. **`/api/switch-tenant.php`** - Tenant switching endpoint

### Calendar & Events APIs
5. **`/api/calendars.php`** - Calendar management
6. **`/api/events.php`** - Event management

### Task Management APIs
7. **`/api/tasks.php`** - Task operations (already correct)
8. **`/api/task-lists.php`** - Task list/board management

### Chat & Messaging APIs
9. **`/api/messages.php`** - Message operations
10. **`/api/channels.php`** - Channel management
11. **`/api/chat-poll.php`** - Long-polling for real-time updates
12. **`/api/presence.php`** - User presence/status
13. **`/api/reactions.php`** - Message reactions

### User Management APIs
14. **`/api/users.php`** - User CRUD operations

---

## Fix Pattern Applied

### Before (INCORRECT):
```php
<?php
// Headers and CORS
header('Content-Type: application/json');
// ...

// Session started with default settings
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Uses PHPSESSID
}

// Config loaded too late
require_once __DIR__ . '/../config_v2.php';
```

### After (CORRECT):
```php
<?php
// Load configuration FIRST to set session name
require_once __DIR__ . '/../config_v2.php';

// Headers and CORS
header('Content-Type: application/json');
// ...

// SimpleAuth will handle session with correct name
require_once __DIR__ . '/../includes/SimpleAuth.php';
$auth = new SimpleAuth(); // Uses NEXIO_V2_SESSID
```

---

## Key Changes

1. **Config Loading Order**: `config_v2.php` is now loaded BEFORE any:
   - HTTP headers
   - Session operations
   - Authentication checks

2. **Session Handling**: Removed direct `session_start()` calls, letting SimpleAuth handle it with the correct session name

3. **Consistency**: All API endpoints now follow the same pattern

---

## Testing

### Test Script Created
Created `/test_session_fix.php` to verify:
- Session name configuration
- Config include order in all API files
- No session_start() before config
- Functional session read/write

### How to Test
```bash
# Run the verification script
php test_session_fix.php

# Test authentication
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'

# Test an authenticated endpoint
curl -X GET http://localhost/Nexiosolution/collabora/api/calendars.php \
  -b "NEXIO_V2_SESSID=<session_id_from_login>"
```

---

## Prevention

To prevent this issue in the future:

1. **Always include `config_v2.php` as the FIRST line after `<?php`**
2. **Never call `session_start()` directly in API endpoints**
3. **Let SimpleAuth handle all session management**
4. **Use the test script to verify new API endpoints**

### Template for New API Endpoints
```php
<?php
/**
 * API Endpoint Description
 */

// ALWAYS load configuration FIRST
require_once __DIR__ . '/../config_v2.php';

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
// ... other headers

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include authentication (handles session properly)
require_once __DIR__ . '/../includes/SimpleAuth.php';
$auth = new SimpleAuth();

// Check authentication
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Your API logic here...
```

---

## Verification Checklist

- [x] All API files include `config_v2.php` before any output
- [x] No direct `session_start()` calls before config include
- [x] Session name is correctly set to `NEXIO_V2_SESSID`
- [x] Authentication works across all endpoints
- [x] Browser cookies use correct session name
- [x] Test script created for future verification

---

## Impact Resolution

This fix resolves:
- Calendar viewing and management (401 errors)
- Task creation and updates (401 errors)
- Chat messaging functionality (401 errors)
- All API authentication issues related to session handling

The system should now properly authenticate users across all API endpoints using the configured `NEXIO_V2_SESSID` session name.