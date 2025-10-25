# 🎓 NCS LAMMS Seeders - Complete Summary

## ✅ Successfully Created!

### 📁 Seeder Files Created

1. **NCS_Teachers_LammsSeeder.php**
   - Location: `database/seeders/NCS_Teachers_LammsSeeder.php`
   - Creates: 44 teachers with sections and subjects

2. **NCS_Students_LammsSeeder.php**
   - Location: `database/seeders/NCS_Students_LammsSeeder.php`
   - Creates: 40 students (Grade 1 and Grade 4 only)

---

## 👨‍🏫 Teachers Created (44 Total)

### Kindergarten (6 Sections)
- **Kinder A (Generous AM)** - Teacher: Liza Santos
- **Kinder B (Generous PM)** - Teacher: Grace Dela Cruz
- **Kinder C (Good AM)** - Teacher: Arlene Villanueva
- **Kinder D (Good PM)** - Teacher: Mark Gutierrez
- **Kinder E (Great AM)** - Teacher: Jenny Ramos
- **Kinder F (Great PM)** - Teacher: Samuel Garcia

### Grade 1 (6 Sections)
- **Grade 1-A (Admirable)** - Teacher: Ana Lopez
- **Grade 1-B (Adorable)** - Teacher: Ruby Dizon
- **Grade 1-C (Affectionate)** - Teacher: Carlo Lim
- **Grade 1-D (Alert)** - Teacher: Melinda Cruz
- **Grade 1-E (Amazing)** - Teacher: Joy Gonzales
- **Grade 1-F (SNED GRADED)** - Teacher: Trisha Serrano

### Grade 2 (7 Sections)
- **Grade 2-A (Beloved)** - Teacher: Ramil Bautista
- **Grade 2-B (Beneficient)** - Teacher: Tess Morales
- **Grade 2-C (Benevolent)** - Teacher: Irene Torres
- **Grade 2-D (Blessed)** - Teacher: Albert Reyes
- **Grade 2-E (Blissful)** - Teacher: Fe Santos
- **Grade 2-F (Blossom)** - Teacher: Vincent Mercado
- **Grade 2-G (SNED-GRADE 2 DHH)** - Teacher: Ursula Perez

### Grade 3 (6 Sections)
- **Grade 3-A (Calm)** - Teacher: Lorna Diaz
- **Grade 3-B (Candor)** - Teacher: Jeric Mendoza
- **Grade 3-C (Charitable)** - Teacher: Rosemarie Tan
- **Grade 3-D (Cheerful)** - Teacher: Jessa Cruz
- **Grade 3-E (Clever)** - Teacher: Marvin Torres
- **Grade 3-G (Curious)** - Teacher: Kyle Dizon

### Grade 4 (7 Sections)
- **Grade 4-A (Dainty)** - Teacher: Claire Ramos
- **Grade 4-B (Dedicated)** - Teacher: Joseph Lim
- **Grade 4-C (Demure)** - Teacher: Anne Soriano
- **Grade 4-D (Devoted)** - Teacher: Joy Castillo
- **Grade 4-E (Dynamic)** - Teacher: Alvin Cortez
- **Grade 4-F (Diligent)** - Teacher: Tristan Santos
- **Grade 4-G (SNED GRADED)** - Teacher: William Perez

### Grade 5 (5 Sections)
- **Grade 5-A (Effective)** - Teacher: Lara Gutierrez
- **Grade 5-B (Efficient)** - Teacher: Marvin Cruz
- **Grade 5-C (Endurance)** - Teacher: Cherry Lopez
- **Grade 5-D (Energetic)** - Teacher: Fely Rivera
- **Grade 5-E (Everlasting)** - Teacher: Nico Fernandez

### Grade 6 (7 Sections)
- **Grade 6-A (Fair)** - Teacher: Joy Manalansan
- **Grade 6-B (Faithful)** - Teacher: Kenneth Tan
- **Grade 6-C (Flexible)** - Teacher: Lea Ramos
- **Grade 6-D (Forebearance)** - Teacher: Divine Garcia
- **Grade 6-E (Fortitude)** - Teacher: Patrick Lopez
- **Grade 6-F (Friendly)** - Teacher: Valerie Aquino
- **Grade 6-G (Fearless)** - Teacher: Wendy Ramos

---

## 👨‍🎓 Students Created (40 Total)

### Grade 1 - Admirable (16 Students)
**Teacher: Ana Lopez**

**Male Students (8):**
1. Adrian Dela Cruz
2. Aaron Villanueva
3. Benedict Santos
4. Carlo Ramirez
5. Daniel Gutierrez
6. Ethan Cruz
7. Francis Mendoza
8. Gabriel Lopez

