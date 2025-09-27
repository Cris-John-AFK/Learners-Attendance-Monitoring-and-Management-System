@echo off
echo Seeding attendance data for SF2 testing...
php artisan db:seed --class=AttendanceSeeder
echo Done!
pause
