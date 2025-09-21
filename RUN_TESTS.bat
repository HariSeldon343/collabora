@echo off
REM =========================================================================
REM Nexio Collabora - Test Runner per Windows
REM =========================================================================
REM Questo script facilita l'esecuzione dei test su Windows/XAMPP
REM =========================================================================

setlocal enabledelayedexpansion
color 0A

REM Configurazione paths
set PHP_PATH=C:\xampp\php\php.exe
set PROJECT_PATH=C:\xampp\htdocs\Nexiosolution\collabora
set TEST_RUNNER=%PROJECT_PATH%\test_runner.php

REM Verifica PHP
if not exist "%PHP_PATH%" (
    color 0C
    echo [ERRORE] PHP non trovato in: %PHP_PATH%
    echo Verifica che XAMPP sia installato correttamente.
    pause
    exit /b 1
)

REM Verifica test runner
if not exist "%TEST_RUNNER%" (
    color 0C
    echo [ERRORE] Test runner non trovato in: %TEST_RUNNER%
    pause
    exit /b 1
)

REM Cambia directory
cd /d "%PROJECT_PATH%"

REM Menu principale
:MENU
cls
echo ==============================================================================
echo                     NEXIO COLLABORA - TEST SUITE v2.0
echo ==============================================================================
echo.
echo   [1] Esegui TUTTI i test
echo   [2] Test AUTENTICAZIONE
echo   [3] Test API
echo   [4] Test DATABASE
echo   [5] Test SISTEMA
echo   [6] Test INTERFACCIA UI
echo   [7] Lista test disponibili
echo   [8] Apri Test Runner nel Browser
echo   [9] Verifica singolo test
echo   [0] Esci
echo.
echo ==============================================================================
set /p choice="Seleziona opzione [0-9]: "

if "%choice%"=="1" goto RUN_ALL
if "%choice%"=="2" goto RUN_AUTH
if "%choice%"=="3" goto RUN_API
if "%choice%"=="4" goto RUN_DB
if "%choice%"=="5" goto RUN_SYSTEM
if "%choice%"=="6" goto RUN_UI
if "%choice%"=="7" goto LIST_TESTS
if "%choice%"=="8" goto OPEN_BROWSER
if "%choice%"=="9" goto RUN_SINGLE
if "%choice%"=="0" goto EXIT

echo [ERRORE] Opzione non valida!
timeout /t 2 >nul
goto MENU

:RUN_ALL
cls
echo ==============================================================================
echo                         ESECUZIONE TUTTI I TEST
echo ==============================================================================
echo.
"%PHP_PATH%" "%TEST_RUNNER%" run
echo.
echo ==============================================================================
pause
goto MENU

:RUN_AUTH
cls
echo ==============================================================================
echo                      TEST AUTENTICAZIONE
echo ==============================================================================
echo.
"%PHP_PATH%" "%TEST_RUNNER%" auth
echo.
echo ==============================================================================
pause
goto MENU

:RUN_API
cls
echo ==============================================================================
echo                           TEST API
echo ==============================================================================
echo.
"%PHP_PATH%" "%TEST_RUNNER%" api
echo.
echo ==============================================================================
pause
goto MENU

:RUN_DB
cls
echo ==============================================================================
echo                         TEST DATABASE
echo ==============================================================================
echo.
"%PHP_PATH%" "%TEST_RUNNER%" db
echo.
echo ==============================================================================
pause
goto MENU

:RUN_SYSTEM
cls
echo ==============================================================================
echo                         TEST SISTEMA
echo ==============================================================================
echo.
"%PHP_PATH%" "%TEST_RUNNER%" system
echo.
echo ==============================================================================
pause
goto MENU

:RUN_UI
cls
echo ==============================================================================
echo                      TEST INTERFACCIA UI
echo ==============================================================================
echo.
"%PHP_PATH%" "%TEST_RUNNER%" ui
echo.
echo ==============================================================================
pause
goto MENU

:LIST_TESTS
cls
echo ==============================================================================
echo                      LISTA TEST DISPONIBILI
echo ==============================================================================
echo.
"%PHP_PATH%" "%TEST_RUNNER%" list
echo.
echo ==============================================================================
pause
goto MENU

:OPEN_BROWSER
echo.
echo Apertura Test Runner nel browser...
start "" "http://localhost/Nexiosolution/collabora/test_runner.php"
timeout /t 2 >nul
goto MENU

:RUN_SINGLE
cls
echo ==============================================================================
echo                      ESECUZIONE TEST SINGOLO
echo ==============================================================================
echo.
echo Test disponibili:
echo -----------------
dir /b test*.php verify*.php check*.php 2>nul | more
echo.
set /p testfile="Inserisci nome del test da eseguire (es: test_auth.php): "

if exist "%testfile%" (
    cls
    echo ==============================================================================
    echo                    ESECUZIONE: %testfile%
    echo ==============================================================================
    echo.
    "%PHP_PATH%" "%testfile%"
    echo.
    echo ==============================================================================
) else (
    echo [ERRORE] File non trovato: %testfile%
)
pause
goto MENU

:EXIT
cls
echo.
echo Grazie per aver utilizzato Nexio Collabora Test Suite!
echo.
timeout /t 2 >nul
exit /b 0