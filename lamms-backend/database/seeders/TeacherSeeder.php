<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 5 sample teachers
        $teachers = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'username' => 'johndoe',
                'password' => 'password123',
                'phone_number' => '123-456-7890',
                'address' => '123 Main St, City, Country',
                'gender' => 'male',
                'is_head_teacher' => true,
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'username' => 'janesmith',
                'password' => 'password123',
                'phone_number' => '234-567-8901',
                'address' => '456 Oak Ave, City, Country',
                'gender' => 'female',
                'is_head_teacher' => false,
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Johnson',
                'email' => 'michael.johnson@example.com',
                'username' => 'michaelj',
                'password' => 'password123',
                'phone_number' => '345-678-9012',
                'address' => '789 Pine St, City, Country',
                'gender' => 'male',
                'is_head_teacher' => false,
            ],
            [
                'first_name' => 'Emily',
                'last_name' => 'Brown',
                'email' => 'emily.brown@example.com',
                'username' => 'emilyb',
                'password' => 'password123',
                'phone_number' => '456-789-0123',
                'address' => '101 Maple Rd, City, Country',
                'gender' => 'female',
                'is_head_teacher' => false,
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Wilson',
                'email' => 'david.wilson@example.com',
                'username' => 'davidw',
                'password' => 'password123',
                'phone_number' => '567-890-1234',
                'address' => '202 Cedar Ln, City, Country',
                'gender' => 'male',
                'is_head_teacher' => false,
            ],
        ];

        foreach ($teachers as $teacherData) {
            // Create user account first
            $user = User::create([
                'username' => $teacherData['username'],
                'email' => $teacherData['email'],
                'password' => Hash::make($teacherData['password']),
                'role' => 'teacher',
                'is_active' => true,
            ]);

            // Create teacher profile
            Teacher::create([
                'user_id' => $user->id,
                'first_name' => $teacherData['first_name'],
                'last_name' => $teacherData['last_name'],
                'phone_number' => $teacherData['phone_number'],
                'address' => $teacherData['address'],
                'gender' => $teacherData['gender'],
                'is_head_teacher' => $teacherData['is_head_teacher'],
            ]);
        }

        $this->command->info('Sample teachers created!');
    }
}
