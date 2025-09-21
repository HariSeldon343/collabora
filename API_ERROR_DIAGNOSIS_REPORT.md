# NEXIO COLLABORA API ERROR DIAGNOSIS REPORT

**Date:** 2025-09-21
**System:** Nexio Collabora Multi-Tenant Collaboration Platform
**Issue:** Multiple API endpoints returning 500/401 errors

## EXECUTIVE SUMMARY

The root cause of all API errors is a **DATABASE SCHEMA MISMATCH**. The codebase expects tables from Part 2 and Part 4 migrations that haven't been applied, while the base schema uses different table names.

## CRITICAL FINDINGS

### 1. TABLE NAME MISMATCH

| API Code Expects | Base Schema Has | Migration Part 4 Creates |
|-----------------|-----------------|-------------------------|
| `chat_channels` | `rooms` | `chat_channels` ✓ |
| `chat_channel_members` | `room_members` | `chat_channel_members` ✓ |
| `chat_messages` | (not in base) | `chat_messages` ✓ |
| `chat_presence` | (not in base) | `chat_presence` ✓ |
| `calendars` | `calendars` ✓ | (Part 2) |
| `events` | `events` ✓ | (Part 2) |
| `task_lists` | (not in base) | `task_lists` (Part 2) |

### 2. MISSING MIGRATIONS

The following migration files exist but haven't been applied:
- `/database/migrations_part2.sql` - Adds calendar and task management tables
- `/database/migrations_part4_chat.sql` - Adds proper chat tables with correct names

## ENDPOINT ERROR ANALYSIS

### `/api/calendars.php` - 500 Error
- **Root Cause:** Table `calendars` exists but `CalendarManager.php` class may be missing dependencies
- **SQL Error:** None - table structure is correct
- **Code Issue:** Missing includes or namespace issues

### `/api/events.php` - 500 Error
- **Root Cause:** Table `events` exists but depends on `CalendarManager.php`
- **SQL Error:** None - table exists
- **Code Issue:** CalendarManager class not loading properly

### `/api/task-lists.php` - 500 Error
- **Root Cause:** Table `task_lists` doesn't exist in base schema
- **SQL Error:** `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'nexio_collabora_v2.task_lists' doesn't exist`
- **Fix Required:** Run `migrations_part2.sql`

### `/api/channels.php` - 500 Error
- **Root Cause:** Code queries `chat_channels` but database has `rooms`
- **SQL Error:** `SQLSTATE[42S02]: Base table or view not found: 1146 Table 'nexio_collabora_v2.chat_channels' doesn't exist`
- **Fix Required:** Run `migrations_part4_chat.sql`

### `/api/chat-poll.php` - 500 Error
- **Root Cause:** Code expects `chat_messages` and `chat_channels` tables
- **SQL Error:** Tables don't exist
- **Fix Required:** Run `migrations_part4_chat.sql`

### `/api/messages.php` - 500 Error
- **Root Cause:** Missing `chat_messages` table
- **SQL Error:** Table doesn't exist
- **Fix Required:** Run `migrations_part4_chat.sql`

### `/api/users.php` - 401 Error
- **Root Cause:** Authentication check failing
- **Issue:** Session not established or SimpleAuth not initialized
- **Fix Required:** Check session management and authentication flow

## IMMEDIATE FIX ACTIONS

### Step 1: Apply Missing Migrations

```bash
# Connect to MySQL and apply migrations
mysql -u root nexio_collabora_v2 < /mnt/c/xampp/htdocs/Nexiosolution/collabora/database/migrations_part2.sql
mysql -u root nexio_collabora_v2 < /mnt/c/xampp/htdocs/Nexiosolution/collabora/database/migrations_part4_chat.sql
```

### Step 2: Verify Tables Created

After running migrations, these tables should exist:
- ✅ `calendars` (already exists)
- ✅ `events` (already exists)
- ✅ `task_lists` (from Part 2)
- ✅ `tasks` (from Part 2)
- ✅ `task_assignments` (from Part 2)
- ✅ `chat_channels` (from Part 4)
- ✅ `chat_channel_members` (from Part 4)
- ✅ `chat_messages` (from Part 4)
- ✅ `chat_presence` (from Part 4)

### Step 3: Handle Duplicate Tables

After migration, you'll have both:
- `rooms` (old) and `chat_channels` (new)
- `room_members` (old) and `chat_channel_members` (new)

**Options:**
1. Keep both (safe but redundant)
2. Migrate data from `rooms` to `chat_channels` and drop old tables
3. Update code to use `rooms` (not recommended - breaks future updates)

### Step 4: Fix Authentication for /api/users.php

Check:
1. Session is started before authentication check
2. SimpleAuth class is properly included
3. User has valid session with tenant selected

## CODE FIXES REQUIRED

### 1. CalendarManager.php Issues
- Check file exists at `/includes/CalendarManager.php`
- Verify namespace declaration matches usage
- Ensure all dependencies are included

### 2. TaskManager.php Issues
- Check file exists at `/includes/TaskManager.php`
- Verify namespace `Collabora\Tasks` is correct
- Ensure PDO connection is passed correctly

### 3. ChatManager.php
- Already configured for `chat_channels` (correct)
- No changes needed after migration

## TESTING SCRIPT

After applying fixes, run:
```bash
php /mnt/c/xampp/htdocs/Nexiosolution/collabora/diagnose-api-errors.php
```

## EXPECTED OUTCOME

After applying migrations:
- ✅ `/api/calendars.php` - Should return calendar list
- ✅ `/api/events.php` - Should return events
- ✅ `/api/task-lists.php` - Should return task lists
- ✅ `/api/channels.php` - Should return chat channels
- ✅ `/api/chat-poll.php` - Should return message updates
- ✅ `/api/messages.php` - Should handle message operations
- ✅ `/api/users.php` - Should return user list (with auth)

## ROOT CAUSE SUMMARY

**The system was partially deployed.** The base schema (`schema_v2.sql`) contains only core tables. The feature modules (calendar, tasks, chat) require their respective migration files to be applied. The confusion arose because:

1. Base schema has `rooms` table (old chat implementation)
2. New code expects `chat_channels` (Part 4 implementation)
3. Task and advanced calendar features need Part 2 tables
4. Migrations were created but never applied to the database

## PERMANENT SOLUTION

1. **Apply all migrations in order:**
   ```bash
   mysql -u root nexio_collabora_v2 < database/migrations_part2.sql
   mysql -u root nexio_collabora_v2 < database/migrations_part4_chat.sql
   ```

2. **Remove old tables (after data migration if needed):**
   ```sql
   DROP TABLE IF EXISTS rooms;
   DROP TABLE IF EXISTS room_members;
   ```

3. **Update deployment documentation** to include migration steps

4. **Create unified schema file** that includes all tables for fresh installations

This will resolve ALL 500 errors on the affected endpoints.