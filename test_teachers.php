<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel app
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Teacher;
use App\Models\User;

echo "Testing Teacher model...\n\n";

try {
    // Test basic teacher count
    $teacherCount = Teacher::count();
    echo "Total teachers: $teacherCount\n";

    // Test user count
    $userCount = User::count();
    echo "Total users: $userCount\n\n";

    // Test lightweight teacher query (same as controller)
    echo "Testing lightweight teacher query...\n";
    $teachers = Teacher::select(['id', 'first_name', 'last_name', 'phone_number', 'user_id'])
        ->with(['user:id,email,username,is_active'])
        ->get()
        ->map(function ($teacher) {
            return [
                'id' => $teacher->id,
                'first_name' => $teacher->first_name,
                'last_name' => $teacher->last_name,
                'phone_number' => $teacher->phone_number,
                'email' => $teacher->user->email ?? null,
                'username' => $teacher->user->username ?? null,
                'is_active' => $teacher->user->is_active ?? false,
            ];
        });

    echo "Found " . $teachers->count() . " teachers:\n";
    foreach ($teachers as $teacher) {
        echo "- {$teacher['first_name']} {$teacher['last_name']} (ID: {$teacher['id']})\n";
        echo "  Email: {$teacher['email']}\n";
        echo "  Active: " . ($teacher['is_active'] ? 'Yes' : 'No') . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
