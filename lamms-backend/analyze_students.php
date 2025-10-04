<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "👨‍🎓 LAMMS Student Analysis - Current Student Data\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// Total student count
$totalStudents = DB::table('student_details')->count();
$activeStudents = DB::table('student_details')->where('current_status', 'active')->count();

echo "📊 STUDENT OVERVIEW:\n";
echo "   • Total Students: {$totalStudents}\n";
echo "   • Active Students: {$activeStudents}\n";
echo "   • Inactive Students: " . ($totalStudents - $activeStudents) . "\n\n";

// Students by grade level
echo "🎓 STUDENTS BY GRADE LEVEL:\n";
try {
    $studentsByGrade = DB::table('student_details as sd')
        ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
        ->join('sections as s', 'ss.section_id', '=', 's.id')
        ->join('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
        ->join('grades as g', 'cg.grade_id', '=', 'g.id')
        ->where('ss.is_active', true)
        ->select('g.name as grade_name', 'g.level', DB::raw('COUNT(*) as student_count'))
        ->groupBy('g.name', 'g.level')
        ->orderBy('g.level')
        ->get();

    foreach ($studentsByGrade as $gradeData) {
        echo "   📚 {$gradeData->grade_name}: {$gradeData->student_count} students\n";
    }
} catch (Exception $e) {
    echo "   ⚠️ Could not get grade-level breakdown: " . $e->getMessage() . "\n";
    
    // Fallback: use gradeLevel field directly
    $gradeDistribution = DB::table('student_details')
        ->select('gradeLevel', DB::raw('COUNT(*) as count'))
        ->groupBy('gradeLevel')
        ->orderBy('gradeLevel')
        ->get();
    
    echo "   Fallback grade distribution:\n";
    foreach ($gradeDistribution as $grade) {
        echo "   📚 Grade {$grade->gradeLevel}: {$grade->count} students\n";
    }
}

echo "\n🏫 STUDENTS BY SECTION:\n";
try {
    $studentsBySection = DB::table('student_details as sd')
        ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
        ->join('sections as s', 'ss.section_id', '=', 's.id')
        ->where('ss.is_active', true)
        ->select('s.name as section_name', DB::raw('COUNT(*) as student_count'))
        ->groupBy('s.name')
        ->orderBy('student_count', 'desc')
        ->get();

    foreach ($studentsBySection as $sectionData) {
        echo "   🏛️ {$sectionData->section_name}: {$sectionData->student_count} students\n";
    }
} catch (Exception $e) {
    echo "   ⚠️ Could not get section breakdown: " . $e->getMessage() . "\n";
}

echo "\n🗺️ GEOGRAPHIC DISTRIBUTION:\n";
$studentsWithAddresses = DB::table('student_details')
    ->whereNotNull('currentAddress')
    ->count();

echo "   • Students with addresses: {$studentsWithAddresses}\n";
echo "   • Students without addresses: " . ($totalStudents - $studentsWithAddresses) . "\n\n";

// Analyze current addresses
if ($studentsWithAddresses > 0) {
    $addressData = DB::table('student_details')
        ->whereNotNull('currentAddress')
        ->select('currentAddress')
        ->get()
        ->map(function($student) {
            $address = json_decode($student->currentAddress, true);
            return [
                'city' => $address['city'] ?? 'Unknown',
                'barangay' => $address['barangay'] ?? 'Unknown',
                'street' => $address['street'] ?? 'Unknown'
            ];
        });

    // City distribution
    $cityDistribution = $addressData->countBy('city');
    echo "   📍 DISTRIBUTION BY CITY:\n";
    foreach ($cityDistribution as $city => $count) {
        echo "      • {$city}: {$count} students\n";
    }

    // Naawan barangay distribution
    $naawaanStudents = $addressData->filter(function($addr) { 
        return $addr['city'] === 'Naawan'; 
    });
    
    if ($naawaanStudents->count() > 0) {
        $barangayDistribution = $naawaanStudents->countBy('barangay');
        echo "\n   📍 NAAWAN BARANGAYS:\n";
        foreach ($barangayDistribution->sortDesc() as $barangay => $count) {
            echo "      • Brgy. {$barangay}: {$count} students\n";
        }
    }

    // Sample addresses
    echo "\n   📋 SAMPLE STUDENT ADDRESSES:\n";
    $sampleStudents = DB::table('student_details')
        ->whereNotNull('currentAddress')
        ->select('firstName', 'lastName', 'currentAddress', 'gradeLevel')
        ->limit(10)
        ->get();

    foreach ($sampleStudents as $student) {
        $address = json_decode($student->currentAddress, true);
        if ($address) {
            $location = "{$address['street']}, Brgy. {$address['barangay']}, {$address['city']}";
            echo "      • {$student->firstName} {$student->lastName} (Grade {$student->gradeLevel}): {$location}\n";
        }
    }
}

echo "\n👥 STUDENT DEMOGRAPHICS:\n";
$genderDistribution = DB::table('student_details')
    ->select('gender', DB::raw('COUNT(*) as count'))
    ->groupBy('gender')
    ->get();

foreach ($genderDistribution as $gender) {
    $genderName = $gender->gender ?: 'Not specified';
    echo "   • {$genderName}: {$gender->count} students\n";
}

echo "\n📅 STUDENT AGE DISTRIBUTION:\n";
$ageDistribution = DB::table('student_details')
    ->select('age', DB::raw('COUNT(*) as count'))
    ->whereNotNull('age')
    ->groupBy('age')
    ->orderBy('age')
    ->get();

if ($ageDistribution->count() > 0) {
    foreach ($ageDistribution as $age) {
        echo "   • Age {$age->age}: {$age->count} students\n";
    }
} else {
    echo "   ⚠️ Age data not available\n";
}

echo "\n📱 QR CODE STATUS:\n";
$qrStats = DB::table('student_qr_codes as sqc')
    ->join('student_details as sd', 'sqc.student_id', '=', 'sd.id')
    ->select(
        DB::raw('COUNT(*) as total_qr_codes'),
        DB::raw('COUNT(CASE WHEN sqc.is_active = true THEN 1 END) as active_qr_codes')
    )
    ->first();

echo "   • Total QR Codes: {$qrStats->total_qr_codes}\n";
echo "   • Active QR Codes: {$qrStats->active_qr_codes}\n";
echo "   • Students without QR: " . ($totalStudents - $qrStats->total_qr_codes) . "\n";

echo "\n📊 ATTENDANCE PARTICIPATION:\n";
$attendanceStats = DB::table('attendance_records as ar')
    ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
    ->select(
        DB::raw('COUNT(DISTINCT ar.student_id) as students_with_attendance'),
        DB::raw('COUNT(ar.id) as total_attendance_records')
    )
    ->first();

echo "   • Students with attendance records: {$attendanceStats->students_with_attendance}\n";
echo "   • Total attendance records: {$attendanceStats->total_attendance_records}\n";
echo "   • Students without attendance: " . ($totalStudents - $attendanceStats->students_with_attendance) . "\n";

echo "\n" . "=" . str_repeat("=", 80) . "\n";
echo "🎯 STUDENT DATA SUMMARY:\n";
echo "✅ Total: {$totalStudents} students across K-6 grade levels\n";
echo "✅ Geographic: {$studentsWithAddresses} students with realistic Naawan addresses\n";
echo "✅ QR System: {$qrStats->active_qr_codes} active QR codes for attendance\n";
echo "✅ Attendance: {$attendanceStats->students_with_attendance} students with attendance records\n";
echo "🏫 School: Naawan Central School (Complete K-6 system)\n";

if ($studentsWithAddresses > 0) {
    echo "\n🗺️ HEATMAP READY:\n";
    echo "🔴 Mountain areas (Upper Naawan, Kapatagan) - Transportation challenges\n";
    echo "🟡 Neighboring towns (Linangkayan, Manticao) - Distance issues\n";
    echo "🟢 Town center (Poblacion) - Easy school access\n";
    echo "🔵 Coastal areas (Baybay, Malubog) - Weather-dependent attendance\n";
}

?>
