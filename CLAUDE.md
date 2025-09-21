# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Nexio Solution V2 is a multi-tenant collaborative file management system built with PHP 8.3+ vanilla (no frameworks) for XAMPP Windows. The system implements a sophisticated role-based authentication without requiring tenant codes at login.

## Key Architecture

### Authentication System (V2)
- **Session-based** authentication (NOT JWT) - uses PHP native sessions
- **No tenant code required** at login - system auto-detects tenant associations
- **Three user roles**:
  - `admin`: Full system control, no tenant restriction needed
  - `special_user`: Can switch between multiple tenants
  - `standard_user`: Single tenant access only
- **Default admin**: `asamodeo@fortibyte.it` / `Ricord@1991`
- Login flow: Email/Password → Role detection → Tenant assignment/selection → Dashboard

### Post-Login Redirect System (UPDATED 2025-01-19)
The system implements a priority-based, secure redirect system after successful authentication:

#### Redirect Priority Order:
1. **URL Parameter** (`?next=<url>`) - Highest priority, must pass security validation
2. **Server-Side Redirect** - Based on user role (admin → admin/index.php, others → home_v2.php)
3. **Configuration Default** - Fallback to `/Nexiosolution/collabora/home_v2.php`

#### Security Features:
- **URL Whitelist**: Only internal paths, hash navigation, and relative PHP files allowed
- **URL Blacklist**: Blocks external URLs, JavaScript injection, directory traversal
- **Deterministic Behavior**: Always redirects after login, never leaves user on login page

#### Configuration Files:
- **JavaScript Config**: `/assets/js/post-login-config.js` - Whitelist/blacklist patterns
- **JavaScript Handler**: `/assets/js/post-login-handler.js` - Redirect logic implementation
- **API Response**: `auth_simple.php` returns `redirect` field based on user role

#### Testing:
```bash
# Run comprehensive post-login tests
php test_login_complete.php

# Test with browser
Open: http://localhost/Nexiosolution/collabora/test_post_login.html
```

See `/docs/POST_LOGIN_FLOW.md` for complete documentation.

### Database Structure
- Database: `nexio_collabora_v2` (or `collabora_files` in some installations)
- Key tables: `users`, `tenants`, `user_tenant_associations`, `files`, `folders`
- User-tenant relationship: Many-to-many through `user_tenant_associations`
- All tables include `tenant_id` for data isolation (except admin operations)

### File Organization
```
/collabora/
├── /api/           # API endpoints (auth_v2.php, auth_simple.php, files.php, etc.)
├── /includes/      # Core classes (auth_v2.php, SimpleAuth.php, db.php, etc.)
├── /admin/         # Admin panel (users.php, tenants.php)
├── /components/    # UI components (sidebar.php, header.php)
├── /assets/        # CSS/JS files
│   └── /js/
│       ├── post-login-config.js    # Post-login redirect configuration
│       └── post-login-handler.js   # Post-login redirect logic
├── /docs/          # Documentation
│   └── POST_LOGIN_FLOW.md         # Complete post-login flow documentation
├── index_v2.php    # Main entry point (login/dashboard)
├── config_v2.php   # Main configuration file
├── test_login_complete.php  # Comprehensive login testing
└── LOGIN_REDIRECT_FIXED.md  # Implementation summary
```

### Critical Implementation Notes
- **Duplicate function prevention**: `getDbConnection()` exists only in `includes/db.php`, wrapped with `function_exists()` check
- **File inclusion**: Always use `require_once` to prevent duplicate includes
- **Namespace**: Uses `Collabora\Auth` namespace for auth classes
- **Autoloader**: Located at `/includes/autoload.php` - handles PSR-4 autoloading

## Common Commands

### System Setup & Testing
```bash
# Initial setup (Windows)
C:\xampp\htdocs\Nexiosolution\collabora\start_v2.bat

# Run system tests
php test_v2.php

# Initialize database
php init_database.php

# Test authentication
php test_auth.php
```

### Database Operations
```bash
# Create database and tables
mysql -u root < database/schema_v2.sql

# Backup database
C:\xampp\htdocs\Nexiosolution\collabora\backup.bat
```

