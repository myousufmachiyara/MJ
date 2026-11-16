@echo off
REM Stops the local web server and database engine started by start.bat.
echo Stopping MJ Jewelry System...

taskkill /f /fi "WINDOWTITLE eq MJ-App*" >nul 2>&1
taskkill /f /fi "WINDOWTITLE eq MJ-MySQL*" >nul 2>&1

REM Fallback: kill by image name, in case the window title didn't match
REM (this will also stop any OTHER php.exe/mariadbd.exe the user has open,
REM  so only use this if the window-title kill above didn't work).
REM taskkill /f /im php.exe >nul 2>&1
REM taskkill /f /im mariadbd.exe >nul 2>&1

echo Stopped.
timeout /t 2 /nobreak >nul
