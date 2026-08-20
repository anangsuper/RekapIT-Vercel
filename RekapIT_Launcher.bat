@echo off
title Rekap IT Desktop Launcher
cd /d "%~dp0"

:: 1. Cari lokasi executable PHP (Lokal, XAMPP, atau System PATH)
set "PHP_BIN="

if exist "%~dp0php\php.exe" (
    set "PHP_BIN=%~dp0php\php.exe"
) else if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
) else (
    where php >nul 2>nul
    if %errorlevel% equ 0 (
        set "PHP_BIN=php"
    )
)

if "%PHP_BIN%"=="" (
    echo [ERROR] PHP tidak ditemukan di sistem Anda!
    echo Harap pastikan XAMPP atau PHP terpasang.
    pause
    exit /b 1
)

:: 2. Tentukan Port Server (Default: 8090)
set PORT=8090
set URL=http://127.0.0.1:%PORT%

:: 3. Jalankan Server PHP lokal jika belum aktif
netstat -ano | findstr /R /C:":%PORT% " >nul
if %errorlevel% neq 0 (
    start "Rekap IT Server" /B "%PHP_BIN%" -S 127.0.0.1:%PORT% -t "%~dp0." >nul 2>&1
    timeout /t 2 /nobreak >nul
)

:: 4. Buka Rekap IT dalam Mode Desktop Standalone (Edge / Chrome App Mode)
set "EDGE_64=C:\Program Files\Microsoft\Edge\Application\msedge.exe"
set "EDGE_86=C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
set "CHROME_64=C:\Program Files\Google\Chrome\Application\chrome.exe"
set "CHROME_86=C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"

if exist "%EDGE_64%" (
    start "" "%EDGE_64%" --app=%URL% --window-name="Rekap IT Asset Management"
) else if exist "%EDGE_86%" (
    start "" "%EDGE_86%" --app=%URL% --window-name="Rekap IT Asset Management"
) else if exist "%CHROME_64%" (
    start "" "%CHROME_64%" --app=%URL%
) else if exist "%CHROME_86%" (
    start "" "%CHROME_86%" --app=%URL%
) else (
    start %URL%
)

exit /b 0
