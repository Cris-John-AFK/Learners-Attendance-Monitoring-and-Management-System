<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    // Load Laravel environment
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "=== TABLE STRUCTURES ===\n\n";
    
    // Check sections table structure
    echo "1. SECTIONS TABLE STRUCTURE:\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'sections' ORDER BY ordinal_position");
    foreach ($columns as $column) {
        echo "   - {$column->column_name}: {$column->data_type}\n";
    }
    echo "\n";
    
    // Check teachers table structure
    echo "2. TEACHERS TABLE STRUCTURE:\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'teachers' ORDER BY ordinal_position");
    foreach ($columns as $column) {
        echo "   - {$column->column_name}: {$column->data_type}\n";
    }
    echo "\n";
    
    // Check subjects table structure
    echo "3. SUBJECTS TABLE STRUCTURE:\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'subjects' ORDER BY ordinal_position");
    foreach ($columns as $column) {
        echo "   - {$column->column_name}: {$column->data_type}\n";
    }
    echo "\n";
    
    // Check teacher_assignments table structure
    echo "4. TEACHER_ASSIGNMENTS TABLE STRUCTURE:\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'teacher_assignments' ORDER BY ordinal_position");
    foreach ($columns as $column) {
        echo "   - {$column->column_name}: {$column->data_type}\n";
    }
    echo "\n";
    
    // Check students table structure
    echo "5. STUDENTS TABLE STRUCTURE:\n";
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'students' ORDER BY ordinal_position");
    foreach ($columns as $column) {
        echo "   - {$column->column_name}: {$column->data_type}\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
