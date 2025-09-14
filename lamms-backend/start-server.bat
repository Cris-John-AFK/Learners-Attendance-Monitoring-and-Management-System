@echo off
echo Starting Laravel Development Server (Direct PHP)...
echo Server will be available at: http://127.0.0.1:8000
echo Press Ctrl+C to stop the server
echo.
cd /d "%~dp0"
php -S 127.0.0.1:8000 -t public
pause
