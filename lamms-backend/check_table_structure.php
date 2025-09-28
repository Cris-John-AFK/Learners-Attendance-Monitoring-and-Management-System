<?php

require_once 'vendor/autoload.php';

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Checking Table Structures for Index Creation\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// Tables to check
$tables = [
    'student_details',
    'teacher_section_subject', 
    'attendances',
    'student_qr_codes',
    'guardhouse_attendance'
];

foreach ($tables as $table) {
    try {
        echo "📊 Table: {$table}\n";
        echo "-" . str_repeat("-", 30) . "\n";
        
        $columns = DB::select("
            SELECT column_name, data_type, is_nullable 
            FROM information_schema.columns 
            WHERE table_name = ? 
            ORDER BY ordinal_position
        ", [$table]);
        
        foreach ($columns as $column) {
            $nullable = $column->is_nullable === 'YES' ? '(nullable)' : '(not null)';
            echo "   • {$column->column_name} ({$column->data_type}) {$nullable}\n";
        }
        
        echo "\n";
        
    } catch (Exception $e) {
        echo "❌ Error checking table {$table}: " . $e->getMessage() . "\n\n";
    }
}

echo "✅ Table structure check complete!\n";
