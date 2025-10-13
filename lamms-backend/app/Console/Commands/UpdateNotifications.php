<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateNotifications extends Command
{
    protected $signature = 'notifications:update-sections';
    protected $description = 'Update existing notifications to include section names';

    public function handle()
    {
        $this->info('Starting notification update...');

        try {
            // Get all session_completed notifications that don't have section names
            $notifications = DB::table('notifications')
                ->where('type', 'session_completed')
                ->whereRaw("message NOT LIKE '%-%-%'") // Find notifications without section format
                ->get();

            $this->info("Found " . count($notifications) . " notifications to update");

            $updated = 0;
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
                            
                            $this->info("Updated notification {$notification->id}: {$newMessage}");
                            $updated++;
                        }
                    }
                }
            }
            
            $this->info("Successfully updated {$updated} notifications!");
            
        } catch (\Exception $e) {
            $this->error("Error updating notifications: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
