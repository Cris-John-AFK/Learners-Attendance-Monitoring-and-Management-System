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
    
    // Check if teacherSectionSubjects relationship exists
    $teacher = App\Models\Teacher::first();
    if ($teacher) {
        echo "Found teacher: " . $teacher->first_name . " " . $teacher->last_name . "\n";
        
        // Try to access the relationship
        try {
            $assignments = $teacher->teacherSectionSubjects;
            echo "teacherSectionSubjects relationship works, found " . $assignments->count() . " assignments\n";
        } catch (Exception $e) {
            echo "ERROR with teacherSectionSubjects relationship: " . $e->getMessage() . "\n";
            
            // Check what relationships are available
            echo "\nChecking available relationships...\n";
            $reflection = new ReflectionClass($teacher);
            $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
            foreach ($methods as $method) {
                if (strpos($method->getName(), 'Section') !== false || strpos($method->getName(), 'Assignment') !== false) {
                    echo "- " . $method->getName() . "\n";
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
