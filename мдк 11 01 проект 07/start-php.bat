@echo off
cd /d "%~dp0"
echo.
echo  Site on PHP only (no Node).
echo  Open: http://127.0.0.1:8080
echo  Stop: Ctrl+C
echo.
php -S 127.0.0.1:8080 -t .
