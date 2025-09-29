<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DepEdSchoolCalendarService;

class InitializeDepEdCalendar extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deped:init-calendar';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize DepEd School Calendar with holidays and school year';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initializing DepEd School Calendar System...');
        
        try {
            DepEdSchoolCalendarService::initializeDepEdCalendar();
            
            $this->info('✅ DepEd School Calendar initialized successfully!');
            $this->info('📅 School Year: 2024-2025');
            $this->info('🏫 School Days: Monday-Friday (excluding holidays)');
            $this->info('🎉 National holidays added');
            $this->info('📊 SF2-compliant attendance calculations enabled');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to initialize DepEd Calendar: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
