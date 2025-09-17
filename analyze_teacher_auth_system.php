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
    echo "=== ANALYZING TEACHER AUTHENTICATION SYSTEM ===\n\n";

    // Check for teacher-related tables
    echo "1. TEACHER-RELATED TABLES:\n";
    $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ? ORDER BY table_name', ['public']);
    foreach($tables as $table) {
        if(strpos($table->table_name, 'teacher') !== false || 
           strpos($table->table_name, 'user') !== false ||
           strpos($table->table_name, 'auth') !== false) {
            echo "   - {$table->table_name}\n";
        }
    }

    echo "\n2. TEACHERS TABLE STRUCTURE:\n";
    if (DB::getSchemaBuilder()->hasTable('teachers')) {
        $columns = DB::select('SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position', ['teachers']);
        foreach($columns as $col) {
            echo "   - {$col->column_name} ({$col->data_type}) - Nullable: {$col->is_nullable}\n";
        }
        
        echo "\n   Sample teacher data:\n";
        $teachers = DB::table('teachers')->limit(3)->get();
        foreach($teachers as $teacher) {
            echo "   ID: {$teacher->id}, Name: {$teacher->name}\n";
        }
    } else {
        echo "   ❌ Teachers table does not exist\n";
    }

    echo "\n3. USERS TABLE STRUCTURE:\n";
    if (DB::getSchemaBuilder()->hasTable('users')) {
        $columns = DB::select('SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position', ['users']);
        foreach($columns as $col) {
            echo "   - {$col->column_name} ({$col->data_type}) - Nullable: {$col->is_nullable}\n";
        }
        
        echo "\n   Sample user data:\n";
        $users = DB::table('users')->limit(3)->get();
        foreach($users as $user) {
            echo "   ID: {$user->id}, Email: {$user->email}, Role: " . ($user->role ?? 'N/A') . "\n";
        }
    } else {
        echo "   ❌ Users table does not exist\n";
    }

    echo "\n4. TEACHER-SECTION-SUBJECT ASSIGNMENTS:\n";
    if (DB::getSchemaBuilder()->hasTable('teacher_section_subject')) {
        $columns = DB::select('SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position', ['teacher_section_subject']);
        foreach($columns as $col) {
            echo "   - {$col->column_name} ({$col->data_type}) - Nullable: {$col->is_nullable}\n";
        }
        
        echo "\n   Sample assignment data:\n";
        $assignments = DB::table('teacher_section_subject as tss')
            ->join('teachers as t', 'tss.teacher_id', '=', 't.id')
            ->join('sections as s', 'tss.section_id', '=', 's.id')
            ->leftJoin('subjects as sub', 'tss.subject_id', '=', 'sub.id')
            ->select('t.name as teacher_name', 's.name as section_name', 'sub.name as subject_name', 'tss.role', 'tss.is_primary')
            ->limit(5)
            ->get();
        
        foreach($assignments as $assignment) {
            echo "   Teacher: {$assignment->teacher_name}, Section: {$assignment->section_name}, Subject: " . ($assignment->subject_name ?? 'Homeroom') . ", Role: {$assignment->role}\n";
        }
    } else {
        echo "   ❌ teacher_section_subject table does not exist\n";
    }

    echo "\n5. AUTHENTICATION TOKENS/SESSIONS:\n";
    $authTables = ['personal_access_tokens', 'sessions', 'password_resets'];
    foreach($authTables as $table) {
        if (DB::getSchemaBuilder()->hasTable($table)) {
            echo "   ✅ {$table} table exists\n";
        } else {
            echo "   ❌ {$table} table does not exist\n";
        }
    }

    echo "\n6. CHECKING TEACHER-USER RELATIONSHIP:\n";
    if (DB::getSchemaBuilder()->hasTable('teachers') && DB::getSchemaBuilder()->hasTable('users')) {
        // Check if teachers have user_id column
        $teacherColumns = DB::select('SELECT column_name FROM information_schema.columns WHERE table_name = ?', ['teachers']);
        $hasUserId = false;
        foreach($teacherColumns as $col) {
            if($col->column_name === 'user_id') {
                $hasUserId = true;
                break;
            }
        }
        
        if($hasUserId) {
            echo "   ✅ Teachers table has user_id column\n";
            $teachersWithUsers = DB::table('teachers as t')
                ->leftJoin('users as u', 't.user_id', '=', 'u.id')
                ->select('t.name as teacher_name', 'u.email', 'u.id as user_id')
                ->limit(3)
                ->get();
            
            foreach($teachersWithUsers as $tw) {
                echo "   Teacher: {$tw->teacher_name}, Email: " . ($tw->email ?? 'No user account') . "\n";
            }
        } else {
            echo "   ❌ Teachers table does not have user_id column\n";
        }
    }

    echo "\n=== ANALYSIS COMPLETE ===\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
