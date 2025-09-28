<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // Naawan Central School Complete Seeding
            NaawaanGradesSeeder::class,
            NaawaanSubjectsSeeder::class,
            NaawaanCurriculumSeeder::class,
            NaawaanTeachersSeeder::class,
            NaawaanSectionsSeeder::class,
            CollectedReportSeeder::class,
            AttendanceSeeder::class,
        ]);
    }
}
