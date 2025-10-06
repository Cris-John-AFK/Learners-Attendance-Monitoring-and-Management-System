# 📤 How to Export Attendance Data for Your Groupmates

Since you're **NOT on the same WiFi**, your groupmates need the DATA itself (SQL file), not a live API.

Just like they sent you `NCS_PORTAL.sql`, you need to send them your attendance data.

---

## ✅ **EASIEST METHOD: Use pgAdmin** (Recommended!)

### Step 1: Open pgAdmin
1. Open pgAdmin 4 on your computer
2. Connect to your PostgreSQL server
3. Find your database: `sakai_lamms`

### Step 2: Export Specific Tables
1. Right-click on `sakai_lamms` database
2. Select **Backup...**
3. In the dialog:
   - **Filename:** `LAMMS_ATTENDANCE_DATA.sql`
   - **Format:** Plain
   - **Encoding:** UTF8
   
4. Go to **"Data/Objects"** tab
5. **Select only these tables** (uncheck others):
   - ✅ student_details
   - ✅ sections  
   - ✅ subjects
   - ✅ grades
   - ✅ attendance_sessions
   - ✅ attendance_records
   - ✅ attendance_statuses
   - ✅ student_section
   - ✅ teachers (if they need teacher info)

6. Click **Backup**

### Step 3: Find Your File
- The file will be saved where you specified
- It's named: `LAMMS_ATTENDANCE_DATA.sql`

---

## 🎯 **ALTERNATIVE: Use Command Line**

If you prefer command line, open **Command Prompt** and run:

```cmd
cd C:\Program Files\PostgreSQL\17\bin

pg_dump -U postgres -d sakai_lamms -t student_details -t sections -t subjects -t grades -t attendance_sessions -t attendance_records -t attendance_statuses -t student_section > C:\xampp\htdocs\CAPSTONE 2\sakai_lamms\LAMMS_ATTENDANCE_DATA.sql
```

When prompted, enter your PostgreSQL password.

---

## 📤 **What to Send to Groupmates**

### **Send Just 1 File:**
- `LAMMS_ATTENDANCE_DATA.sql`

### **With This Message:**

```
Hey team!

Here's the attendance data export from our LAMMS system.

📁 File: LAMMS_ATTENDANCE_DATA.sql

📊 What's Included:
- Student details (names, LRN, grade levels, etc.)
- Attendance sessions
- Attendance records (present/absent/late)
- Sections, subjects, grades info

💾 How to Import:

Method 1 - pgAdmin:
1. Open pgAdmin
2. Right-click your database → Restore
3. Select the SQL file
4. Click Restore

Method 2 - Command Line:
psql -U postgres -d your_database < LAMMS_ATTENDANCE_DATA.sql

📝 Tables Included:
- student_details (student information)
- attendance_sessions (attendance sessions)
- attendance_records (individual attendance marks)
- attendance_statuses (Present/Absent/Late/Excused)
- sections, subjects, grades (supporting data)
- student_section (student-section relationships)

Let me know if you need help!
```

---

## 🔄 **If They Want Regular Updates**

Since you're not on same WiFi, you have these options:

### **Option A: Export & Send Periodically**
- Export new SQL file weekly/monthly
- Send via email/Google Drive
- They import to get latest data

### **Option B: Deploy Your API Online** (Advanced)
If you want them to have live access:
1. Deploy your backend to a cloud service:
   - **Free options:** Railway.app, Render.com, Heroku
   - **Paid:** AWS, DigitalOcean
   
2. They access via public URL:
   ```
   https://your-api.railway.app/api/shared/attendance/students
   ```

### **Option C: Share Database Online**
- Use **Supabase** (free PostgreSQL hosting)
- Upload your data there
- Give them read-only access

---

## ❓ **FAQ**

**Q: What's the difference between SQL file and API?**
- **SQL File:** One-time data copy. They get a snapshot. Need to re-export for updates.
- **API:** Live connection. They get real-time data. Requires same network or internet hosting.

**Q: Which method is better?**
- **Not same WiFi:** Use SQL file (what they sent you)
- **Same WiFi:** Use API (what we built earlier)
- **Need live data:** Deploy API online

**Q: How big will the SQL file be?**
- Depends on data size
- For 800+ students with attendance: ~5-20 MB
- Still easy to send via email/Drive

**Q: Do they need my whole database?**
- No! Just attendance tables
- That's why we only export specific tables

---

## ✅ **Quick Steps Summary**

1. **Open pgAdmin**
2. **Backup database** → Select only attendance tables
3. **Save as:** `LAMMS_ATTENDANCE_DATA.sql`
4. **Send file** to groupmates via email/Drive
5. **Done!** They import into their database

---

**Choose the pgAdmin method - it's the easiest and safest!** ✨