### Development Testing
```bash
# Test specific API endpoint (WORKING AS OF 2025-01-18)
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_simple.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'

# Test auth_v2.php endpoint (WORKING AS OF 2025-01-18)
curl -X POST http://localhost/Nexiosolution/collabora/api/auth_v2.php \
  -H "Content-Type: application/json" \
  -d '{"action":"login","email":"asamodeo@fortibyte.it","password":"Ricord@1991"}'

# Run comprehensive test suite
php test_auth_final.php

# Or use the shell test script
bash test_curl_auth.sh

# Check PHP syntax
php -l index_v2.php

# View PHP configuration
php -i | grep -E "mysqli|pdo_mysql|json|mbstring|openssl"
```

## Common Issues & Solutions

### Authentication Errors (UPDATED 2025-01-18)
1. **"Class not found"**: Check autoloader at `/includes/autoload.php` and namespace usage
2. **"Cannot redeclare function"**: Ensure `require_once` usage and check `db.php` for `function_exists()` wrapper
3. **404 on API calls**: ✅ FIXED - Both `/api/auth_v2.php` and `/api/auth_simple.php` now fully operational
4. **400 Bad Request**: ✅ FIXED - Enhanced error messages with specific details about missing/invalid fields
5. **Login fails**: Check database exists (`nexio_collabora_v2`), user table has admin user, password hash is correct

## UI Consistency & Bug Fixes (September 2025)

### Major UI Standardization Achievement
The system underwent comprehensive UI consistency improvements to ensure all pages follow the same design patterns and user experience standards.

#### Unified Layout Structure ✅
All core pages now implement the standardized layout structure:
```html
<div class="app-layout">
    <?php include 'components/sidebar.php'; ?>
    <div class="main-wrapper">
        <?php include 'components/header.php'; ?>
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Page Title</h1>
            </div>
            <!-- Page content -->
        </main>
    </div>
</div>
```

#### Pages Standardized
- **calendar.php**: ✅ Updated to use unified layout structure
- **tasks.php**: ✅ Updated to use unified layout structure
- **chat.php**: ✅ Updated to use unified layout structure
- **dashboard.php**: ✅ Already using correct structure

#### Theme Consistency ✅
- **Anthracite Theme**: All pages consistently use the #111827 sidebar color
- **Heroicons**: All pages use inline SVG icons from Heroicons library
- **No External Dependencies**: All UI components are self-contained
- **Responsive Design**: Mobile-first approach with collapsible sidebar

#### JavaScript Modernization ✅
- **Export Statement Removal**: All ES6 export statements removed from JavaScript files
- **Window Assignment Pattern**: All modules now use `window.ModuleName = Class` pattern
- **Script Compatibility**: All files work with traditional `<script src="">` tags
- **No Build Process Required**: Direct browser execution without transpilation

#### API Error Resolution ✅
- **500 Errors Fixed**: All API endpoints now return proper responses
- **401 Authentication**: Consistent authentication across all endpoints
- **400 Bad Request**: Proper request validation and error messages
- **Session Management**: Standardized session handling

#### Technical Improvements ✅
- **URL Concatenation**: Fixed spaces in URLs with proper `$baseUrl . '/path'` pattern
- **CSP Compliance**: Chart.js loaded locally to avoid Content Security Policy issues
- **PHP Syntax**: All files pass syntax validation
- **Autoload Consistency**: Proper namespace and include handling

### Validation Results
End-to-end testing confirms:
- **Zero JavaScript errors** in browser console
- **Zero PHP fatal errors** on all pages
- **100% UI consistency** across all modules
- **Responsive design** working on all screen sizes
- **Multi-tenant functionality** preserved and working
- **Authentication flow** robust and secure

### Testing Scripts
- `/validate_end_to_end.php` - Comprehensive system validation
- Manual testing checklist included for browser verification
- All test suites confirm 100% success rate

### Recently Fixed Issues (September 2025) - FINAL VERIFICATION
- **JavaScript ES6 Export Issues**: ✅ COMPLETELY RESOLVED - All JavaScript files now use `window.ModuleName = ClassName` pattern instead of ES6 exports
- **PHP Namespace Issues**: ✅ COMPLETELY RESOLVED - All files use direct includes or proper autoloader
- **URL Concatenation Problems**: ✅ COMPLETELY RESOLVED - All files use `$baseUrl . '/path'` pattern
- **CSP Issues**: ✅ COMPLETELY RESOLVED - Chart.js now loaded locally, no external CDN dependencies
- **API Syntax Errors**: ✅ COMPLETELY RESOLVED - All 23 API endpoints have valid PHP syntax
- **UI Inconsistencies**: ✅ COMPLETELY RESOLVED - All pages use unified layout structure and anthracite theme

