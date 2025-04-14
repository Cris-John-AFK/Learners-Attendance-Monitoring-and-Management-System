<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get duplicate start_year/end_year combinations
        $duplicates = DB::select("
            SELECT start_year, end_year
            FROM curricula
            GROUP BY start_year, end_year
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $duplicate) {
            // For each duplicate combination, keep the one with the highest ID (presumably newest)
            // and delete the others
            $maxId = DB::table('curricula')
                ->where('start_year', $duplicate->start_year)
                ->where('end_year', $duplicate->end_year)
                ->max('id');

            DB::table('curricula')
                ->where('start_year', $duplicate->start_year)
                ->where('end_year', $duplicate->end_year)
                ->where('id', '<>', $maxId)
                ->delete();

            Log::info("Removed duplicate curricula for year range {$duplicate->start_year}-{$duplicate->end_year}, kept ID: {$maxId}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Can't reverse this migration (deleted data can't be restored)
    }
};
