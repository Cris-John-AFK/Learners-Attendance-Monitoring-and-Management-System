# LAMMS Shared Attendance API Documentation

## Overview
This API provides access to student attendance records and details for external integrations. Share this documentation with your groupmates who need access to attendance data.

**Base URL:** `http://localhost:8000/api/shared/attendance`

---

## API Endpoints

### 1. Get All Students with Attendance Records

**Endpoint:** `GET /api/shared/attendance/students`

**Description:** Retrieve all students with their complete details and attendance records.

**Query Parameters:**
- `date_from` (optional) - Start date for attendance records (YYYY-MM-DD). Default: 30 days ago
- `date_to` (optional) - End date for attendance records (YYYY-MM-DD). Default: today
- `section_id` (optional) - Filter by specific section ID
- `grade_level` (optional) - Filter by grade level (e.g., "Kinder One", "Grade 1")

**Example Request:**
```bash
GET http://localhost:8000/api/shared/attendance/students?date_from=2025-09-01&date_to=2025-10-06&section_id=1
```

**Example Response:**
```json
{
  "success": true,
  "data": [
    {
      "student_id": 14,
      "student_number": "2024-0001",
      "lrn": "123456789012",
      "name": "Juan Dela Cruz",
      "first_name": "Juan",
      "middle_name": "Santos",
      "last_name": "Dela Cruz",
      "grade_level": "Grade 1",
      "section": "Section A",
      "email": "juan.delacruz@example.com",
      "gender": "Male",
      "birthdate": "2010-05-15",
      "contact_number": "09123456789",
      "parent_contact": "09198765432",
      "address": "123 Main Street, City",
      "attendance_summary": {
        "total_records": 45,
        "present": 40,
        "absent": 3,
        "late": 2,
        "excused": 0,
        "attendance_rate": 88.89,
        "date_range": {
          "from": "2025-09-01",
          "to": "2025-10-06"
        }
      },
      "attendance_records": [
        {
          "session_date": "2025-10-06",
          "arrival_time": "08:00:00",
          "departure_time": "15:00:00",
          "status": "Present",
          "status_code": "P",
          "subject_name": "Mathematics",
          "section_name": "Grade 1 - Section A",
          "remarks": null
        }
      ]
    }
  ],
  "meta": {
    "total_students": 25,
    "date_range": {
      "from": "2025-09-01",
      "to": "2025-10-06"
    },
    "filters": {
      "section_id": 1,
      "grade_level": null
    }
  },
  "generated_at": "2025-10-06 22:12:00"
}
```

---

### 2. Get Specific Student Details with Attendance

**Endpoint:** `GET /api/shared/attendance/students/{studentId}`

**Description:** Retrieve detailed information for a specific student including complete attendance history.

**Path Parameters:**
- `studentId` (required) - The student's ID

**Query Parameters:**
- `date_from` (optional) - Start date (YYYY-MM-DD). Default: 3 months ago
- `date_to` (optional) - End date (YYYY-MM-DD). Default: today

**Example Request:**
```bash
GET http://localhost:8000/api/shared/attendance/students/14?date_from=2025-07-01&date_to=2025-10-06
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "student_info": {
      "student_id": 14,
      "student_number": "2024-0001",
      "lrn": "123456789012",
      "name": "Juan Dela Cruz",
      "first_name": "Juan",
      "middle_name": "Santos",
      "last_name": "Dela Cruz",
      "grade_level": "Grade 1",
      "section": "Section A",
      "email": "juan.delacruz@example.com",
      "gender": "Male",
      "birthdate": "2010-05-15",
      "age": 15,
      "contact_number": "09123456789",
      "parent_name": "Maria Dela Cruz",
      "parent_contact": "09198765432",
      "address": "123 Main Street, City",
      "enrollment_date": "2025-06-15",
      "status": "enrolled"
    },
    "attendance_summary": {
      "total_records": 85,
      "present": 75,
      "absent": 6,
      "late": 4,
      "excused": 0,
      "attendance_rate": 88.24,
      "consecutive_absences": 2,
      "date_range": {
        "from": "2025-07-01",
        "to": "2025-10-06"
      }
    },
    "attendance_records": [
      {
        "session_date": "2025-10-06",
        "arrival_time": "08:00:00",
        "departure_time": "15:00:00",
        "status": "Present",
        "status_code": "P",
        "subject_name": "Mathematics",
        "section_name": "Grade 1 - Section A",
        "teacher_name": "Maria Santos",
        "remarks": null,
        "marked_at": "2025-10-06 08:05:00"
      }
    ]
  },
  "generated_at": "2025-10-06 22:12:00"
}
```

---

### 3. Get Attendance Summary Statistics

**Endpoint:** `GET /api/shared/attendance/summary`

**Description:** Get overall attendance statistics and summary data.

**Query Parameters:**
- `date_from` (optional) - Start date (YYYY-MM-DD). Default: 30 days ago
- `date_to` (optional) - End date (YYYY-MM-DD). Default: today
- `section_id` (optional) - Filter by section
- `grade_level` (optional) - Filter by grade level

**Example Request:**
```bash
GET http://localhost:8000/api/shared/attendance/summary?date_from=2025-09-01&date_to=2025-10-06
```

**Example Response:**
```json
{
  "success": true,
  "data": {
    "total_records": 1250,
    "total_present": 1100,
    "total_absent": 80,
    "total_late": 60,
    "total_excused": 10,
    "unique_students": 25,
    "total_days": 35,
    "attendance_rate": 88.00,
    "date_range": {
      "from": "2025-09-01",
      "to": "2025-10-06"
    },
    "filters": {
      "section_id": null,
      "grade_level": null
    }
  },
  "generated_at": "2025-10-06 22:12:00"
}
```

