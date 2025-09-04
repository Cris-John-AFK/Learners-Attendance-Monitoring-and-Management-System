@echo off
echo Running Laravel migrations...
cd lamms-backend
php artisan migrate --force
echo Migrations completed!
pause
