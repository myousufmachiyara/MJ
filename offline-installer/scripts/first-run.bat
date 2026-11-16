@echo off
REM =====================================================================
REM  FIRST-RUN SETUP — runs once, right after install.
REM  Initializes the local MariaDB data directory, creates the database,
REM  runs migrations, and seeds the admin user. Safe to re-run: it skips
REM  steps that are already done.
REM =====================================================================
setlocal
cd /d "%~dp0\.."

set PHP_DIR=%cd%\php
set MYSQL_DIR=%cd%\mysql
set APP_DIR=%cd%\app
set DATA_DIR=%cd%\data\mysql-data
set PATH=%PHP_DIR%;%MYSQL_DIR%\bin;%PATH%

echo.
echo ==================================================
echo   MJ Jewelry System - First-time setup
echo ==================================================
echo.

REM ---- 1. Initialize the MariaDB data directory if it doesn't exist ----
if not exist "%DATA_DIR%\mysql" (
    echo [1/6] Initializing local database - first run only...
    rmdir /s /q "%DATA_DIR%" >nul 2>&1
    mkdir "%DATA_DIR%" >nul 2>&1
    "%MYSQL_DIR%\bin\mariadb-install-db.exe" --datadir="%DATA_DIR%"
) else (
    echo [1/6] Database already initialized, skipping.
)

REM ---- 2. Start MariaDB in the background to run setup commands ----
echo [2/6] Starting database engine...
start "MJ-MySQL" /min "%MYSQL_DIR%\bin\mariadbd.exe" --datadir="%DATA_DIR%" --port=3307 --socket="%cd%\data\mysql.sock"
timeout /t 6 /nobreak >nul

REM ---- 3. Create the database if it doesn't exist yet ----
echo [3/6] Creating database mj if needed...
"%MYSQL_DIR%\bin\mariadb.exe" --port=3307 -u root -e "CREATE DATABASE IF NOT EXISTS mj CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

REM ---- 4. Write the .env file if it doesn't exist ----
if not exist "%APP_DIR%\.env" (
    echo [4/6] Writing .env configuration...
    copy "%~dp0.env.offline" "%APP_DIR%\.env" >nul
) else (
    echo [4/6] .env already exists, skipping.
)

cd /d "%APP_DIR%"

REM ---- 5. Generate app key, run migrations, link storage ----
echo [5/6] Running database migrations...
"%PHP_DIR%\php.exe" artisan key:generate --force
"%PHP_DIR%\php.exe" artisan migrate --force
"%PHP_DIR%\php.exe" artisan storage:link
"%PHP_DIR%\php.exe" artisan config:cache
"%PHP_DIR%\php.exe" artisan route:cache

REM ---- 6. Seed the first admin user - only if none exists ----
echo [6/6] Seeding admin user - skips if one already exists...
"%PHP_DIR%\php.exe" artisan app:seed-offline-admin

echo.
echo ==================================================
echo   Setup complete. You can now use Start MJ Jewelry System
echo   from the desktop or Start Menu.
echo ==================================================
echo.
pause