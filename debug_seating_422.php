<?php
require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== DEBUGGING SEATING ARRANGEMENT 422 ERROR ===\n\n";

try {
    // 1. Check seating_arrangements table structure
    echo "1. Checking seating_arrangements table structure:\n";
    $columns = DB::select("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'seating_arrangements'");
    
    if (empty($columns)) {
        echo "   ✗ seating_arrangements table does not exist!\n";
        echo "   Creating table...\n";
        
        DB::statement("
            CREATE TABLE seating_arrangements (
                id SERIAL PRIMARY KEY,
                section_id INTEGER NOT NULL REFERENCES sections(id),
                subject_id INTEGER REFERENCES subjects(id),
                teacher_id INTEGER NOT NULL REFERENCES teachers(id),
                layout TEXT NOT NULL,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "   ✓ Table created\n";
    } else {
        echo "   ✓ Table exists with columns:\n";
        foreach ($columns as $col) {
            echo "     - {$col->column_name}: {$col->data_type} (" . ($col->is_nullable === 'YES' ? 'nullable' : 'not null') . ")\n";
        }
    }
    
    // 2. Test the validation that's failing
    echo "\n2. Testing validation requirements:\n";
    $testData = [
        'section_id' => 3,
        'subject_id' => 3,
        'teacher_id' => 3,
        'seating_layout' => [
            ['row' => 0, 'col' => 0, 'student' => ['id' => 1, 'name' => 'Test Student']]
        ]
    ];
    
    echo "   Test data structure:\n";
    echo "   - section_id: {$testData['section_id']} (exists: " . (DB::table('sections')->where('id', $testData['section_id'])->exists() ? 'Yes' : 'No') . ")\n";
    echo "   - subject_id: {$testData['subject_id']} (exists: " . (DB::table('subjects')->where('id', $testData['subject_id'])->exists() ? 'Yes' : 'No') . ")\n";
    echo "   - teacher_id: {$testData['teacher_id']} (exists: " . (DB::table('teachers')->where('id', $testData['teacher_id'])->exists() ? 'Yes' : 'No') . ")\n";
    echo "   - seating_layout: array with " . count($testData['seating_layout']) . " items\n";
    
    // 3. Check teacher assignment
    echo "\n3. Checking teacher assignment:\n";
    $assignment = DB::table('teacher_section_subject')
        ->where('teacher_id', $testData['teacher_id'])
        ->where('section_id', $testData['section_id'])
        ->where('is_active', true)
        ->first();
    
    if ($assignment) {
        echo "   ✓ Teacher {$testData['teacher_id']} has assignment to section {$testData['section_id']}\n";
        echo "     Subject: {$assignment->subject_id}, Role: {$assignment->role}\n";
    } else {
        echo "   ✗ No active assignment found for teacher {$testData['teacher_id']} in section {$testData['section_id']}\n";
    }
    
    // 4. Test the actual save operation
    echo "\n4. Testing save operation:\n";
    try {
        $result = DB::table('seating_arrangements')->updateOrInsert(
            [
                'section_id' => $testData['section_id'],
                'teacher_id' => $testData['teacher_id']
            ],
            [
                'layout' => json_encode($testData['seating_layout']),
                'subject_id' => null,
                'last_updated' => now()
            ]
        );
        echo "   ✓ Save operation successful\n";
    } catch (Exception $e) {
        echo "   ✗ Save operation failed: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
