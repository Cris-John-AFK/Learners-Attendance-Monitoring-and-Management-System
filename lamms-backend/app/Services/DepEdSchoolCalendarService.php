<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepEdSchoolCalendarService
{
    /**
     * DepEd Philippine School Calendar Service
     * Handles proper school day calculations excluding weekends and holidays
     */

    // DepEd National Holidays 2024-2025
    private static $nationalHolidays = [
        '2024-08-21' => 'Ninoy Aquino Day',
        '2024-08-26' => 'National Heroes Day',
        '2024-11-01' => 'All Saints Day',
        '2024-11-30' => 'Bonifacio Day',
        '2024-12-25' => 'Christmas Day',
        '2024-12-30' => 'Rizal Day',
        '2025-01-01' => 'New Year\'s Day',
        '2025-04-09' => 'Araw ng Kagitingan',
        '2025-04-17' => 'Maundy Thursday',
        '2025-04-18' => 'Good Friday',
        '2025-05-01' => 'Labor Day',
        '2025-06-12' => 'Independence Day',
    ];

    /**
     * Check if a date is a valid school day (DepEd compliant)
     */
    public static function isValidSchoolDay(Carbon $date): bool
    {
        // Check if it's a weekend
        if ($date->isWeekend()) {
            return false;
        }

        // Check if it's a national holiday
        $dateString = $date->format('Y-m-d');
        if (isset(self::$nationalHolidays[$dateString])) {
            return false;
        }

        // Check database for school-specific holidays
        $isHoliday = DB::table('school_holidays')
            ->where('date', $dateString)
            ->where('is_active', true)
            ->exists();

        if ($isHoliday) {
            return false;
        }

        // Check if it's within the school year
        return self::isWithinSchoolYear($date);
    }

    /**
     * Check if date is within active school year
     */
    public static function isWithinSchoolYear(Carbon $date): bool
    {
        $schoolYear = DB::table('school_years')
            ->where('is_active', true)
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->first();

        return $schoolYear !== null;
    }

    /**
     * Get all valid school days between two dates
     */
    public static function getValidSchoolDaysBetween(Carbon $startDate, Carbon $endDate): array
    {
        $schoolDays = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (self::isValidSchoolDay($current)) {
                $schoolDays[] = $current->format('Y-m-d');
            }
            $current->addDay();
        }

        return $schoolDays;
    }

    /**
     * Count valid school days between two dates
     */
    public static function countValidSchoolDays(Carbon $startDate, Carbon $endDate): int
    {
        return count(self::getValidSchoolDaysBetween($startDate, $endDate));
    }

    /**
     * Calculate attendance rate for DepEd SF2 compliance
     */
    public static function calculateAttendanceRate(array $attendanceRecords, Carbon $startDate, Carbon $endDate): array
    {
        $validSchoolDays = self::getValidSchoolDaysBetween($startDate, $endDate);
        $totalSchoolDays = count($validSchoolDays);

        if ($totalSchoolDays === 0) {
            return [
                'total_school_days' => 0,
                'days_present' => 0,
                'days_absent' => 0,
                'days_late' => 0,
                'attendance_rate' => 0,
                'valid_for_sf2' => false
            ];
        }

        $daysPresent = 0;
        $daysAbsent = 0;
        $daysLate = 0;

        // Count attendance only for valid school days
        foreach ($attendanceRecords as $record) {
            $recordDate = Carbon::parse($record['date'])->format('Y-m-d');
            
            // Only count if it's a valid school day
            if (in_array($recordDate, $validSchoolDays)) {
                switch ($record['status']) {
                    case 'P':
                    case 'PRESENT':
                        $daysPresent++;
                        break;
                    case 'A':
                    case 'ABSENT':
                        $daysAbsent++;
                        break;
                    case 'L':
                    case 'LATE':
                        $daysLate++;
                        break;
                }
            }
        }

        // Calculate days without records (considered absent)
        $recordedDays = $daysPresent + $daysAbsent + $daysLate;
        $unrecordedDays = $totalSchoolDays - $recordedDays;
        $daysAbsent += $unrecordedDays;

        $attendanceRate = $totalSchoolDays > 0 ? round(($daysPresent / $totalSchoolDays) * 100, 2) : 0;

        return [
            'total_school_days' => $totalSchoolDays,
            'days_present' => $daysPresent,
            'days_absent' => $daysAbsent,
            'days_late' => $daysLate,
            'attendance_rate' => $attendanceRate,
            'valid_for_sf2' => $totalSchoolDays >= 10, // Minimum days for valid SF2
            'sf2_compliant' => true
        ];
    }

    /**
     * Initialize default DepEd school year and holidays
     */
    public static function initializeDepEdCalendar(): void
    {
        // Create default school year 2024-2025
        DB::table('school_years')->updateOrInsert(
            ['name' => '2024-2025'],
            [
                'start_date' => '2024-08-26',  // DepEd typical start
                'end_date' => '2025-05-30',    // DepEd typical end
                'is_active' => true,
                'quarters' => json_encode([
                    'Q1' => ['start' => '2024-08-26', 'end' => '2024-10-31'],
                    'Q2' => ['start' => '2024-11-04', 'end' => '2025-01-24'],
                    'Q3' => ['start' => '2025-01-27', 'end' => '2025-04-04'],
                    'Q4' => ['start' => '2025-04-07', 'end' => '2025-05-30'],
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );

        // Insert national holidays
        foreach (self::$nationalHolidays as $date => $name) {
            DB::table('school_holidays')->updateOrInsert(
                ['date' => $date],
                [
                    'name' => $name,
                    'type' => 'national',
                    'description' => 'DepEd National Holiday',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }

        Log::info('DepEd School Calendar initialized successfully');
    }
}