**Female Students (8):**
1. Abigail Santos
2. Alexa Dela Cruz
3. Angelica Villanueva
4. Bianca Ramos
5. Camille Gutierrez
6. Danica Lopez
7. Erika Mendoza
8. Faith Morales

### Grade 4 - Devoted (24 Students)
**Teacher: Joy Castillo**

**Male Students (11):**
1. Henry Bautista
2. Ivan Torres
3. James Navarro
4. Kevin Ramos
5. Lance Flores
6. Mark Castillo
7. Nathan Diaz
8. Oliver Hernandez
9. Patrick Morales
10. Quinn Salazar
11. Ryan Dominguez

**Female Students (13):**
1. Gabrielle Castillo
2. Hazel Tan
3. Isabelle Reyes
4. Jasmine Gonzales
5. Katrina Bautista
6. Lara Dominguez
7. Monica Cabrera
8. Nicole Torres
9. Olivia Navarro
10. Pauline Rivera
11. Queenie Flores
12. Rose Hernandez
13. Sofia Lim

---

## 🔐 Login Credentials

### Admin Account
- **Username**: `admin`
- **Password**: `admin`
- **Login URL**: http://localhost:5173/admin-login

### Teacher Accounts
- **Username Format**: `firstname.lastname` (all lowercase)
- **Password**: `password123` (for all teachers)
- **Login URL**: http://localhost:5173/teacher-login

**Examples:**
- liza.santos / password123
- ana.lopez / password123
- joy.castillo / password123
- grace.dela cruz / password123

---

## 🚀 How to Run the Seeders

### Run Teachers Seeder Only
```bash
php artisan db:seed --class=NCS_Teachers_LammsSeeder
```

### Run Students Seeder Only
```bash
php artisan db:seed --class=NCS_Students_LammsSeeder
```

### Run Both Seeders
```bash
php artisan db:seed --class=NCS_Teachers_LammsSeeder
php artisan db:seed --class=NCS_Students_LammsSeeder
```

---

## 📊 Database Summary

### Created Records:
- ✅ **1 Admin User** (admin/admin)
- ✅ **44 Teachers** (all with password123)
- ✅ **44 Sections** (Kinder to Grade 6)
- ✅ **38 Subjects** (various subjects per grade level)
- ✅ **40 Students** (Grade 1 and Grade 4)
- ✅ **1 Curriculum** (MATATAG Curriculum 2025-2026)
- ✅ **7 Grade Levels** (K, G1, G2, G3, G4, G5, G6)

### Tables Populated:
- `users` - User accounts for login
- `admins` - Admin profile
- `teachers` - Teacher profiles
- `grades` - Grade levels (K-6)
- `curricula` - School curriculum
- `curriculum_grade` - Grade-curriculum relationships
- `sections` - Class sections
- `subjects` - Subject definitions
- `teacher_section_subject` - Teacher assignments
- `student_details` - Student information (detailed)
- `students` - Student records (basic)
- `student_section` - Student-section enrollments

---

## 📝 Notes

1. **Student Data**: Only Grade 1-Admirable and Grade 4-Devoted have students seeded. Other sections are empty and ready for manual data entry or additional seeding.

2. **LRN Numbers**: Students are assigned LRN (Learner Reference Numbers) starting from 100000000000.

3. **Subjects**: Each grade level has appropriate subjects based on DepEd curriculum:
   - Kindergarten: 8 subjects (Oral Communication, Reading, Writing, Math, Science, Music/Arts, GMRC, Mother Tongue)
   - Grade 1-2: 8 subjects (English, Filipino, Math, Science, AP, EsP, MAPEH, Mother Tongue)
   - Grade 3: 7 subjects (no Mother Tongue)
   - Grade 4-6: 8 subjects with grade level indicators

4. **Teacher Assignments**: All teachers are assigned as homeroom teachers to their respective sections with all subjects for that section.

5. **Email Format**: 
   - Teachers: firstname.lastname@msunaawan.edu.ph
   - Students: firstname.lastname@student.msunaawan.edu.ph

6. **Contact Numbers**: Auto-generated Philippine mobile numbers (09XXXXXXXXX)

7. **Address**: All users are from Naawan, Misamis Oriental, Philippines

---

## ✅ Ready to Use!

The system is now ready with:
- 1 admin account for system management
- 44 teachers ready to login and manage their classes
- 40 students enrolled in 2 sections
- Complete grade and subject structure
- Proper relationships and foreign keys

You can now login and test the attendance monitoring system! 🎉

---

**Created**: October 25, 2025  
**System**: Learners Attendance Monitoring and Management System (LAMMS)  
**School**: MSU Naawan Campus School
