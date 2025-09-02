<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class NaawaanTeachersSeeder extends Seeder
{
    /**
     * Seed teachers for Naawan Central School
     * Creates realistic teacher profiles for Filipino educators
     */
    public function run(): void
    {
        // Clear existing teachers and users to avoid duplicates (PostgreSQL compatible)
        DB::statement('TRUNCATE TABLE teachers RESTART IDENTITY CASCADE;');
        DB::statement('DELETE FROM users WHERE role = \'teacher\';');

        $teachers = [
            // KINDERGARTEN TEACHERS
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria.santos@naawan.edu.ph',
                'employee_id' => 'NCS-K001',
                'specialization' => 'Early Childhood Education',
                'department' => 'Kindergarten'
            ],
            [
                'first_name' => 'Ana',
                'last_name' => 'Cruz',
                'email' => 'ana.cruz@naawan.edu.ph',
                'employee_id' => 'NCS-K002',
                'specialization' => 'Early Childhood Education',
                'department' => 'Kindergarten'
            ],

            // PRIMARY TEACHERS (Grades 1-3)
            [
                'first_name' => 'Rosa',
                'last_name' => 'Garcia',
                'email' => 'rosa.garcia@naawan.edu.ph',
                'employee_id' => 'NCS-P001',
                'specialization' => 'Elementary Education',
                'department' => 'Primary'
            ],
            [
                'first_name' => 'Carmen',
                'last_name' => 'Reyes',
                'email' => 'carmen.reyes@naawan.edu.ph',
                'employee_id' => 'NCS-P002',
                'specialization' => 'Elementary Education',
                'department' => 'Primary'
            ],
            [
                'first_name' => 'Elena',
                'last_name' => 'Morales',
                'email' => 'elena.morales@naawan.edu.ph',
                'employee_id' => 'NCS-P003',
                'specialization' => 'Elementary Education',
                'department' => 'Primary'
            ],

            // INTERMEDIATE TEACHERS (Grades 4-6)
            [
                'first_name' => 'Roberto',
                'last_name' => 'Dela Cruz',
                'email' => 'roberto.delacruz@naawan.edu.ph',
                'employee_id' => 'NCS-I001',
                'specialization' => 'Mathematics',
                'department' => 'Intermediate'
            ],
            [
                'first_name' => 'Gloria',
                'last_name' => 'Villanueva',
                'email' => 'gloria.villanueva@naawan.edu.ph',
                'employee_id' => 'NCS-I002',
                'specialization' => 'Science',
                'department' => 'Intermediate'
            ],
            [
                'first_name' => 'Jose',
                'last_name' => 'Ramos',
                'email' => 'jose.ramos@naawan.edu.ph',
                'employee_id' => 'NCS-I003',
                'specialization' => 'Filipino Language',
                'department' => 'Intermediate'
            ],

            // JUNIOR HIGH SCHOOL TEACHERS (Grades 7-10)
            [
                'first_name' => 'Luz',
                'last_name' => 'Fernandez',
                'email' => 'luz.fernandez@naawan.edu.ph',
                'employee_id' => 'NCS-JH001',
                'specialization' => 'English Language',
                'department' => 'Junior High'
            ],
            [
                'first_name' => 'Pedro',
                'last_name' => 'Gonzales',
                'email' => 'pedro.gonzales@naawan.edu.ph',
                'employee_id' => 'NCS-JH002',
                'specialization' => 'Mathematics',
                'department' => 'Junior High'
            ],
            [
                'first_name' => 'Esperanza',
                'last_name' => 'Torres',
                'email' => 'esperanza.torres@naawan.edu.ph',
                'employee_id' => 'NCS-JH003',
                'specialization' => 'Science',
                'department' => 'Junior High'
            ],
            [
                'first_name' => 'Antonio',
                'last_name' => 'Mendoza',
                'email' => 'antonio.mendoza@naawan.edu.ph',
                'employee_id' => 'NCS-JH004',
                'specialization' => 'Araling Panlipunan',
                'department' => 'Junior High'
            ],

            // SENIOR HIGH SCHOOL TEACHERS (Grades 11-12)
            [
                'first_name' => 'Cristina',
                'last_name' => 'Aquino',
                'email' => 'cristina.aquino@naawan.edu.ph',
                'employee_id' => 'NCS-SH001',
                'specialization' => 'General Mathematics',
                'department' => 'Senior High'
            ],
            [
                'first_name' => 'Miguel',
                'last_name' => 'Rivera',
                'email' => 'miguel.rivera@naawan.edu.ph',
                'employee_id' => 'NCS-SH002',
                'specialization' => 'Physical Science',
                'department' => 'Senior High'
            ],
            [
                'first_name' => 'Teresita',
                'last_name' => 'Bautista',
                'email' => 'teresita.bautista@naawan.edu.ph',
                'employee_id' => 'NCS-SH003',
                'specialization' => 'Research',
                'department' => 'Senior High'
            ],

            // SPECIAL AREA TEACHERS
            [
                'first_name' => 'Ricardo',
                'last_name' => 'Pascual',
                'email' => 'ricardo.pascual@naawan.edu.ph',
                'employee_id' => 'NCS-PE001',
                'specialization' => 'Physical Education',
                'department' => 'Special Areas'
            ],
            [
                'first_name' => 'Melody',
                'last_name' => 'Santiago',
                'email' => 'melody.santiago@naawan.edu.ph',
                'employee_id' => 'NCS-MU001',
                'specialization' => 'Music',
                'department' => 'Special Areas'
            ],
            [
                'first_name' => 'Arturo',
                'last_name' => 'Valdez',
                'email' => 'arturo.valdez@naawan.edu.ph',
                'employee_id' => 'NCS-AR001',
                'specialization' => 'Arts',
                'department' => 'Special Areas'
            ],
            [
                'first_name' => 'Remedios',
                'last_name' => 'Castro',
                'email' => 'remedios.castro@naawan.edu.ph',
                'employee_id' => 'NCS-TLE001',
                'specialization' => 'Technology and Livelihood Education',
                'department' => 'Special Areas'
            ],

            // ADDITIONAL TEACHERS FOR COVERAGE
            [
                'first_name' => 'Benjamin',
                'last_name' => 'Flores',
                'email' => 'benjamin.flores@naawan.edu.ph',
                'employee_id' => 'NCS-GEN001',
                'specialization' => 'General Education',
                'department' => 'General'
            ],
            [
                'first_name' => 'Rosario',
                'last_name' => 'Herrera',
                'email' => 'rosario.herrera@naawan.edu.ph',
                'employee_id' => 'NCS-GEN002',
                'specialization' => 'General Education',
                'department' => 'General'
            ]
        ];

        $teachersCreated = 0;

        foreach ($teachers as $teacherData) {
            // Create user account for teacher
            $user = User::create([
                'username' => strtolower($teacherData['first_name'] . '.' . $teacherData['last_name']),
                'email' => $teacherData['email'],
                'password' => Hash::make('password123'), // Default password
                'email_verified_at' => now(),
                'role' => 'teacher'
            ]);

            // Create teacher profile
            Teacher::create([
                'user_id' => $user->id,
                'first_name' => $teacherData['first_name'],
                'last_name' => $teacherData['last_name'],
                'phone_number' => '+63 9' . rand(100000000, 999999999), // Random PH mobile
                'address' => 'Naawan, Misamis Oriental, Philippines',
                'date_of_birth' => now()->subYears(rand(25, 55))->format('Y-m-d'),
                'gender' => rand(0, 1) ? 'male' : 'female',
                'is_head_teacher' => false
            ]);

            $teachersCreated++;
        }

        $this->command->info('👨‍🏫 Naawan Central School teachers seeded successfully!');
        $this->command->info("📊 Total teachers created: {$teachersCreated}");
        $this->command->info('🔐 Default password for all teachers: password123');
        $this->command->info('📧 Email format: firstname.lastname@naawan.edu.ph');
    }
}
