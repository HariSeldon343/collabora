# Database Schema Alignment Solution

## Problem Summary
The application code expects certain table names (e.g., `chat_channels`, `chat_messages`) but the database might have different table names (e.g., `rooms`, `messages`) or missing tables entirely. This causes API failures and 500 errors.

## Solution Overview
We've created a comprehensive migration system that:
1. **Creates all missing tables** with the exact names the code expects
2. **Preserves existing data** (non-destructive migrations)
3. **Adds proper indexes and foreign keys** for performance
4. **Implements multi-tenant structure** with `tenant_id` columns

## Files Created

### 1. `/database/fix_schema_alignment.sql`
- **Purpose**: Main migration script with all required tables
- **Features**:
  - Creates 20+ tables for chat, calendar, and task modules
  - Uses `CREATE TABLE IF NOT EXISTS` (idempotent/safe to run multiple times)
  - Includes proper indexes, foreign keys, and constraints
  - Adds views and stored procedures for common operations

### 2. `/apply_schema_fixes.php`
- **Purpose**: PHP script to apply the migration safely
- **Features**:
  - Creates database backup before migration
  - Executes migration with error handling
  - Verifies all tables exist after migration
  - Provides detailed logging and statistics

### 3. `/verify_schema_alignment.php`
- **Purpose**: Verification script to check schema alignment
- **Features**:
  - Checks all required tables exist
  - Verifies column structure
  - Tests foreign key constraints
  - Checks views and stored procedures
  - Provides pass/fail summary

## How to Apply the Fix

### Step 1: Run the Migration
```bash
# Windows Command Prompt
cd C:\xampp\htdocs\Nexiosolution\collabora
php apply_schema_fixes.php

# Or if XAMPP PHP is not in PATH:
C:\xampp\php\php.exe apply_schema_fixes.php
```

Expected output:
```
[12:34:56] ℹ Starting database migration process...
[12:34:56] ✓ Database connection established
[12:34:56] ℹ Creating database backup...
[12:34:57] ✓ Backup created: database/backups/backup_2025-01-21_123456.sql
[12:34:57] ℹ Executing migration script...
[12:34:58] ✓ Executed 50 SQL statements
[12:34:58] ✓ All required tables exist!
```

### Step 2: Verify the Schema
```bash
php verify_schema_alignment.php
```

Expected output:
```
✓ DATABASE SCHEMA IS PROPERLY ALIGNED!
All required tables exist with correct structure
The system is ready for use
```

### Step 3: Test the APIs
After migration, test that APIs are working:

```bash
# Test Chat API
curl -X GET http://localhost/Nexiosolution/collabora/api/messages.php

# Test Calendar API
curl -X GET http://localhost/Nexiosolution/collabora/api/calendars.php

# Test Tasks API
curl -X GET http://localhost/Nexiosolution/collabora/api/tasks.php
```

## Tables Created

### Chat Module (9 tables)
- `chat_channels` - Channel containers
- `chat_channel_members` - Membership tracking
- `chat_messages` - Message storage
- `message_reactions` - Emoji reactions
- `message_mentions` - @mention tracking
- `message_reads` - Read status
- `chat_presence` - Online status
- `chat_typing_indicators` - Typing status
- `chat_pinned_messages` - Pinned messages

### Calendar Module (4 tables)
- `calendars` - Calendar containers
- `events` - Event records
- `event_participants` - RSVP tracking
- `calendar_shares` - Sharing permissions

### Task Module (3 tables)
- `task_lists` - Task boards/lists
- `tasks` - Task records
- `task_assignments` - User assignments

## Key Design Decisions

### 1. Table Naming Convention
- Used exact names the code expects (e.g., `chat_channels` not `channels`)
- Consistent plural naming for collection tables
- Descriptive names for junction tables

### 2. Multi-Tenant Architecture
- Every primary table has `tenant_id` column
- Composite indexes on (tenant_id, primary_lookup_field)
- Foreign key cascades ensure data integrity

### 3. Soft Deletes
- Most tables include `deleted_at` timestamp
- Allows data recovery and audit trails
- Queries filter out soft-deleted records

### 4. Performance Optimizations
- Strategic indexes on frequently queried columns
- Full-text indexes on searchable content
- Views for complex queries
- Stored procedures for common operations

## Troubleshooting

### Issue: Migration fails with "Access denied"
**Solution**: Check MySQL credentials in `apply_schema_fixes.php`:
```php
$dbConfig = [
    'host' => 'localhost',
    'name' => 'nexio_collabora_v2',
    'user' => 'root',  // <- Check this
    'pass' => '',      // <- And this
];
```

### Issue: "Database not found" error
**Solution**: The script will create the database if it doesn't exist. Ensure MySQL is running.

### Issue: Some tables already exist
**Solution**: This is fine! The migration uses `CREATE TABLE IF NOT EXISTS` so existing tables are preserved.

### Issue: Foreign key constraint errors
**Solution**: The migration temporarily disables foreign key checks. If errors persist, check that referenced tables (tenants, users) exist.

## Rollback Procedure

If you need to rollback:

1. **Restore from backup**:
```bash
mysql -u root nexio_collabora_v2 < database/backups/backup_[timestamp].sql
```

2. **Or drop specific tables**:
```sql
DROP TABLE IF EXISTS chat_messages, chat_channel_members, chat_channels;
```

## Alternative Approach: Update Code Instead

If you prefer to update the code to match existing database tables:

1. **Find all references to table names**:
```bash
grep -r "chat_channels" --include="*.php" .
grep -r "chat_messages" --include="*.php" .
```

2. **Update table names in code**:
- Change `chat_channels` to `rooms`
- Change `chat_messages` to `messages`
- Change `chat_channel_members` to `room_members`

3. **Update API queries**:
Edit files in `/api/` folder to use existing table names.

**Note**: This approach requires updating many files and is more error-prone than the database migration approach.

## Benefits of This Solution

1. **Non-destructive**: Doesn't delete or modify existing data
2. **Idempotent**: Safe to run multiple times
3. **Comprehensive**: Fixes all modules (chat, calendar, tasks)
4. **Future-proof**: Proper structure for scaling
5. **Performance-ready**: Includes indexes and optimizations
6. **Multi-tenant ready**: Proper isolation between tenants

## Next Steps After Migration

1. **Test all modules**:
   - Chat: http://localhost/Nexiosolution/collabora/chat.php
   - Calendar: http://localhost/Nexiosolution/collabora/calendar.php
   - Tasks: http://localhost/Nexiosolution/collabora/tasks.php

2. **Create initial data**:
   - Create a test channel
   - Add some calendar events
   - Create sample tasks

3. **Monitor performance**:
   - Check query execution times
   - Monitor table sizes
   - Review slow query log

## Support

If you encounter issues:

1. Check the migration output for specific errors
2. Run the verification script for detailed diagnostics
3. Review MySQL error log: `C:\xampp\mysql\data\[hostname].err`
4. Ensure all prerequisite tables (tenants, users) exist

## Conclusion

This migration approach ensures the database schema perfectly aligns with what the application code expects, resolving all API errors and enabling full functionality of the chat, calendar, and task modules.