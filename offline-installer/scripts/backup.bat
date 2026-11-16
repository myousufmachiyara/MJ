@echo off
REM =====================================================================
REM  Daily backup: dumps the database and copies uploaded files/images to
REM  a dated folder. Point Windows Task Scheduler at this script to run
REM  it automatically every day (see README-BUILD.md, step 7).
REM =====================================================================
setlocal enabledelayedexpansion
cd /d "%~dp0\.."

set MYSQL_DIR=%cd%\mysql
set APP_DIR=%cd%\app
set BACKUP_ROOT=%cd%\backups

REM ---- Build a dated folder name, e.g. backups\2026-11-16 ----
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set dt=%%I
set TODAY=%dt:~0,4%-%dt:~4,2%-%dt:~6,2%
set DEST=%BACKUP_ROOT%\%TODAY%

if exist "%DEST%" (
    echo Backup for %TODAY% already exists, skipping.
    goto :eof
)
mkdir "%DEST%"

echo Backing up database...
"%MYSQL_DIR%\bin\mariadb-dump.exe" --port=3307 -u root mj > "%DEST%\mj.sql"

echo Backing up uploaded files and images...
xcopy "%APP_DIR%\storage\app\public" "%DEST%\storage" /E /I /Y /Q >nul

echo Backup saved to %DEST%

REM ---- Keep only the last 30 days of backups ----
forfiles /p "%BACKUP_ROOT%" /m *.* /d -30 /c "cmd /c if @isdir==TRUE rmdir /s /q @path" 2>nul

endlocal
