@echo off
set DIR=%~dp0
set SCRIPT=%1

:: Sanity check
echo %SCRIPT% | findstr /R /C:"\.\." /C:"[^a-zA-Z0-9_-]" >nul
if %ERRORLEVEL% neq 1 (
    echo Invalid script name.
    exit /b 1
)

shift
php "%DIR%..\scripts\%SCRIPT%.php" %*
