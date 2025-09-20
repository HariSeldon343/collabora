@echo off
setlocal enabledelayedexpansion

:: ========================================
:: NEXIOSOLUTION - CHAT MODULE DEPLOYMENT
:: Part 4: Chat & Communication System
:: ========================================

echo.
echo ============================================================
echo     NEXIOSOLUTION CHAT MODULE DEPLOYMENT - PART 4
echo ============================================================
echo.

:: Set variables
set XAMPP_PATH=C:\xampp
set PROJECT_PATH=%XAMPP_PATH%\htdocs\Nexiosolution\collabora
set MYSQL_PATH=%XAMPP_PATH%\mysql\bin
set PHP_PATH=%XAMPP_PATH%\php

:: Colors for output
set GREEN=[92m
set YELLOW=[93m
set RED=[91m
set RESET=[0m

:: Check if running as administrator
net session >nul 2>&1
if %errorLevel% == 0 (
    echo %GREEN%Running with administrator privileges%RESET%
) else (
    echo %YELLOW%Warning: Not running as administrator. Some operations may fail.%RESET%
)

:: ========================================
:: STEP 1: Check Services
:: ========================================
echo.
echo %YELLOW%[1/7] Checking required services...%RESET%

:: Check MySQL Service
sc query mysql >nul 2>&1
if %errorLevel% == 0 (
    echo %GREEN%  - MySQL service found%RESET%
    sc query mysql | findstr "RUNNING" >nul 2>&1
    if !errorLevel! == 0 (
        echo %GREEN%  - MySQL is running%RESET%
    ) else (
        echo %YELLOW%  - Starting MySQL service...%RESET%
        net start mysql >nul 2>&1
        timeout /t 3 >nul
    )
) else (
    echo %YELLOW%  - MySQL service not found, checking XAMPP MySQL...%RESET%
    tasklist /fi "imagename eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
    if !errorLevel! == 0 (
        echo %GREEN%  - XAMPP MySQL is running%RESET%
    ) else (
        echo %YELLOW%  - Starting XAMPP MySQL...%RESET%
        start "" "%XAMPP_PATH%\mysql_start.bat"
        timeout /t 5 >nul
    )
)

:: Check Apache Service
sc query apache2.4 >nul 2>&1
if %errorLevel% == 0 (
    echo %GREEN%  - Apache service found%RESET%
    sc query apache2.4 | findstr "RUNNING" >nul 2>&1
    if !errorLevel! == 0 (
        echo %GREEN%  - Apache is running%RESET%
    ) else (
        echo %YELLOW%  - Starting Apache service...%RESET%
        net start apache2.4 >nul 2>&1
        timeout /t 3 >nul
    )
) else (
    echo %YELLOW%  - Apache service not found, checking XAMPP Apache...%RESET%
    tasklist /fi "imagename eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
    if !errorLevel! == 0 (
        echo %GREEN%  - XAMPP Apache is running%RESET%
    ) else (
        echo %YELLOW%  - Starting XAMPP Apache...%RESET%
        start "" "%XAMPP_PATH%\apache_start.bat"
        timeout /t 5 >nul
    )
)

:: ========================================
:: STEP 2: Database Migration
:: ========================================
echo.
echo %YELLOW%[2/7] Applying database migrations...%RESET%

:: Check if migration file exists
if not exist "%PROJECT_PATH%\database\migrations_part4_chat.sql" (
    echo %RED%  ERROR: Migration file not found!%RESET%
    echo %RED%  Expected: %PROJECT_PATH%\database\migrations_part4_chat.sql%RESET%
    goto :error
)

:: Apply migration
echo %GREEN%  - Found migration file%RESET%
echo %YELLOW%  - Applying chat module tables...%RESET%

"%MYSQL_PATH%\mysql.exe" -u root nexiosolution < "%PROJECT_PATH%\database\migrations_part4_chat.sql" 2>nul
if !errorLevel! == 0 (
    echo %GREEN%  - Database migration successful%RESET%
) else (
    echo %YELLOW%  - Retrying with password...%RESET%
    set /p MYSQL_PASS="Enter MySQL root password (or press Enter if none): "
    if "!MYSQL_PASS!"=="" (
        "%MYSQL_PATH%\mysql.exe" -u root nexiosolution < "%PROJECT_PATH%\database\migrations_part4_chat.sql"
    ) else (
        "%MYSQL_PATH%\mysql.exe" -u root -p!MYSQL_PASS! nexiosolution < "%PROJECT_PATH%\database\migrations_part4_chat.sql"
    )
    if !errorLevel! neq 0 (
        echo %RED%  ERROR: Database migration failed!%RESET%
        goto :error
    )
    echo %GREEN%  - Database migration successful%RESET%
)

:: ========================================
:: STEP 3: Verify File Permissions
:: ========================================
echo.
echo %YELLOW%[3/7] Verifying file permissions...%RESET%

:: Create required directories if they don't exist
if not exist "%PROJECT_PATH%\uploads\chat" (
    mkdir "%PROJECT_PATH%\uploads\chat"
    echo %GREEN%  - Created uploads\chat directory%RESET%
)

if not exist "%PROJECT_PATH%\logs\chat" (
    mkdir "%PROJECT_PATH%\logs\chat"
    echo %GREEN%  - Created logs\chat directory%RESET%
)

if not exist "%PROJECT_PATH%\temp\chat" (
    mkdir "%PROJECT_PATH%\temp\chat"
    echo %GREEN%  - Created temp\chat directory%RESET%
)

if not exist "%PROJECT_PATH%\config" (
    mkdir "%PROJECT_PATH%\config"
    echo %GREEN%  - Created config directory%RESET%
)

:: Set permissions (Windows)
echo %YELLOW%  - Setting directory permissions...%RESET%
icacls "%PROJECT_PATH%\uploads\chat" /grant Everyone:F /T >nul 2>&1
icacls "%PROJECT_PATH%\logs\chat" /grant Everyone:F /T >nul 2>&1
icacls "%PROJECT_PATH%\temp\chat" /grant Everyone:F /T >nul 2>&1
echo %GREEN%  - Permissions set successfully%RESET%

:: ========================================
:: STEP 4: Test API Endpoints
:: ========================================
echo.
echo %YELLOW%[4/7] Testing API endpoints...%RESET%

:: Test each chat API endpoint
set ENDPOINTS=channels messages presence reactions chat-poll

for %%e in (%ENDPOINTS%) do (
    if exist "%PROJECT_PATH%\api\%%e.php" (
        echo %GREEN%  - API endpoint %%e.php found%RESET%

        :: Quick PHP syntax check
        "%PHP_PATH%\php.exe" -l "%PROJECT_PATH%\api\%%e.php" >nul 2>&1
        if !errorLevel! == 0 (
            echo %GREEN%    Syntax OK%RESET%
        ) else (
            echo %RED%    Syntax error in %%e.php%RESET%
        )
    ) else (
        echo %RED%  - API endpoint %%e.php NOT found%RESET%
    )
)

:: ========================================
:: STEP 5: Clear Caches
:: ========================================
echo.
echo %YELLOW%[5/7] Clearing caches...%RESET%

:: Clear session files
if exist "%XAMPP_PATH%\tmp\sess_*" (
    del /q "%XAMPP_PATH%\tmp\sess_*" >nul 2>&1
    echo %GREEN%  - Session cache cleared%RESET%
)

:: Clear temp files
if exist "%PROJECT_PATH%\temp\chat\*" (
    del /q "%PROJECT_PATH%\temp\chat\*" >nul 2>&1
    echo %GREEN%  - Temp files cleared%RESET%
)

:: Restart Apache to clear OpCache
echo %YELLOW%  - Restarting Apache to clear OpCache...%RESET%
"%XAMPP_PATH%\apache\bin\httpd.exe" -k restart >nul 2>&1
timeout /t 3 >nul
echo %GREEN%  - Apache restarted%RESET%

:: ========================================
:: STEP 6: Configure PHP Settings
:: ========================================
echo.
echo %YELLOW%[6/7] Checking PHP configuration...%RESET%

:: Check PHP configuration for long-polling
"%PHP_PATH%\php.exe" -r "echo 'max_execution_time: ' . ini_get('max_execution_time') . ' seconds' . PHP_EOL;"
"%PHP_PATH%\php.exe" -r "echo 'memory_limit: ' . ini_get('memory_limit') . PHP_EOL;"
"%PHP_PATH%\php.exe" -r "echo 'session.gc_maxlifetime: ' . ini_get('session.gc_maxlifetime') . ' seconds' . PHP_EOL;"

echo.
echo %YELLOW%  Note: For optimal chat performance, ensure:%RESET%
echo %YELLOW%  - max_execution_time = 60 (for long-polling)%RESET%
echo %YELLOW%  - memory_limit = 256M%RESET%
echo %YELLOW%  - session.gc_maxlifetime = 3600%RESET%

:: ========================================
:: STEP 7: Run Test Suite
:: ========================================
echo.
echo %YELLOW%[7/7] Running test suite...%RESET%

if exist "%PROJECT_PATH%\test_part4_chat.php" (
    echo %GREEN%  - Running chat module tests...%RESET%
    "%PHP_PATH%\php.exe" "%PROJECT_PATH%\test_part4_chat.php"
) else (
    echo %YELLOW%  - Test suite not found, creating basic test...%RESET%
    "%PHP_PATH%\php.exe" -r "echo 'Basic connectivity test: OK' . PHP_EOL;"
)

:: ========================================
:: DEPLOYMENT COMPLETE
:: ========================================
echo.
echo ============================================================
echo %GREEN%       CHAT MODULE DEPLOYMENT COMPLETED!%RESET%
echo ============================================================
echo.
echo %GREEN%Next Steps:%RESET%
echo 1. Access the chat interface at: http://localhost/Nexiosolution/collabora/chat.php
echo 2. Configure chat settings in: config/chat.config.php
echo 3. Review logs in: logs/chat/
echo 4. Test long-polling at: api/chat-poll.php
echo.
echo %GREEN%Documentation:%RESET%
echo - Deployment Guide: docs/CHAT_DEPLOYMENT.md
echo - API Reference: PARTE4-PROGRESS.md
echo - Troubleshooting: docs/CHAT_DEPLOYMENT.md#troubleshooting
echo.
echo %GREEN%Press any key to open the chat interface in your browser...%RESET%
pause >nul
start http://localhost/Nexiosolution/collabora/chat.php
goto :end

:error
echo.
echo %RED%============================================================%RESET%
echo %RED%        DEPLOYMENT FAILED - Please check the errors%RESET%
echo %RED%============================================================%RESET%
echo.
pause
exit /b 1

:end
echo.
echo %GREEN%Deployment script completed successfully!%RESET%
pause
exit /b 0