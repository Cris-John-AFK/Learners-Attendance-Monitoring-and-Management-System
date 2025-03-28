# LAMMS (Learning and Academic Management System) Project Summary

## Project Overview
LAMMS is a comprehensive Learning and Academic Management System designed to manage teachers, students, sections, subjects, grades, and their relationships. The system includes admin interfaces for managing these entities and a teacher dashboard for instructors to access their assigned classes and subjects.

## Key Components and Features

### Admin Teacher Management
- Teacher registration and profile management
- Assignment of teachers to sections and subjects
- Grade and section management
- Teacher scheduling and workload management

### Technical Architecture
- Frontend: Vue.js with PrimeVue components
- Backend: Laravel API with MySQL/PostgreSQL database
- Authentication: Laravel Sanctum for API authentication

## Issues Addressed and Solutions

### 1. Teacher Assignment Data Persistence
**Problem:** Teacher assignments (sections and subjects) weren't persisting on page reload, and data was showing as "N/A" despite being selected.

**Root Cause:** 
- The teacher assignment data was being stored in local memory only and not saved to the backend database
- The API endpoint for saving assignments wasn't being called correctly
- The data formatting for assignments was incorrect

**Solution:**
```javascript
// Function to save assignments to backend
const saveAssignmentToBackend = async (teacherId, assignments) => {
    try {
        console.log('Saving assignments to backend for teacher ID:', teacherId);
        
        const response = await axios.put(
            `${API_BASE_URL}/teachers/${teacherId}/assignments`, 
            { assignments: assignments.map(a => ({
                section_id: a.section_id,
                subject_id: a.subject_id,
                is_primary: a.is_primary || false,
                role: a.role || 'Teacher'
            })) }
        );
        
        console.log('Backend save response:', response.data);
        
        // Refresh teacher data to ensure we have the latest from the server
        await loadTeachers();
        
        return response.data;
    } catch (error) {
        console.error('Failed to save assignments to backend:', error);
        throw error;
    }
};
```

### 2. Teacher Data Loading and Relationships
**Problem:** Teacher data wasn't correctly loaded with its relationships (sections, subjects, grades), causing display issues.

**Solution:**
```javascript
// Methods
const loadTeachers = async () => {
    try {
        loading.value = true;
        toast.add({
            severity: 'info',
            summary: 'Loading',
            detail: 'Fetching teachers from server...',
            life: 2000
        });

        // Use the tryApiEndpoints helper to handle multiple endpoints
        const data = await tryApiEndpoints('/teachers');

        if (!data || data.length === 0) {
            console.warn('No teachers returned from API');
            toast.add({
                severity: 'warn',
                summary: 'No Teachers',
                detail: 'No teachers found in the database. Please add teachers using the Register button.',
                life: 5000
            });
            teachers.value = [];
            return;
        }

        // If sections and grades aren't loaded yet, load them first
        if (sections.value.length === 0) {
            await loadSections();
        }

        if (subjects.value.length === 0) {
            await loadSubjects();
        }

        if (gradeOptions.value.length === 0) {
            await loadGrades();
        }

        // Process teachers data with proper assignment handling
        teachers.value = data.map(teacher => {
            // Normalize assignments
            let processedAssignments = [];
            
            if (teacher.assignments && Array.isArray(teacher.assignments)) {
                processedAssignments = teacher.assignments
                    .filter(a => a && a.section_id && a.subject_id) // Filter out invalid assignments
                    .map(assignment => {
                        // Find the full section from our loaded sections data
                        const sectionObj = sections.value.find(s => Number(s.id) === Number(assignment.section_id));
                        
                        // Find the full subject from our loaded subjects data
                        const subjectObj = subjects.value.find(s => Number(s.id) === Number(assignment.subject_id));
                        
                        // Create enhanced assignment with complete objects
                        return {
                            id: assignment.id,
                            section_id: Number(assignment.section_id),
                            subject_id: Number(assignment.subject_id),
                            is_primary: assignment.is_primary || false,
                            is_active: assignment.is_active !== undefined ? assignment.is_active : true,
                            // Include full section object with grade info
                            section: sectionObj || { 
                                id: Number(assignment.section_id), 
                                name: `Section ${assignment.section_id}`,
                                grade_id: null,
                                grade: null
                            },
                            // Include full subject object
                            subject: subjectObj || { 
                                id: Number(assignment.subject_id), 
                                name: `Subject ${assignment.subject_id}` 
                            },
                            role: assignment.role || 'Teacher'
                        };
                    });
            }
            
            return {
                ...teacher,
                active_assignments: processedAssignments
            };
        });

        console.log('Successfully processed teachers with full assignment data:', teachers.value);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Loaded ${teachers.value.length} teachers successfully`,
            life: 3000
        });
    } catch (error) {
        console.error('Error loading teachers:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to load teachers: ${error.message}`,
            life: 5000
        });

        // Initialize with empty array instead of fallback data
        teachers.value = [];
    } finally {
        loading.value = false;
    }
};
```

### 3. Missing editTeacher Function
**Problem:** The "Edit Teacher" button was triggering an error because the `editTeacher` function was missing.

**Solution:**
```javascript
const editTeacher = (teacherData) => {
    teacher.value = { ...teacherData };
    submitted.value = false;
    teacherDialog.value = true;
    console.log('Edit dialog opened for teacher:', teacherData);
};
```

### 4. API Connectivity and Fallback
**Problem:** API connection issues were preventing the application from loading data.

**Solution:**
```javascript
// API configuration with multiple endpoints to try
const API_ENDPOINTS = [
    'http://localhost:8000/api',
    'http://127.0.0.1:8000/api',
    'http://localhost/api'
];