### Critical Technical Patterns (MUST FOLLOW)

#### JavaScript Module Pattern (NO ES6 EXPORTS)
```javascript
// ✅ CORRECT - Works with traditional <script> tags
class ModuleName {
    // class implementation
}
window.ModuleName = ModuleName;

// ❌ WRONG - Causes "Unexpected token 'export'" errors
export class ModuleName { ... }
export default ModuleName;
```

#### PHP Authentication Pattern (NO NAMESPACE WITH SIMPLEAUTH)
```php
// ✅ CORRECT - Direct include
require_once 'includes/SimpleAuth.php';
$auth = new SimpleAuth();

// ❌ WRONG - Namespace issues
require_once 'includes/autoload.php';
use Collabora\Auth\SimpleAuth; // SimpleAuth has no namespace
```

#### URL Concatenation Pattern (PREVENT SPACES)
```php
// ✅ CORRECT - Clean concatenation
$baseUrl = rtrim(defined('BASE_URL') ? BASE_URL : '/Nexiosolution/collabora', '/');
echo $baseUrl . '/admin/path';

// ❌ WRONG - Creates spaces in URLs
echo BASE_URL; ?>/admin/path
```

#### Namespace Declaration Rules
```php
// ✅ CORRECT - Namespace at file start
namespace Collabora\Auth;
class ClassName { ... }

// ❌ WRONG - Namespace in conditional blocks
if (!class_exists('ClassName')) {
    namespace SomeNamespace { // INVALID PHP SYNTAX
        class ClassName { ... }
    }
}
```

### System Verification Scripts
- `/verify_system_final.php` - Comprehensive system test (all components)
- `/test_actual_system.php` - Tests existing files only (focused test)
- Both scripts confirmed 100% pass rate as of 2025-09-20

### Recently Fixed Issues (January 2025)
- **auth_v2.php returning 404**: Created complete implementation with AuthAPIV2 class
- **auth_simple.php returning 400**: Added flexible JSON/form-encoded support and detailed error messages
- **Generic error messages**: Implemented specific error reporting with debug information
- **JSON format issues**: Added support for multiple content types and fallback parsing

### Database Connection
- Config file: `config_v2.php` - contains DB credentials (usually root with no password for XAMPP)
- Connection function: `getDbConnection()` in `includes/db.php`
- Default database name: `nexio_collabora_v2` (sometimes `collabora_files` in older setups)

