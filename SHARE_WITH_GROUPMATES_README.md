# 📚 LAMMS Attendance API - Quick Start Guide for Groupmates

## 🎯 What is This?

This is a **ready-to-use API** that provides access to student attendance records and details from the LAMMS (Learning Attendance Monitoring and Management System). You can use this API to:

- Get all students with their attendance records
- Get specific student details and attendance history
- Get attendance summary statistics
- Get daily attendance reports

---

## 🚀 Quick Start (3 Easy Steps)

### Step 1: Make Sure Backend is Running

```bash
cd lamms-backend
php artisan serve
```

The API should be running at: `http://localhost:8000`

### Step 2: Test the API

**Option A: Use the Visual Test Page (Easiest)**
1. Open `api_test_page.html` in your browser
2. Click on any endpoint to expand it
3. Fill in the parameters (or use defaults)
4. Click "🚀 Test API" button
5. View the JSON response

**Option B: Use Postman**
1. Open Postman
2. Import `LAMMS_Shared_API_Postman_Collection.json`
3. Run any request from the collection

**Option C: Use cURL in Terminal**
```bash
curl http://localhost:8000/api/shared/attendance/students?date_from=2025-09-01&date_to=2025-10-06
```

### Step 3: Read the Documentation

Open `SHARED_API_DOCUMENTATION.md` for complete API reference with:
- All endpoint details
- Request/response examples
- Query parameters
- Code examples in JavaScript, Python, PHP, cURL

---

## 📋 Available API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/shared/attendance/students` | GET | Get all students with attendance records |
| `/api/shared/attendance/students/{id}` | GET | Get specific student details |
| `/api/shared/attendance/summary` | GET | Get attendance statistics summary |
| `/api/shared/attendance/daily` | GET | Get daily attendance report |

---

## 💡 Quick Examples

### Example 1: Get All Students (JavaScript)

```javascript
fetch('http://localhost:8000/api/shared/attendance/students?date_from=2025-09-01&date_to=2025-10-06')
  .then(response => response.json())
  .then(data => {
    console.log('Total Students:', data.meta.total_students);
    data.data.forEach(student => {
      console.log(`${student.name}: ${student.attendance_summary.attendance_rate}%`);
    });
  });
```

### Example 2: Get Specific Student (Python)

```python
import requests

response = requests.get('http://localhost:8000/api/shared/attendance/students/14')
data = response.json()

if data['success']:
    student = data['data']['student_info']
    summary = data['data']['attendance_summary']
    print(f"{student['name']}: {summary['attendance_rate']}% attendance")
```

### Example 3: Get Daily Attendance (PHP)

```php
$url = 'http://localhost:8000/api/shared/attendance/daily?date=2025-10-06';
$response = file_get_contents($url);
$data = json_decode($response, true);

foreach ($data['data'] as $record) {
    echo $record['student_name'] . ': ' . $record['status'] . "\n";
}
```

---

## 📊 Response Format

All endpoints return JSON in this format:

```json
{
  "success": true,
  "data": { ... },
  "meta": { ... },
  "generated_at": "2025-10-06 22:16:00"
}
```

### Student Data Structure

Each student object includes:

```json
{
  "student_id": 14,
  "student_number": "2024-0001",
  "lrn": "123456789012",
  "name": "Juan Dela Cruz",
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "grade_level": "Grade 1",
  "section": "Section A",
  "email": "juan@example.com",
  "gender": "Male",
  "attendance_summary": {
    "total_records": 45,
    "present": 40,
    "absent": 3,
    "late": 2,
    "attendance_rate": 88.89
  },
  "attendance_records": [ ... ]
}
```

---

## 🔍 Common Query Parameters

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `date_from` | Date (YYYY-MM-DD) | Start date for records | 30 days ago |
| `date_to` | Date (YYYY-MM-DD) | End date for records | Today |
| `section_id` | Number | Filter by section | All sections |
| `grade_level` | String | Filter by grade | All grades |

---

