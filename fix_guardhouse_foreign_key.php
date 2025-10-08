<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Fixing guardhouse_attendance foreign key constraint...\n";
    
    // Drop the incorrect foreign key constraint
    DB::statement('ALTER TABLE guardhouse_attendance DROP CONSTRAINT IF EXISTS guardhouse_attendance_student_id_fkey');
    echo "✅ Dropped old foreign key constraint\n";
    
    // Add the correct foreign key constraint pointing to student_details table
    DB::statement('ALTER TABLE guardhouse_attendance ADD CONSTRAINT guardhouse_attendance_student_id_fkey FOREIGN KEY (student_id) REFERENCES student_details(id) ON DELETE CASCADE');
    echo "✅ Added correct foreign key constraint pointing to student_details table\n";
    
    // Verify the constraint exists
    $constraint = DB::select("
        SELECT constraint_name, table_name, column_name, foreign_table_name, foreign_column_name 
        FROM information_schema.key_column_usage 
        WHERE constraint_name = 'guardhouse_attendance_student_id_fkey'
    ");
    
    if (!empty($constraint)) {
        echo "✅ Foreign key constraint verified:\n";
        foreach ($constraint as $c) {
            echo "   - Constraint: {$c->constraint_name}\n";
            echo "   - Table: {$c->table_name}\n";
            echo "   - Column: {$c->column_name}\n";
            echo "   - References: {$c->foreign_table_name}({$c->foreign_column_name})\n";
        }
    }
    
    echo "\n🎉 Foreign key constraint fixed successfully!\n";
    echo "The guardhouse scanner should now work properly.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