---

### 4. Get Daily Attendance Report

**Endpoint:** `GET /api/shared/attendance/daily`

**Description:** Get attendance records for a specific date.

**Query Parameters:**
- `date` (optional) - Date to retrieve (YYYY-MM-DD). Default: today
- `section_id` (optional) - Filter by section

**Example Request:**
```bash
GET http://localhost:8000/api/shared/attendance/daily?date=2025-10-06&section_id=1
```

**Example Response:**
```json
{
  "success": true,
  "data": [
    {
      "student_id": 14,
      "student_name": "Juan Dela Cruz",
      "lrn": "123456789012",
      "gradeLevel": "Grade 1",
      "section_name": "Grade 1 - Section A",
      "subject_name": "Mathematics",
      "status": "Present",
      "status_code": "P",
      "arrival_time": "08:00:00",
      "departure_time": "15:00:00",
      "remarks": null
    }
  ],
  "meta": {
    "date": "2025-10-06",
    "total_records": 25,
    "section_id": 1
  },
  "generated_at": "2025-10-06 22:12:00"
}
```

---

## Status Codes

### Attendance Status Codes
- **P** - Present
- **A** - Absent
- **L** - Late
- **E** - Excused

### HTTP Status Codes
- **200** - Success
- **404** - Student not found
- **500** - Server error

---

## Error Response Format

```json
{
  "success": false,
  "message": "Failed to retrieve student attendance data",
  "error": "Detailed error message"
}
```

---

## Usage Examples

### JavaScript/Fetch
```javascript
// Get all students with attendance
fetch('http://localhost:8000/api/shared/attendance/students?date_from=2025-09-01&date_to=2025-10-06')
  .then(response => response.json())
  .then(data => {
    console.log('Students:', data.data);
    console.log('Total Students:', data.meta.total_students);
  })
  .catch(error => console.error('Error:', error));

// Get specific student
fetch('http://localhost:8000/api/shared/attendance/students/14')
  .then(response => response.json())
  .then(data => {
    console.log('Student Info:', data.data.student_info);
    console.log('Attendance Rate:', data.data.attendance_summary.attendance_rate);
  });
```

### Python/Requests
```python
import requests

# Get all students with attendance
url = 'http://localhost:8000/api/shared/attendance/students'
params = {
    'date_from': '2025-09-01',
    'date_to': '2025-10-06',
    'section_id': 1
}

response = requests.get(url, params=params)
data = response.json()

if data['success']:
    for student in data['data']:
        print(f"{student['name']}: {student['attendance_summary']['attendance_rate']}%")
```

### cURL
```bash
# Get all students
curl -X GET "http://localhost:8000/api/shared/attendance/students?date_from=2025-09-01&date_to=2025-10-06"

# Get specific student
curl -X GET "http://localhost:8000/api/shared/attendance/students/14"

# Get daily attendance
curl -X GET "http://localhost:8000/api/shared/attendance/daily?date=2025-10-06"
```

### PHP
```php
<?php
// Get all students with attendance
$url = 'http://localhost:8000/api/shared/attendance/students';
$params = http_build_query([
    'date_from' => '2025-09-01',
    'date_to' => '2025-10-06',
    'section_id' => 1
]);

$response = file_get_contents($url . '?' . $params);
$data = json_decode($response, true);

if ($data['success']) {
    foreach ($data['data'] as $student) {
        echo $student['name'] . ': ' . $student['attendance_summary']['attendance_rate'] . "%\n";
    }
}
?>
```

---

## Data Fields Reference

### Student Information Fields
- `student_id` - Unique student identifier
- `student_number` - Student number/ID
- `lrn` - Learner Reference Number
- `name` - Full name
- `first_name` - First name
- `middle_name` - Middle name
- `last_name` - Last name
- `grade_level` - Current grade level
- `section` - Current section
- `email` - Email address
- `gender` - Gender
- `birthdate` - Date of birth (YYYY-MM-DD)
- `age` - Current age
- `contact_number` - Student contact number
- `parent_name` - Parent/Guardian name
- `parent_contact` - Parent/Guardian contact
- `address` - Current address
- `enrollment_date` - Enrollment date
- `status` - Enrollment status

### Attendance Record Fields
- `session_date` - Date of attendance session
- `arrival_time` - Time student arrived
- `departure_time` - Time student departed
- `status` - Full status name (Present, Absent, Late, Excused)
- `status_code` - Status code (P, A, L, E)
- `subject_name` - Subject name
- `section_name` - Section name
- `teacher_name` - Teacher's name
- `remarks` - Additional notes
- `marked_at` - Timestamp when attendance was marked

---

## Notes for Developers

1. **Date Format:** All dates should be in YYYY-MM-DD format
2. **Default Date Ranges:** 
   - Most endpoints default to 30 days of data
   - Student details endpoint defaults to 3 months
3. **Performance:** For large datasets, use date ranges and filters to limit results
4. **Caching:** Consider implementing client-side caching for frequently accessed data
5. **Rate Limiting:** Currently no rate limiting, but be considerate with API calls
6. **Time Zone:** All timestamps are in server local time (Asia/Manila, UTC+8)

---

## Support

For issues or questions about this API, contact the system administrator or refer to the main LAMMS documentation.

**API Version:** 1.0  
**Last Updated:** October 6, 2025  
**Backend Framework:** Laravel 10 + PostgreSQL
