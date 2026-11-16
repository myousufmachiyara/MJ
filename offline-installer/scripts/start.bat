@echo off
REM =====================================================================
REM  Starts the database engine and the app's web server, then opens the
REM  browser. This is what the desktop icon runs.
REM =====================================================================
setlocal
cd /d "%~dp0\.."

set PHP_DIR=%cd%\php
set MYSQL_DIR=%cd%\mysql
set APP_DIR=%cd%\app
set DATA_DIR=%cd%\data\mysql-data
set PORT=8000

echo Starting MJ Jewelry System...

REM ---- Start MariaDB if it isn't already running ----
tasklist /fi "imagename eq mariadbd.exe" 2>nul | find /i "mariadbd.exe" >nul
if errorlevel 1 (
    start "MJ-MySQL" /min "%MYSQL_DIR%\bin\mariadbd.exe" --datadir="%DATA_DIR%" --port=3307 --socket="%cd%\data\mysql.sock"
    timeout /t 5 /nobreak >nul
)

REM ---- Start PHP's built-in web server if it isn't already running ----
tasklist /fi "imagename eq php.exe" 2>nul | find /i "php.exe" >nul
if errorlevel 1 (
    cd /d "%APP_DIR%"
    start "MJ-App" /min "%PHP_DIR%\php.exe" -S 127.0.0.1:%PORT% -t public
    timeout /t 3 /nobreak >nul
)

start http://127.0.0.1:%PORT%
