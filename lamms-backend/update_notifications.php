<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Schema\Blueprint;

// Database configuration
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'lamms_db',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "Starting notification update...\n";

try {
    // Get all session_completed notifications that don't have section names
    $notifications = DB::table('notifications')
        ->where('type', 'session_completed')
        ->whereRaw("message NOT LIKE '%-%-%'") // Find notifications without section format
        ->get();

    echo "Found " . count($notifications) . " notifications to update\n";

    foreach ($notifications as $notification) {
        $data = json_decode($notification->data, true);
        
        if (isset($data['section_id'])) {
            // Get section name from sections table
            $section = DB::table('sections')
                ->where('id', $data['section_id'])
                ->first();
            
            if ($section) {
                // Parse current message to extract subject and stats
                $currentMessage = $notification->message;
                
                // Extract subject name and stats from current message
                if (preg_match('/^(.+?) - (\d+ present, \d+ absent)$/', $currentMessage, $matches)) {
                    $subjectName = $matches[1];
                    $stats = $matches[2];
                    
                    // Create new message with section
                    $newMessage = "{$subjectName} - {$section->name} - {$stats}";
                    
                    // Update the notification
                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update([
                            'message' => $newMessage,
                            'updated_at' => now()
                        ]);
                    
                    echo "Updated notification {$notification->id}: {$newMessage}\n";
                }
            }
        }
    }
    
    echo "Notification update completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error updating notifications: " . $e->getMessage() . "\n";
}