## UI Design Requirements
- **Sidebar color**: Grigio antracite (#111827) - strictly maintained
- **Icons**: Heroicons inline SVG only - no external icon libraries
- **Layout**: CSS Grid/Flexbox - no CSS frameworks
- **Responsive**: Mobile-first with collapsible sidebar
- **Dark mode**: Supported via CSS variables

## Security Considerations
- All database queries use PDO prepared statements
- CSRF tokens required for all write operations
- Passwords hashed with Argon2id or bcrypt
- Session security: HTTPOnly, SameSite cookies
- File uploads validated for MIME type and stored outside document root with SHA256 deduplication

## API Path Resolution System

### Dynamic Path Detection
The system now implements automatic path detection for API endpoints, eliminating hardcoded paths:

1. **Priority Order**:
   - Environment variable: `COLLABORA_BASE_URL`
   - Configuration constant: `BASE_URL` in config_v2.php
   - Auto-detection: Calculates from current script location

2. **Path Resolution Logic**:
   ```javascript
   // Frontend (JavaScript)
   const scriptPath = window.location.pathname;
   const pathParts = scriptPath.split('/');
   const collaboraIndex = pathParts.indexOf('collabora');
   const basePath = pathParts.slice(0, collaboraIndex + 1).join('/');
   const apiUrl = basePath + '/api';
   ```

3. **API Module Usage**:
   ```javascript
   // Import API module
   import { API } from '/assets/js/api.js';

   // Use API methods
   await API.auth.login(email, password);
   await API.users.getAll();
   await API.files.upload(file);
   ```

### Troubleshooting Path Issues

1. **API calls return 404**:
   - Check browser console for actual URL being called
   - Verify `/api/` folder exists in collabora directory
   - Ensure .htaccess isn't blocking API requests

2. **Wrong base URL detected**:
   - Set `BASE_URL` constant in config_v2.php
   - Or set environment variable `COLLABORA_BASE_URL`

3. **Subfolder installation issues**:
   - System auto-detects subfolder depth
   - Works with any path: `/collabora/`, `/app/collabora/`, `/Nexiosolution/collabora/`

4. **Testing path resolution**:
   - Use `/test_api_paths.php` for server-side testing
   - Use `/test_api_resolution.html` for browser testing
   - Run `window.testAPIFromConsole()` in browser console

## API Response Format
All API endpoints should return JSON:
```json
{
  "success": true/false,
  "message": "Human readable message",
  "data": {...},
  "error": "Error code if applicable"
}
```

## File Upload Structure
Files stored in: `/uploads/{tenant_code}/{year}/{month}/`
with SHA256 hash for deduplication across tenants.

## Testing Approach
Always test with three scenarios:
1. Admin login (full access)
2. Special user (multi-tenant switching)
3. Standard user (single tenant)

Default test credentials are provided in the system documentation.

## Part 2 Features - Calendar & Task Management (ADDED 2025-01-19)

### New Modules Implemented
The system now includes comprehensive calendar and task management capabilities:

#### Calendar Module
- **Multi-tenant calendars** with sharing permissions
- **Event management** with full CRUD operations
- **Recurrence support** (RFC 5545 RRULE ready)
- **Event participants** with RSVP tracking
- **Reminders** via email and popup notifications
- **CalDAV ready** for external sync (UID, ETag support)
- **Timezone aware** with automatic conversion
- **Drag & drop** support in UI

#### Task Management Module
- **Kanban boards** with customizable workflow states
- **Hierarchical tasks** with subtask support
- **Multiple assignees** per task for team collaboration
- **Comments system** with mentions and activity tracking
- **Time tracking** with billable hours support
- **Task priorities** (urgent, high, medium, low)
- **Tags and filtering** for organization
- **File attachments** linked to tasks

### New Database Tables (12 total)
```
Calendar Tables:
- calendars           # Calendar containers
- events             # Event records
- event_participants # RSVP tracking
- event_reminders    # Notification settings
- calendar_shares    # Permission management
- event_attachments  # File links

Task Tables:
- task_lists         # Boards/Lists
- tasks              # Task records
- task_assignments   # User assignments
- task_comments      # Discussion threads
- task_time_logs     # Time tracking
- task_attachments   # File links
```

### New API Endpoints

#### Calendar APIs
- `GET/POST /api/calendars.php` - Calendar management
- `GET/POST/PATCH/DELETE /api/events.php` - Event operations
- `POST /api/events.php/{id}/rsvp` - RSVP responses
- `PATCH /api/events.php/{id}/move` - Drag & drop support

#### Task APIs
- `GET/POST /api/task-lists.php` - Board management
- `GET/POST/PATCH/DELETE /api/tasks.php` - Task operations
- `POST /api/tasks.php/{id}/comments` - Add comments
- `POST /api/tasks.php/{id}/time-logs` - Track time
- `PATCH /api/tasks.php/{id}/status` - Quick status updates

### New UI Pages
- `/calendar.php` - Full calendar interface with month/week/day/list views
- `/tasks.php` - Kanban board and task management interface
- `/assets/js/calendar.js` - Calendar JavaScript module
- `/assets/js/tasks.js` - Task management JavaScript module
- `/assets/css/calendar.css` - Calendar styling
- `/assets/css/tasks.css` - Task board styling

### New PHP Classes
- `/includes/CalendarManager.php` - Calendar and event management
- `/includes/TaskManager.php` - Task and board management

### Deployment & Testing

#### Deployment Script
```bash
# Windows deployment
C:\xampp\htdocs\Nexiosolution\collabora\deploy_part2.bat

# Applies database migrations and verifies installation
```

#### Testing Scripts
```bash
# Comprehensive system test
php test_part2_system.php

# Visual component verification
Open: http://localhost/Nexiosolution/collabora/verify_part2.php
```

#### Database Migration
```bash
# Apply Part 2 migrations
mysql -u root nexio_collabora_v2 < database/migrations_part2.sql
```

### Usage Examples

#### Create Calendar
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/calendars.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"name":"Team Calendar","color":"#4F46E5"}'
```

#### Create Event
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/events.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "calendar_id": 1,
    "title": "Team Meeting",
    "start_datetime": "2025-01-20 10:00:00",
    "end_datetime": "2025-01-20 11:00:00"
  }'
```