## 🎨 Attendance Status Codes

| Code | Status | Description |
|------|--------|-------------|
| **P** | Present | Student attended |
| **A** | Absent | Student was absent |
| **L** | Late | Student arrived late |
| **E** | Excused | Excused absence |

---

## 📁 Files Included

1. **SHARED_API_DOCUMENTATION.md** - Complete API documentation
2. **LAMMS_Shared_API_Postman_Collection.json** - Postman collection for testing
3. **api_test_page.html** - Visual testing interface (open in browser)
4. **lamms-backend/app/Http/Controllers/API/SharedAttendanceController.php** - Backend controller
5. **This README** - Quick start guide

---

## 🛠️ Integration Examples

### For Web Applications

```html
<script>
async function getStudentAttendance(studentId) {
  const response = await fetch(`http://localhost:8000/api/shared/attendance/students/${studentId}`);
  const data = await response.json();
  return data;
}

// Usage
getStudentAttendance(14).then(data => {
  console.log(data.data.student_info);
  console.log(data.data.attendance_summary);
});
</script>
```

### For Mobile Apps (React Native)

```javascript
import axios from 'axios';

const getAttendanceSummary = async () => {
  try {
    const response = await axios.get('http://localhost:8000/api/shared/attendance/summary', {
      params: {
        date_from: '2025-09-01',
        date_to: '2025-10-06'
      }
    });
    return response.data;
  } catch (error) {
    console.error('Error fetching attendance:', error);
  }
};
```

### For Data Analysis (Python/Pandas)

```python
import pandas as pd
import requests

# Get all students
response = requests.get('http://localhost:8000/api/shared/attendance/students')
data = response.json()

# Convert to DataFrame
students_df = pd.DataFrame(data['data'])

# Analyze attendance rates
print(students_df[['name', 'attendance_summary']].head())
```

---

## ❓ FAQ

### Q: Do I need authentication?
**A:** Currently, no authentication is required. This is a shared API for your groupmates.

### Q: Can I filter by multiple parameters?
**A:** Yes! Combine parameters like `?date_from=2025-09-01&section_id=1&grade_level=Grade 1`

### Q: What if a student has no attendance records?
**A:** The API will still return the student info with empty attendance records array.

### Q: Can I export data to Excel/CSV?
**A:** Yes! Get the JSON data and convert it using your preferred tool or library.

### Q: Is there a rate limit?
**A:** Currently no rate limiting, but please be reasonable with API calls.

---

## 🐛 Troubleshooting

**Problem: "Connection refused" or can't connect**
- Solution: Make sure Laravel backend is running (`php artisan serve`)

**Problem: "404 Not Found"**
- Solution: Check the URL and make sure route is correct

**Problem: Empty data returned**
- Solution: Check your date range and filters, verify data exists in database

**Problem: CORS errors in browser**
- Solution: Check Laravel CORS configuration in `config/cors.php`

---

## 📞 Support

If you encounter issues:
1. Check the Laravel logs: `lamms-backend/storage/logs/laravel.log`
2. Test endpoints using the test page or Postman
3. Verify database has attendance data
4. Contact the system administrator

---

## 🎓 Additional Resources

- **Full Documentation:** `SHARED_API_DOCUMENTATION.md`
- **Postman Collection:** `LAMMS_Shared_API_Postman_Collection.json`
- **Test Interface:** `api_test_page.html`
- **Backend Code:** `lamms-backend/app/Http/Controllers/API/SharedAttendanceController.php`

---

## ✅ Checklist for Groupmates

- [ ] Backend is running (`php artisan serve`)
- [ ] Opened `api_test_page.html` and tested at least one endpoint
- [ ] Read `SHARED_API_DOCUMENTATION.md` for endpoint details
- [ ] Imported Postman collection (optional)
- [ ] Successfully retrieved student data
- [ ] Understand the response format
- [ ] Know how to filter by date, section, and grade level

---

**Happy Coding! 🚀**

Last Updated: October 6, 2025
