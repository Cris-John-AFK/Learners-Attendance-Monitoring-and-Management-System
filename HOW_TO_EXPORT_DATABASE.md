# 📦 How to Export Your LAMMS Database for Groupmates

## 🎯 Quick Start

I've created a PHP script that will export your **ACTUAL database data** (just like the SQL file you showed me) so your groupmates can import it into their own database.

---

## 📋 Step-by-Step Instructions

### **Step 1: Configure the Export Script**

1. Open the file: `export_database.php`
2. Find this line:
   ```php
   $password = 'your_password_here';
   ```
3. Change it to your actual PostgreSQL password (the one you use in `.env`)

### **Step 2: Run the Export Script**

Open Command Prompt in your project folder and run:

```bash
cd "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms"
php export_database.php
```

### **Step 3: Get Your Export File**

The script will create a file named something like:
```
LAMMS_DATABASE_EXPORT_2025-10-21_114530.sql
```

This file contains **ALL your real data** from the database!

---

## 📤 What Gets Exported?

The script exports these tables in the correct order:

✅ **Users & Authentication**
- `users`
- `teachers`

✅ **Curriculum Structure**
- `curriculums`
- `grades`
- `curriculum_grade`
- `sections`
- `subjects`

✅ **Students**
- `student_details` (all 839+ students)
- `student_section` (student-section assignments)

✅ **Teacher Assignments**
- `teacher_section_subject`

✅ **Attendance System**
- `attendance_statuses`
- `attendance_sessions`
- `attendance_records`

✅ **Guardhouse System**
- `guardhouse_attendance` (QR scan records)
- `student_qr_codes`

✅ **Schedules & Reports**
- `subject_schedules`
- `class_schedules`
- `submitted_sf2_reports`

---

## 👥 For Your Groupmates

### **How to Import the Database**

**Step 1: Create Database**
```sql
CREATE DATABASE lamms_db;
```

**Step 2: Run Migrations First**
```bash
cd lamms-backend
php artisan migrate
```

**Step 3: Import the SQL File**

**Option A: Using pgAdmin**
1. Open pgAdmin
2. Right-click on `lamms_db` database
3. Select "Query Tool"
4. Click "Open File" and select the exported SQL file
5. Click "Execute" (▶️ button)

**Option B: Using Command Line**
```bash
psql -U postgres -d lamms_db -f LAMMS_DATABASE_EXPORT_2025-10-21_114530.sql
```

---

## 🔧 Troubleshooting

### **Error: "Connection failed"**
- Make sure PostgreSQL is running
- Check your password in the script
- Verify database name is correct

### **Error: "Table does not exist"**
- Run migrations first: `php artisan migrate`
- Make sure you're connected to the right database

### **Export file is too large**
- This is normal! Your database has 839+ students
- The file might be several MB
- You can compress it to a ZIP file before sharing

---

## 🌐 Multi-Device Setup (Bonus)

After your groupmate imports the database, they can access it from their device:

### **Option 1: Same WiFi Network (Recommended)**

**On YOUR computer (HOST):**
1. Find your IP address: `ipconfig` (look for IPv4)
2. Share your IP with groupmate (e.g., 192.168.1.100)

**On GROUPMATE's computer:**
1. Update their `.env` file:
   ```env
   VITE_API_BASE_URL=http://192.168.1.100:8000/api
   ```
2. Access your backend: `http://192.168.1.100:5173`

### **Option 2: Each Person Runs Their Own**

**Better for development:**
- Each person runs their own backend and frontend
- Everyone has the same data (from the export)
- No network configuration needed
- Each person can test independently

---

## 📊 Example Output

When you run the export script, you'll see:

```
✅ Connected to database successfully!
📊 Starting export...

📦 Exporting table: users
   📊 Found 25 rows
   ✅ Exported 25 rows

📦 Exporting table: teachers
   📊 Found 21 rows
   ✅ Exported 21 rows

📦 Exporting table: student_details
   📊 Found 839 rows
   ✅ Exported 839 rows

📦 Exporting table: guardhouse_attendance
   📊 Found 150 rows
   ✅ Exported 150 rows

✅ Export completed successfully!
📁 File saved to: LAMMS_DATABASE_EXPORT_2025-10-21_114530.sql
📊 File size: 2,450.75 KB

🎉 You can now share this file with your groupmates!
```

---

## 🎓 For Your Capstone Presentation

**Talking Points:**
- "We use PostgreSQL database with real production data"
- "Team members can sync database using export/import"
- "839 students with realistic Naawan, Misamis Oriental addresses"
- "Complete attendance history and guardhouse records"
- "Easy database sharing for collaborative development"

---

## 📞 Need Help?

If the export script doesn't work:

1. **Check PostgreSQL is running**
   - Open Services (Win + R → `services.msc`)
   - Find "postgresql-x64-15"
   - Make sure it's "Running"

2. **Verify your password**
   - Check `lamms-backend/.env` file
   - Copy the `DB_PASSWORD` value
   - Use that in the export script

3. **Check database name**
   - Default is `lamms_db`
   - If yours is different, update the script

---

## ✅ Summary

1. ✅ Edit `export_database.php` with your password
2. ✅ Run: `php export_database.php`
3. ✅ Share the generated SQL file with groupmates
4. ✅ They run migrations, then import the SQL file
5. ✅ Everyone has the same data!

**That's it! Your groupmates will have an exact copy of your database.** 🎉
