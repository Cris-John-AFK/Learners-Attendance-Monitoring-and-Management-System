<?php

namespace App\Console\Commands;

use App\Models\Curriculum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RemoveDuplicateCurricula extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'curricula:remove-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove duplicate curricula with the same year range';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Searching for duplicate curricula...');

        // Find curricula grouped by year range with count > 1
        $duplicates = DB::table('curricula')
            ->select('start_year', 'end_year', DB::raw('COUNT(*) as count'))
            ->groupBy('start_year', 'end_year')
            ->having(DB::raw('COUNT(*)'), '>', 1)
            ->get();

        if ($duplicates->isEmpty()) {
            $this->info('No duplicate curricula found.');
            return 0;
        }

        $this->info('Found ' . $duplicates->count() . ' year ranges with duplicates.');

        foreach ($duplicates as $duplicate) {
            $this->info("Processing duplicates for year range: {$duplicate->start_year}-{$duplicate->end_year}");

            // Get all curricula with this year range
            $curricula = Curriculum::where('start_year', $duplicate->start_year)
                ->where('end_year', $duplicate->end_year)
                ->orderBy('created_at', 'desc')
                ->get();

            // Keep the first one (most recent), delete the rest
            $keep = $curricula->shift();
            $this->info("Keeping curriculum ID {$keep->id} (created at {$keep->created_at})");

            foreach ($curricula as $curriculum) {
                $this->info("Deleting curriculum ID {$curriculum->id} (created at {$curriculum->created_at})");
                $curriculum->delete();
            }
        }

        $this->info('Successfully removed duplicate curricula.');
        return 0;
    }
}
