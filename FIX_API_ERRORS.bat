@echo off
REM =========================================
REM NEXIO COLLABORA - API ERROR FIX SCRIPT
REM Applies missing migrations to fix all 500 errors
REM =========================================

echo.
echo =====================================
echo NEXIO COLLABORA API ERROR FIX
echo =====================================
echo.

set MYSQL_PATH=C:\xampp\mysql\bin
set DB_NAME=nexio_collabora_v2
set DB_USER=root
set PROJECT_PATH=C:\xampp\htdocs\Nexiosolution\collabora

echo [1/5] Checking MySQL availability...
"%MYSQL_PATH%\mysql" -u%DB_USER% -e "SELECT VERSION();" >nul 2>&1
if errorlevel 1 (
    echo ERROR: MySQL is not running or not accessible
    echo Please start XAMPP MySQL service first
    pause
    exit /b 1
)
echo MySQL is running

echo.
echo [2/5] Backing up current database...
"%MYSQL_PATH%\mysqldump" -u%DB_USER% %DB_NAME% > "%PROJECT_PATH%\database\backup_before_fix_%date:~-4%%date:~3,2%%date:~0,2%.sql"
echo Backup created

echo.
echo [3/5] Applying Part 2 migrations (Calendars and Tasks)...
"%MYSQL_PATH%\mysql" -u%DB_USER% %DB_NAME% < "%PROJECT_PATH%\database\migrations_part2.sql"
if errorlevel 1 (
    echo ERROR: Failed to apply Part 2 migrations
    echo Check the migration file for errors
    pause
    exit /b 1
)
echo Part 2 migrations applied successfully

echo.
echo [4/5] Applying Part 4 migrations (Chat System)...
"%MYSQL_PATH%\mysql" -u%DB_USER% %DB_NAME% < "%PROJECT_PATH%\database\migrations_part4_chat.sql"
if errorlevel 1 (
    echo ERROR: Failed to apply Part 4 migrations
    echo Check the migration file for errors
    pause
    exit /b 1
)
echo Part 4 migrations applied successfully

echo.
echo [5/5] Verifying tables...
echo.
echo Checking for required tables:
"%MYSQL_PATH%\mysql" -u%DB_USER% %DB_NAME% -e "SHOW TABLES LIKE 'task_lists';" 2>nul | findstr "task_lists" >nul
if errorlevel 1 (
    echo [ ] task_lists - MISSING
) else (
    echo [X] task_lists - OK
)

"%MYSQL_PATH%\mysql" -u%DB_USER% %DB_NAME% -e "SHOW TABLES LIKE 'chat_channels';" 2>nul | findstr "chat_channels" >nul
if errorlevel 1 (
    echo [ ] chat_channels - MISSING
) else (
    echo [X] chat_channels - OK
)

"%MYSQL_PATH%\mysql" -u%DB_USER% %DB_NAME% -e "SHOW TABLES LIKE 'chat_messages';" 2>nul | findstr "chat_messages" >nul
if errorlevel 1 (
    echo [ ] chat_messages - MISSING
) else (
    echo [X] chat_messages - OK
)

"%MYSQL_PATH%\mysql" -u%DB_USER% %DB_NAME% -e "SHOW TABLES LIKE 'calendars';" 2>nul | findstr "calendars" >nul
if errorlevel 1 (
    echo [ ] calendars - MISSING
) else (
    echo [X] calendars - OK
)

"%MYSQL_PATH%\mysql" -u%DB_USER% %DB_NAME% -e "SHOW TABLES LIKE 'events';" 2>nul | findstr "events" >nul
if errorlevel 1 (
    echo [ ] events - MISSING
) else (
    echo [X] events - OK
)

echo.
echo =====================================
echo FIX COMPLETE!
echo =====================================
echo.
echo All migrations have been applied.
echo.
echo You can now test the APIs:
echo - http://localhost/Nexiosolution/collabora/api/calendars.php
echo - http://localhost/Nexiosolution/collabora/api/events.php
echo - http://localhost/Nexiosolution/collabora/api/task-lists.php
echo - http://localhost/Nexiosolution/collabora/api/channels.php
echo - http://localhost/Nexiosolution/collabora/api/chat-poll.php
echo - http://localhost/Nexiosolution/collabora/api/messages.php
echo.
echo To run diagnostics: php diagnose-api-errors.php
echo.
pause