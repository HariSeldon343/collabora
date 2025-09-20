@echo off
echo ==================================================
echo Calendar and Task Management Migration
echo ==================================================
echo.

set MYSQL_PATH=C:\xampp\mysql\bin
set MYSQL_USER=root
set MYSQL_DB=collabora_files
set MIGRATION_FILE=database\migrations_part2.sql

echo Applying migration to database: %MYSQL_DB%
echo.

"%MYSQL_PATH%\mysql.exe" -u %MYSQL_USER% %MYSQL_DB% < %MIGRATION_FILE%

if %errorlevel% == 0 (
    echo.
    echo Migration applied successfully!
    echo.
    echo Verifying tables...
    "%MYSQL_PATH%\mysql.exe" -u %MYSQL_USER% %MYSQL_DB% -e "SHOW TABLES LIKE '%%calendar%%'; SHOW TABLES LIKE '%%task%%'; SHOW TABLES LIKE '%%event%%';"
) else (
    echo.
    echo ERROR: Migration failed!
)

echo.
pause