// Try multiple API endpoints with proper error handling
const tryApiEndpoints = async (path, options = {}) => {
    let lastError = null;
    
    for (const baseUrl of API_ENDPOINTS) {
        try {
            const url = `${baseUrl}${path}`;
            console.log(`Trying API endpoint: ${url}`);
            
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    ...(options.headers || {})
                },
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (response.ok) {
                if (baseUrl !== API_BASE_URL) {
                    console.log(`Found working API endpoint: ${baseUrl}`);
                    API_BASE_URL = baseUrl;
                }
                return await response.json();
            }
        } catch (error) {
            lastError = error;
            console.error(`Error with endpoint ${baseUrl}${path}:`, error);
        }
    }
    
    throw lastError || new Error('Failed to connect to any API endpoint');
};
```

### 5. UI Enhancements
- Modern card-based UI for teacher listing
- Improved assignment dialog with better validation
- Responsive design for mobile usage
- Enhanced visual feedback for user actions

## Database Schema
The system uses the following key tables:
- `teachers` - Stores teacher profile information
- `sections` - Stores class section data
- `subjects` - Stores subject information
- `grades` - Stores grade levels
- `teacher_section_subject` - Junction table connecting teachers to sections and subjects

```php
// TeacherSectionSubject model (junction table)
protected $fillable = [
    'teacher_id',
    'section_id',
    'subject_id',
    'is_primary',
    'is_active'
];

// Teacher model relationships
public function assignments()
{
    return $this->hasMany(TeacherSectionSubject::class);
}

public function sections()
{
    return $this->belongsToMany(Section::class, 'teacher_section_subject')
        ->withPivot('subject_id', 'is_primary', 'is_active')
        ->withTimestamps();
}

public function subjects()
{
    return $this->belongsToMany(Subject::class, 'teacher_section_subject')
        ->withPivot('section_id', 'is_primary', 'is_active')
        ->withTimestamps();
}
```

## API Endpoints
Key API endpoints include:
- `GET /teachers` - List all teachers
- `GET /teachers/{id}` - Get a specific teacher
- `PUT /teachers/{id}` - Update a teacher
- `PUT /teachers/{id}/assignments` - Update teacher assignments
- `GET /sections` - List all sections
- `GET /subjects` - List all subjects

## Future Development
Planned enhancements:
1. Teacher authentication with unique accounts
2. Integration with student management
3. Grade and assessment management
4. Parent portal access
5. Reporting and analytics

## Current Status
The system now properly persists teacher assignment data to the database, displays grade, section, and subject information correctly, and provides a responsive user interface for managing teacher data.

## Requirements for Further Work
- All data should be stored in the database, not localStorage
- Database relationships should be used for data integrity
- Backend endpoints should handle all data operations
- Frontend should provide immediate feedback while ensuring data is properly saved 