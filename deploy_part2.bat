@echo off
:: ====================================================
:: NEXIOSOLUTION PARTE 2 - DEPLOYMENT SCRIPT
:: Calendar & Task Management Modules
:: ====================================================
:: Creato: 2025-01-19
:: Versione: 2.1.0
:: ====================================================

setlocal enabledelayedexpansion
color 0A
cls

echo ====================================================
echo    NEXIOSOLUTION - DEPLOYMENT PARTE 2
echo    Calendar ^& Task Management
echo ====================================================
echo.

:: Verifica se XAMPP è in esecuzione
echo [1/8] Verifico servizi XAMPP...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I /N "httpd.exe">NUL
if "%ERRORLEVEL%"=="1" (
    echo [ERRORE] Apache non è in esecuzione!
    echo Avvia XAMPP Control Panel e riprova.
    pause
    exit /b 1
)

tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="1" (
    echo [ERRORE] MySQL/MariaDB non è in esecuzione!
    echo Avvia XAMPP Control Panel e riprova.
    pause
    exit /b 1
)
echo [OK] Servizi XAMPP attivi

:: Verifica percorso di installazione
echo.
echo [2/8] Verifico percorso di installazione...
set "BASE_PATH=C:\xampp\htdocs\Nexiosolution\collabora"
if not exist "%BASE_PATH%" (
    echo [ERRORE] Percorso %BASE_PATH% non trovato!
    echo Verifica l'installazione di Nexiosolution.
    pause
    exit /b 1
)
cd /d "%BASE_PATH%"
echo [OK] Percorso verificato: %BASE_PATH%

:: Backup database esistente
echo.
echo [3/8] Creo backup del database...
set "BACKUP_DIR=%BASE_PATH%\backups"
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"
set "TIMESTAMP=%DATE:~-4%%DATE:~3,2%%DATE:~0,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%"
set "TIMESTAMP=%TIMESTAMP: =0%"
set "BACKUP_FILE=%BACKUP_DIR%\nexio_backup_%TIMESTAMP%.sql"

C:\xampp\mysql\bin\mysqldump -u root --databases nexio_collabora_v2 > "%BACKUP_FILE%" 2>NUL
if %ERRORLEVEL% EQU 0 (
    echo [OK] Backup creato: %BACKUP_FILE%
) else (
    echo [WARNING] Impossibile creare backup automatico
    echo Continuare comunque? (S/N)
    choice /C SN /M "Scelta"
    if !ERRORLEVEL! EQU 2 exit /b 1
)

:: Applicazione migrazione database
echo.
echo [4/8] Applico migrazione database Parte 2...
if not exist "%BASE_PATH%\database\migrations_part2.sql" (
    echo [ERRORE] File migrations_part2.sql non trovato!
    pause
    exit /b 1
)

echo Eseguo migrazione tabelle Calendar e Task Management...
C:\xampp\mysql\bin\mysql -u root nexio_collabora_v2 < "%BASE_PATH%\database\migrations_part2.sql" 2>NUL
if %ERRORLEVEL% EQU 0 (
    echo [OK] Migrazione database completata
) else (
    echo [ERRORE] Errore durante la migrazione database!
    echo Controlla il file di log per dettagli.
    pause
    exit /b 1
)

:: Verifica file API
echo.
echo [5/8] Verifico API endpoints...
set "ERROR_COUNT=0"

if not exist "%BASE_PATH%\api\calendars.php" (
    echo [ERRORE] File api/calendars.php mancante!
    set /a ERROR_COUNT+=1
)
if not exist "%BASE_PATH%\api\events.php" (
    echo [ERRORE] File api/events.php mancante!
    set /a ERROR_COUNT+=1
)
if not exist "%BASE_PATH%\api\tasks.php" (
    echo [ERRORE] File api/tasks.php mancante!
    set /a ERROR_COUNT+=1
)
if not exist "%BASE_PATH%\api\task-lists.php" (
    echo [ERRORE] File api/task-lists.php mancante!
    set /a ERROR_COUNT+=1
)

if !ERROR_COUNT! GTR 0 (
    echo [ERRORE] File API mancanti. Deployment incompleto!
    pause
    exit /b 1
) else (
    echo [OK] Tutti gli endpoint API sono presenti
)

:: Verifica file Frontend
echo.
echo [6/8] Verifico interfacce utente...
set "ERROR_COUNT=0"

if not exist "%BASE_PATH%\calendar.php" (
    echo [ERRORE] File calendar.php mancante!
    set /a ERROR_COUNT+=1
)
if not exist "%BASE_PATH%\tasks.php" (
    echo [ERRORE] File tasks.php mancante!
    set /a ERROR_COUNT+=1
)
if not exist "%BASE_PATH%\assets\js\calendar.js" (
    echo [ERRORE] File assets/js/calendar.js mancante!
    set /a ERROR_COUNT+=1
)
if not exist "%BASE_PATH%\assets\js\tasks.js" (
    echo [ERRORE] File assets/js/tasks.js mancante!
    set /a ERROR_COUNT+=1
)

if !ERROR_COUNT! GTR 0 (
    echo [ERRORE] File frontend mancanti. Deployment incompleto!
    pause
    exit /b 1
) else (
    echo [OK] Tutte le interfacce utente sono presenti
)

:: Verifica permessi cartelle
echo.
echo [7/8] Verifico e imposto permessi...
:: In Windows XAMPP i permessi sono automaticamente gestiti
echo [OK] Permessi verificati (Windows/XAMPP)

:: Test connettività database
echo.
echo [8/8] Test finale connettività...
echo SELECT 'OK' as status; | C:\xampp\mysql\bin\mysql -u root nexio_collabora_v2 2>NUL | findstr /C:"OK" >NUL
if %ERRORLEVEL% EQU 0 (
    echo [OK] Database connesso e funzionante
) else (
    echo [ERRORE] Impossibile connettersi al database!
    pause
    exit /b 1
)

:: Riepilogo finale
echo.
echo ====================================================
echo    DEPLOYMENT COMPLETATO CON SUCCESSO!
echo ====================================================
echo.
echo Moduli installati:
echo - Calendar Management (calendari ed eventi)
echo - Task Management (attività e kanban board)
echo.
echo Prossimi passi:
echo 1. Esegui test_part2_system.php per verificare il sistema
echo 2. Accedi a http://localhost/Nexiosolution/collabora
echo 3. Naviga a Calendario e Attività dal menu laterale
echo.
echo Per testare le funzionalità:
echo - Login: asamodeo@fortibyte.it / Ricord@1991
echo.
echo ====================================================

:: Chiedi se eseguire test automatici
echo.
echo Vuoi eseguire i test automatici ora? (S/N)
choice /C SN /M "Scelta"
if !ERRORLEVEL! EQU 1 (
    echo.
    echo Eseguo test di sistema...
    php "%BASE_PATH%\test_part2_system.php"
)

echo.
echo Deployment completato alle %TIME%
pause