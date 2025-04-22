<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Logic to cleanup duplicate curricula
        // In PostgreSQL we need to reference the aggregate expression directly in HAVING
        $duplicates = DB::select("
            SELECT name, start_year, end_year, COUNT(*) as count
            FROM curricula
            GROUP BY name, start_year, end_year
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $duplicate) {
            $records = DB::table('curricula')
                ->where('name', $duplicate->name)
                ->where('start_year', $duplicate->start_year)
                ->where('end_year', $duplicate->end_year)
                ->orderBy('id')
                ->get();

            // Keep the first record, remove the rest
            for ($i = 1; $i < count($records); $i++) {
                DB::table('curricula')->where('id', $records[$i]->id)->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably restore deleted duplicates
    }
};
