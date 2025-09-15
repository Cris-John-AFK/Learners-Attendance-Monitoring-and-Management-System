<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    // Load Laravel environment
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    // First, check what tables exist
    echo "Checking available tables...\n";
    $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
    foreach ($tables as $table) {
        if (strpos($table->tablename, 'teacher') !== false || strpos($table->tablename, 'assignment') !== false) {
            echo "- " . $table->tablename . "\n";
        }
    }
    
    echo "\nChecking Maria Santos...\n";
    
    // Query Maria Santos basic info
    $teacher = DB::table('teachers')
        ->where('first_name', 'Maria')
        ->where('last_name', 'Santos')
        ->first();
    
    if ($teacher) {
        echo "Maria Santos found:\n";
        echo "ID: " . $teacher->id . "\n";
        echo "Name: " . $teacher->first_name . " " . $teacher->last_name . "\n";
        echo "Email: " . $teacher->email . "\n";
        
        // Check for section assignments in different possible tables
        $possibleTables = ['teacher_section_assignments', 'section_teachers', 'assignments'];
        
        foreach ($possibleTables as $tableName) {
            try {
                $exists = DB::select("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = '$tableName')")[0]->exists;
                if ($exists) {
                    echo "\nChecking $tableName table...\n";
                    $assignments = DB::table($tableName)
                        ->where('teacher_id', $teacher->id)
                        ->get();
                    
                    if ($assignments->count() > 0) {
                        echo "Found " . $assignments->count() . " assignments in $tableName:\n";
                        foreach ($assignments as $assignment) {
                            echo "- Assignment: " . json_encode($assignment) . "\n";
                        }
                    } else {
                        echo "No assignments found in $tableName\n";
                    }
                }
            } catch (Exception $e) {
                // Table doesn't exist, continue
            }
        }
        
    } else {
        echo "Maria Santos not found in database\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
