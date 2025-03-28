<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define the standard Philippine K-12 grade levels
        $grades = [
            [
                'code' => 'K1',
                'name' => 'Kinder 1',
                'description' => 'First year of Kindergarten',
                'display_order' => 1,
                'is_active' => true
            ],
            [
                'code' => 'K2',
                'name' => 'Kinder 2',
                'description' => 'Second year of Kindergarten',
                'display_order' => 2,
                'is_active' => true
            ],
            [
                'code' => '1',
                'name' => 'Grade 1',
                'description' => 'First Grade - Elementary',
                'display_order' => 3,
                'is_active' => true
            ],
            [
                'code' => '2',
                'name' => 'Grade 2',
                'description' => 'Second Grade - Elementary',
                'display_order' => 4,
                'is_active' => true
            ],
            [
                'code' => '3',
                'name' => 'Grade 3',
                'description' => 'Third Grade - Elementary',
                'display_order' => 5,
                'is_active' => true
            ],
            [
                'code' => '4',
                'name' => 'Grade 4',
                'description' => 'Fourth Grade - Elementary',
                'display_order' => 6,
                'is_active' => true
            ],
            [
                'code' => '5',
                'name' => 'Grade 5',
                'description' => 'Fifth Grade - Elementary',
                'display_order' => 7,
                'is_active' => true
            ],
            [
                'code' => '6',
                'name' => 'Grade 6',
                'description' => 'Sixth Grade - Elementary',
                'display_order' => 8,
                'is_active' => true
            ],
            [
                'code' => 'ALS',
                'name' => 'Alternative Learning System',
                'description' => 'Alternative Learning System for out-of-school youth and adults',
                'display_order' => 9,
                'is_active' => true
            ]
        ];

        // Insert the grades
        foreach ($grades as $grade) {
            Grade::updateOrCreate(
                ['code' => $grade['code']],
                $grade
            );
        }
    }
}
