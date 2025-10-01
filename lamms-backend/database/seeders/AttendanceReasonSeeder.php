<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceReasonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasons = [
            // LATE REASONS
            ['reason_name' => 'Far distance from home to school', 'reason_type' => 'late', 'category' => 'Transportation', 'display_order' => 1],
            ['reason_name' => 'Muddy/impassable road', 'reason_type' => 'late', 'category' => 'Transportation', 'display_order' => 2],
            ['reason_name' => 'Flooded road/area', 'reason_type' => 'late', 'category' => 'Transportation', 'display_order' => 3],
            ['reason_name' => 'No available transportation', 'reason_type' => 'late', 'category' => 'Transportation', 'display_order' => 4],
            ['reason_name' => 'Helping with farm/household chores before school', 'reason_type' => 'late', 'category' => 'Family', 'display_order' => 5],
            ['reason_name' => 'Heavy rain', 'reason_type' => 'late', 'category' => 'Weather', 'display_order' => 6],
            ['reason_name' => 'Strong typhoon/storm', 'reason_type' => 'late', 'category' => 'Weather', 'display_order' => 7],
            ['reason_name' => 'Illness (mild)', 'reason_type' => 'late', 'category' => 'Health', 'display_order' => 8],
            ['reason_name' => 'Medical appointment', 'reason_type' => 'late', 'category' => 'Health', 'display_order' => 9],
            ['reason_name' => 'Family emergency', 'reason_type' => 'late', 'category' => 'Family', 'display_order' => 10],
            ['reason_name' => 'Took care of younger sibling', 'reason_type' => 'late', 'category' => 'Family', 'display_order' => 11],
            ['reason_name' => 'Other', 'reason_type' => 'late', 'category' => 'Other', 'display_order' => 99],
            
            // EXCUSED REASONS
            ['reason_name' => 'Illness', 'reason_type' => 'excused', 'category' => 'Health', 'display_order' => 1],
            ['reason_name' => 'Medical appointment', 'reason_type' => 'excused', 'category' => 'Health', 'display_order' => 2],
            ['reason_name' => 'Medical procedure/treatment', 'reason_type' => 'excused', 'category' => 'Health', 'display_order' => 3],
            ['reason_name' => 'Recovering from illness', 'reason_type' => 'excused', 'category' => 'Health', 'display_order' => 4],
            ['reason_name' => 'Family emergency', 'reason_type' => 'excused', 'category' => 'Family', 'display_order' => 5],
            ['reason_name' => 'Family bereavement', 'reason_type' => 'excused', 'category' => 'Family', 'display_order' => 6],
            ['reason_name' => 'Family obligation/event', 'reason_type' => 'excused', 'category' => 'Family', 'display_order' => 7],
            ['reason_name' => 'Taking care of sick family member', 'reason_type' => 'excused', 'category' => 'Family', 'display_order' => 8],
            ['reason_name' => 'Typhoon/storm', 'reason_type' => 'excused', 'category' => 'Weather', 'display_order' => 9],
            ['reason_name' => 'Flooding (area inaccessible)', 'reason_type' => 'excused', 'category' => 'Weather', 'display_order' => 10],
            ['reason_name' => 'Road completely impassable', 'reason_type' => 'excused', 'category' => 'Weather', 'display_order' => 11],
            ['reason_name' => 'School-sanctioned activity', 'reason_type' => 'excused', 'category' => 'School', 'display_order' => 12],
            ['reason_name' => 'Other', 'reason_type' => 'excused', 'category' => 'Other', 'display_order' => 99],
        ];

        foreach ($reasons as $reason) {
            DB::table('attendance_reasons')->insert([
                'reason_name' => $reason['reason_name'],
                'reason_type' => $reason['reason_type'],
                'category' => $reason['category'],
                'display_order' => $reason['display_order'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
