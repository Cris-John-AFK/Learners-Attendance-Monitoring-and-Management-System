@echo off
REM Setup Local PostgreSQL Database for LAMMS

echo.
echo ========================================
echo LAMMS Local Database Setup
echo ========================================
echo.

REM Check if PostgreSQL is running
echo Checking PostgreSQL connection...
psql -U postgres -h localhost -c "SELECT version();" 2>nul
if %errorlevel% neq 0 (
    echo.
    echo ERROR: PostgreSQL is not running or not accessible at localhost:5432
    echo.
    echo Please ensure:
    echo 1. PostgreSQL is installed
    echo 2. PostgreSQL service is running (check Services)
    echo 3. PostgreSQL is listening on localhost:5432
    echo.
    pause
    exit /b 1
)

echo PostgreSQL is running!
echo.

REM Create database if it doesn't exist
echo Creating database 'lamms_db'...
psql -U postgres -h localhost -c "CREATE DATABASE lamms_db;" 2>nul
if %errorlevel% equ 0 (
    echo Database created successfully!
) else (
    echo Database may already exist (that's OK)
)

echo.
echo Running migrations...
php artisan migrate --force

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Your local database is ready.
echo You can now start the backend with: php artisan serve --host=0.0.0.0 --port=8000
echo.
pause
