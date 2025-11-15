<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing heatmap query...\n";

    $teacherId = 1;
    $period = 'week';
    $subjectId = 1;

    $endDate = Carbon::now();
    $startDate = $endDate->copy()->subWeeks(4);

    echo "Date range: {$startDate} to {$endDate}\n";

    // Test the query step by step
    $query = DB::table('attendance_records as ar')
        ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
        ->join('attendance_reasons as areason', 'ar.reason_id', '=', 'areason.id')
        ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
        ->where('ases.teacher_id', $teacherId)
        ->whereIn('ast.code', ['L', 'E'])
        ->whereNotNull('ar.reason_id')
        ->whereBetween('ases.session_date', [$startDate, $endDate])
        ->where('areason.is_active', true);

    echo "Query built successfully\n";

    $count = $query->count();
    echo "Found {$count} records\n";

    if ($count > 0) {
        $sample = $query->select([
            'areason.reason_name',
            'areason.reason_type',
            'ast.code as status_code',
            'sd.address'
        ])->first();

        echo "Sample record:\n";
        print_r($sample);
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
