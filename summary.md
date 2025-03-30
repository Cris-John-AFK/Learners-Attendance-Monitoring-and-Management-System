# LAMMS Project Summary - Teacher Assignment System

## Overview
The Learning and Academic Management System (LAMMS) is being developed with a focus on teacher assignment management. The system allows school administrators to assign teachers to grade levels, sections, and subjects, with special handling for primary teachers who are automatically assigned homeroom subjects.

## Key Components 

### TeacherAssignmentWizard
A multi-step wizard component that guides administrators through the process of assigning teachers, with two main modes:
- **new**: For creating new assignments for a teacher
- **add-subjects**: For adding additional subjects to teachers who already have a primary assignment

### Admin-Teacher.vue
Main administration view for managing teachers, with capabilities to:
- View all teachers in a card-based UI
- Display primary assignments and subjects for each teacher
- Assign teachers to sections and subjects
- Edit teacher information
- Delete teachers

## Implemented Features

### Primary Teacher Assignment
- Primary teachers are automatically assigned homeroom subjects
- Each teacher can only have one primary assignment
- The UI differentiates between primary and subject assignments using colored tags

### Subject Assignment
- Teachers can be assigned to teach multiple subjects
- Primary teachers can have additional subject assignments
- The system validates to prevent duplicate assignments

### UI Enhancements
- Teacher cards display all subjects assigned to a teacher
- Subjects are shown with tags indicating whether they are primary assignments
- The assignment wizard interface provides clear guidance through the assignment process

## Technical Issues Resolved

### Duplicate Variable Declarations
Fixed multiple instances of duplicated variable declarations in Admin-Teacher.vue:
- `assignmentWizardDialog`
- `assignmentWizardMode`
- `currentAssignmentStep`
- `totalAssignmentSteps`

### Missing Variable Declarations
Added missing variable declarations for wizard functionality:
```javascript
// Assignment wizard refs
const selectedRole = ref(null);
const selectedGradeForAssignment = ref(null);
const selectedSectionForAssignment = ref(null);
const selectedSubjectsForAssignment = ref([]);
const assignmentWizardTeacher = ref(null);
const availableSubjectsForAssignment = ref([]);
const availableSections = ref([]);
```

### Homeroom Subject Handling
Fixed an issue where the system was trying to use "homeroom" as a string ID when assigning primary teachers, causing SQL errors:
```javascript
// Previous implementation creating an invalid homeroom subject
selectedSubjects.value = [{
    id: 'homeroom', // Invalid string ID causing database errors
    name: 'Homeroom',
    description: 'Main class for primary teacher',
    is_primary: true
}];

// Fixed implementation checking for existing homeroom subject
const homeroomSubject = availableSubjects.value.find(
    s => s.name.toLowerCase() === 'homeroom'
);

if (homeroomSubject) {
    selectedSubjects.value = [homeroomSubject];
} else {
    // Show warning instead of creating invalid subject
    toast.add({
        severity: 'warn',
        summary: 'Homeroom Subject Missing',
        detail: 'Homeroom subject not found in the system. Please create it first.',
        life: 5000
    });
}
```

### Assignment Mode Handling
Enhanced the wizard to support two modes:
1. **New Assignment Mode**:
   - Full wizard flow from role selection to subject selection
   - Automatically adds the homeroom subject for primary teachers

2. **Add Subjects Mode**:
   - Skip directly to subject selection when teacher already has a primary assignment
   - Show already assigned subjects as disabled
   - Never sets additional subjects as primary to avoid validation errors

## Pending Tasks and Future Improvements

1. **Homeroom Subject Requirements**:
   - The system requires a homeroom subject to exist in the database for primary teacher assignments
   - Admin should ensure a subject with name "Homeroom" exists before attempting primary assignments

2. **Error Handling Improvements**:
   - Added detailed error handling for 500 errors related to duplicate assignments
   - Further improvements could be made for other error scenarios

3. **UI Consistency**:
   - Ensure consistent styling across the assignment dialogs and teacher cards

## Technical Constraints

1. **Database Schema**:
   - The teacher_assignments table requires numeric IDs for subjects
   - Teacher assignments need proper role and is_primary flags

2. **API Endpoints**:
   - The system uses axios for API calls to endpoints like `/teachers/{id}/assignments`
   - POST and PUT endpoints are used for creating and updating assignments

## Code Structure

The application follows a Vue.js structure with:
- Composition API for reactive variables and functions
- PrimeVue components for UI elements
- Axios for API calls
- Server validation with appropriate error handling

## Usage Workflow

1. Admin navigates to the Teacher Management page
2. Admin can view all teachers and their assignments
3. To add assignments:
   - For new teachers: Click "Assign" and follow the full wizard
   - For teachers with existing primary assignments: Click "Assign" to add more subjects
4. The system validates assignments to prevent duplicates and follow business rules

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
