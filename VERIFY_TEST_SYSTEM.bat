@echo off
REM =========================================================================
REM Nexio Collabora - Verifica Sistema Test
REM =========================================================================

setlocal
color 0B

echo =========================================================================
echo                 NEXIO COLLABORA - VERIFICA SISTEMA TEST
echo =========================================================================
echo.

REM Test 1: PHP disponibile
echo [1/5] Verifica PHP...
C:\xampp\php\php.exe -v >nul 2>&1
if %errorlevel% == 0 (
    echo       [OK] PHP disponibile
) else (
    echo       [ERRORE] PHP non trovato
    pause
    exit /b 1
)

REM Test 2: Test runner presente
echo [2/5] Verifica test runner...
if exist "test_runner.php" (
    echo       [OK] test_runner.php presente
) else (
    echo       [ERRORE] test_runner.php non trovato
    pause
    exit /b 1
)

REM Test 3: Directory test
echo [3/5] Verifica directory test...
if exist "tests" (
    echo       [OK] Directory tests/ presente
) else (
    echo       [ERRORE] Directory tests/ non trovata
)

REM Test 4: Config test
echo [4/5] Verifica configurazione test...
if exist "config\test.config.php" (
    echo       [OK] config/test.config.php presente
) else (
    echo       [ERRORE] config/test.config.php non trovato
)

REM Test 5: Conta test disponibili
echo [5/5] Conteggio test disponibili...
set count=0
for %%f in (test*.php verify*.php check*.php) do set /a count+=1
echo       [OK] Trovati %count% file di test

echo.
echo =========================================================================
echo                          RIEPILOGO SISTEMA TEST
echo =========================================================================
echo.
echo ACCESSO WEB:
echo   http://localhost/Nexiosolution/collabora/test_runner.php
echo.
echo ACCESSO CLI:
echo   - Lista test:    php test_runner.php list
echo   - Esegui tutti:  php test_runner.php run
echo   - Per categoria: php test_runner.php [auth^|api^|system^|db^|ui]
echo.
echo SCRIPT BATCH:
echo   - Interattivo:   RUN_TESTS.bat
echo   - Verifica:      VERIFY_TEST_SYSTEM.bat (questo file)
echo.
echo DOCUMENTAZIONE:
echo   - Guida completa: TESTING_GUIDE.md
echo.
echo =========================================================================
echo.

REM Opzione per aprire test runner
set /p open="Vuoi aprire il Test Runner nel browser? (S/N): "
if /i "%open%"=="S" (
    echo Apertura browser...
    start "" "http://localhost/Nexiosolution/collabora/test_runner.php"
)

echo.
pause