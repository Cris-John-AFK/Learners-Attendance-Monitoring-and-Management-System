<?php
require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable('lamms-backend');
$dotenv->load();

// Setup database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'database' => $_ENV['DB_DATABASE'] ?? 'lamms_db',
    'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "=== CURRENT TEACHER AUTHENTICATION ANALYSIS ===\n\n";

    // 1. Check existing teacher data with user accounts
    echo "1. TEACHERS WITH USER ACCOUNTS:\n";
    $teachers = DB::table('teachers as t')
        ->join('users as u', 't.user_id', '=', 'u.id')
        ->select('t.id as teacher_id', 't.first_name', 't.last_name', 'u.email', 'u.username', 'u.role', 'u.is_active')
        ->get();
    
    foreach($teachers as $teacher) {
        echo "   Teacher ID: {$teacher->teacher_id}, Name: {$teacher->first_name} {$teacher->last_name}\n";
        echo "   Email: {$teacher->email}, Username: {$teacher->username}, Role: {$teacher->role}, Active: " . ($teacher->is_active ? 'Yes' : 'No') . "\n\n";
    }

    // 2. Check teacher assignments
    echo "2. TEACHER SUBJECT/SECTION ASSIGNMENTS:\n";
    $assignments = DB::table('teacher_section_subject as tss')
        ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
        ->join('sections as s', 'tss.section_id', '=', 's.id')
        ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
        ->select('t.id as teacher_id', 't.first_name', 't.last_name', 's.name as section_name', 'sub.name as subject_name', 'tss.role', 'tss.is_primary', 'tss.is_active')
        ->where('tss.is_active', true)
        ->get();
    
    $teacherAssignments = [];
    foreach($assignments as $assignment) {
        $teacherId = $assignment->teacher_id;
        if (!isset($teacherAssignments[$teacherId])) {
            $teacherAssignments[$teacherId] = [
                'name' => "{$assignment->first_name} {$assignment->last_name}",
                'assignments' => []
            ];
        }
        $teacherAssignments[$teacherId]['assignments'][] = [
            'section' => $assignment->section_name,
            'subject' => $assignment->subject_name ?? 'Homeroom',
            'role' => $assignment->role,
            'is_primary' => $assignment->is_primary
        ];
    }

    foreach($teacherAssignments as $teacherId => $data) {
        echo "   Teacher: {$data['name']} (ID: $teacherId)\n";
        foreach($data['assignments'] as $assignment) {
            echo "     - Section: {$assignment['section']}, Subject: {$assignment['subject']}, Role: {$assignment['role']}\n";
        }
        echo "\n";
    }

    // 3. Check authentication tables
    echo "3. AUTHENTICATION INFRASTRUCTURE:\n";
    $authTables = ['personal_access_tokens', 'sessions', 'password_resets'];
    foreach($authTables as $table) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            echo "   ✅ {$table} table exists\n";
        } else {
            echo "   ❌ {$table} table missing\n";
        }
    }

    // 4. Check Laravel Sanctum setup
    echo "\n4. LARAVEL SANCTUM TOKENS:\n";
    if (DB::getSchemaBuilder()->hasTable('personal_access_tokens')) {
        $tokenCount = DB::table('personal_access_tokens')->count();
        echo "   Total tokens: $tokenCount\n";
        if ($tokenCount > 0) {
            $recentTokens = DB::table('personal_access_tokens')
                ->select('name', 'tokenable_type', 'tokenable_id', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
            foreach($recentTokens as $token) {
                echo "   - Token: {$token->name}, Type: {$token->tokenable_type}, ID: {$token->tokenable_id}\n";
            }
        }
    }

    // 5. Check existing API routes
    echo "\n5. CHECKING EXISTING AUTH ROUTES:\n";
    $routeFile = 'lamms-backend/routes/api.php';
    if (file_exists($routeFile)) {
        $content = file_get_contents($routeFile);
        if (strpos($content, 'login') !== false) {
            echo "   ✅ Login routes found in api.php\n";
        } else {
            echo "   ❌ No login routes found in api.php\n";
        }
        if (strpos($content, 'auth') !== false) {
            echo "   ✅ Auth routes found in api.php\n";
        } else {
            echo "   ❌ No auth routes found in api.php\n";
        }
    }

    echo "\n=== RECOMMENDATIONS ===\n";
    echo "Based on the analysis:\n";
    echo "1. ✅ Teacher-User relationship exists (teachers.user_id -> users.id)\n";
    echo "2. ✅ Teacher assignments are properly structured\n";
    echo "3. ✅ Users table has role-based access (role column)\n";
    echo "4. Need to implement: Teacher login system\n";
    echo "5. Need to implement: Teacher dashboard with assigned subjects/sections\n";
    echo "6. Need to implement: Session-based authentication for teachers\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
