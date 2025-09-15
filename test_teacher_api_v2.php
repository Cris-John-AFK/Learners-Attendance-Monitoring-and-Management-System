<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    // Load Laravel environment
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "Testing TeacherController index method...\n";
    
    // Test the relationship that's causing issues
    echo "\nChecking Teacher model relationships...\n";
    
    // Check if assignments relationship exists
    $teacher = App\Models\Teacher::first();
    if ($teacher) {
        echo "Found teacher: " . $teacher->first_name . " " . $teacher->last_name . "\n";
        
        // Try to access the assignments relationship
        try {
            $assignments = $teacher->assignments;
            echo "assignments relationship works, found " . $assignments->count() . " assignments\n";
            
            foreach ($assignments as $assignment) {
                echo "- Assignment ID: " . $assignment->id . ", Role: " . ($assignment->role ?? 'N/A') . "\n";
                echo "  Section: " . ($assignment->section->name ?? 'N/A') . "\n";
                echo "  Subject: " . ($assignment->subject->name ?? 'N/A') . "\n";
            }
            
        } catch (Exception $e) {
            echo "ERROR with assignments relationship: " . $e->getMessage() . "\n";
        }
        
        // Now test the TeacherController directly
        echo "\nTesting TeacherController index method directly...\n";
        try {
            $controller = new App\Http\Controllers\API\TeacherController();
            $response = $controller->index();
            $data = $response->getData();
            echo "Controller returned " . count($data) . " teachers\n";
            
            if (count($data) > 0) {
                $firstTeacher = $data[0];
                echo "First teacher: " . $firstTeacher->first_name . " " . $firstTeacher->last_name . "\n";
                echo "Has primary_assignment: " . (isset($firstTeacher->primary_assignment) ? 'Yes' : 'No') . "\n";
                if (isset($firstTeacher->primary_assignment) && $firstTeacher->primary_assignment) {
                    echo "Primary assignment section: " . $firstTeacher->primary_assignment->section->name . "\n";
                }
            }
            
        } catch (Exception $e) {
            echo "ERROR in TeacherController: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