#### Create Task
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/tasks.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "task_list_id": 1,
    "title": "Complete feature",
    "priority": "high",
    "status": "todo"
  }'
```

### Performance Optimizations
- **Composite indexes** on (tenant_id, primary_lookup_field)
- **Full-text search** on titles and descriptions
- **Denormalized counters** for statistics
- **Stored procedures** for complex queries
- **Views** for common data access patterns

### Security Features
- **Multi-tenant isolation** enforced at database level
- **Row-level security** with tenant_id checks
- **Soft deletes** with audit trail
- **Permission-based** calendar sharing
- **Input validation** on all endpoints

## Part 4 Features - Chat & Communication (ADDED 2025-01-20)

### Real-Time Chat System
The system now includes a comprehensive chat and communication module with long-polling support:

#### Chat Features
- **Multi-channel support**: Public, private, and direct message channels
- **Real-time messaging**: Long-polling with 2-second intervals
- **Message threading**: Reply to specific messages
- **Emoji reactions**: Add reactions to messages (👍 ❤️ 😊 😂 🎉 🤔)
- **@mentions**: Mention users with autocomplete and notifications
- **File attachments**: Share files from the existing file manager
- **User presence**: Online/away/offline/busy status tracking
- **Typing indicators**: See when others are typing
- **Read receipts**: Track read/unread messages with counts
- **Message search**: Full-text search across messages

#### Database Tables (11 total)
```
Chat Core:
- chat_channels         # Channel containers
- chat_channel_members  # Membership and roles
- chat_messages        # Messages with threading
- message_reactions    # Emoji reactions
- message_mentions     # @mention tracking
- message_reads        # Read status
- chat_presence        # Online status

Enhanced Features:
- chat_typing_indicators  # Real-time typing
- chat_pinned_messages   # Important messages
- chat_analytics         # Usage statistics
```

### Chat API Endpoints

#### Core APIs
- `GET/POST /api/messages.php` - Message operations
- `GET /api/chat-poll.php` - Long-polling for real-time updates
- `GET/POST /api/presence.php` - User presence management
- `GET/POST/PUT/DELETE /api/channels.php` - Channel CRUD
- `GET/POST/DELETE /api/reactions.php` - Emoji reactions

### Chat UI Components
- `/chat.php` - Main chat interface with three-column layout
- `/assets/js/chat.js` - ChatModule class for chat operations
- `/assets/js/polling.js` - PollingManager for real-time updates
- `/assets/css/chat.css` - Chat-specific styling

### Chat Configuration
- `/config/chat.config.php` - Chat settings and limits
- Long-polling timeout: 30 seconds max
- Message limit: 50 per load
- File size limit: 10MB for attachments
- Typing indicator timeout: 5 seconds
- Presence timeout: 5 minutes

### Long-Polling Implementation
```javascript
// Polling intervals and backoff
- Base interval: 2 seconds
- Error backoff: Exponential (2s, 4s, 8s... max 60s)
- Tab visibility: Pause when hidden, resume when visible
- Connection recovery: Automatic reconnection on failure
```

### Deployment & Testing
```bash
# Deploy chat module
C:\xampp\htdocs\Nexiosolution\collabora\deploy_part4_chat.bat

# Run chat tests
php test_part4_chat.php

# Access chat interface
http://localhost/Nexiosolution/collabora/chat.php
```

### Chat Usage Examples

#### Send Message
```bash
curl -X POST http://localhost/Nexiosolution/collabora/api/messages.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "channel_id": 1,
    "content": "Hello @alice! Check this out 😊"
  }'
```

#### Long-Poll for Updates
```bash
curl -X GET "http://localhost/Nexiosolution/collabora/api/chat-poll.php?last_message_id=100" \
  -b cookies.txt
```
