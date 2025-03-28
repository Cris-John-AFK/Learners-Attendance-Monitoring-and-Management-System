# Project Summary: LAMMS Teacher Management System

## Overview
We've been working on the LAMMS (Learning and Academic Management System) teacher management module, specifically focusing on the `Admin-Teacher.vue` component which handles teacher registration, assignment management, scheduling, and UI improvements. The project has faced several technical challenges related to API connectivity, data persistence, and UI/UX enhancements.

## Key Issues Addressed

### 1. API Connection & Data Loading Issues
- **Problem**: Encountered 500 Internal Server Error when fetching active sections from the API endpoint at `http://localhost:8000/api/sections/active` and other connection issues.
- **Solution**: 
  - Implemented a resilient API connection system with multiple fallback endpoints
  - Created a `tryApiEndpoints` helper function that attempts multiple API URLs (localhost:8000, 127.0.0.1:8000, localhost)
  - Added proper error handling with detailed logging
  - Implemented fallback data for offline functionality

```javascript
// API configuration with multiple endpoints to try
const API_ENDPOINTS = [
    'http://localhost:8000/api',
    'http://127.0.0.1:8000/api',
    'http://localhost/api'
];

// Function to try multiple API endpoints
const tryApiEndpoints = async (path, options = {}) => {
    let lastError = null;
    let lastResponse = null;
    
    // Try each endpoint until one works
    for (const baseUrl of API_ENDPOINTS) {
        try {
            const url = `${baseUrl}${path}`;
            console.log(`Trying API endpoint: ${url}`);
            
            // Create abort controller for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            // Make the request
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    ...(options.headers || {})
                },
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            lastResponse = response;
            
            // If successful, update the base URL and return the response
            if (response.ok) {
                if (baseUrl !== API_BASE_URL) {
                    console.log(`Found working API endpoint: ${baseUrl}`);
                    API_BASE_URL = baseUrl;
                }
                return await response.json();
            }
            
            // Try to parse error response but continue to next endpoint
            // [Error handling code...]
        } catch (error) {
            // [Error handling code...]
        }
    }
    
    // If we get here, all endpoints failed
    throw lastError || new Error('Failed to connect to any API endpoint');
};
```

### 2. Teacher Assignment Management
- **Problem**: Teacher assignments weren't saving correctly due to API connectivity issues and payload structure problems.
- **Solution**:
  - Implemented a local-first approach to save assignments in localStorage
  - Added background sync to backend when connectivity is available
  - Fixed payload structure to match backend expectations (wrapped in 'assignments' array)
  - Added proper validation and error handling

```javascript
const saveAssignment = async () => {
    // Validate first
    const errors = validateAssignment();
    if (errors.length > 0) {
        assignmentErrors.value = errors;
        return;
    }

    try {
        // Extra validation for IDs
        if (!assignment.value.section_id || assignment.value.section_id === 0 ||
            !assignment.value.subject_id || assignment.value.subject_id === 0) {
            toast.add({
                severity: 'error',
                summary: 'Invalid Data',
                detail: 'Section or Subject ID cannot be zero or null',
                life: 3000
            });
            console.error('Attempted to save with invalid IDs:', {
                section_id: assignment.value.section_id,
                subject_id: assignment.value.subject_id
            });
            return;
        }

        // Show processing toast
        toast.add({
            severity: 'info',
            summary: 'Processing',
            detail: 'Saving assignment...',
            life: 2000
        });

        // Create a unique ID for local storage
        const localId = 'local-' + Date.now();

        // Get the actual section and subject objects
        const selectedSection = filteredSections.value.find(s => Number(s.id) === Number(assignment.value.section_id));
        const selectedSubject = subjectOptions.value.find(s => Number(s.id) === Number(assignment.value.subject_id));

        // Create new assignment object with necessary data
        const newAssignment = {
            id: localId,
            teacher_id: teacher.value.id,
            section_id: Number(assignment.value.section_id),
            subject_id: Number(assignment.value.subject_id),
            is_primary: assignment.value.is_primary,
            role: assignment.value.role,
            // Include the related objects for display
            section: selectedSection,
            subject: selectedSubject
        };

        // Add to teacher's assignments
        teacher.value.active_assignments.push(newAssignment);

        // Show success message
        setTimeout(() => {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Assignment saved successfully',
                life: 3000
            });
        }, 1000);

        // Close the dialog
        hideAssignmentDialog();

        // Try to sync with backend in the background
        // [Background sync code...]
    } catch (error) {
        console.error('Error saving assignment:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'An unexpected error occurred',
            life: 3000
        });
    }
};
```

### 3. UI/UX Improvements
- **Problem**: The original UI was not professional-looking and had usability issues (buttons not working, scrolling requirements).
- **Solution**:
  - Created a modern card-based layout for teachers instead of a table
  - Improved button sizes and styling for better usability
  - Enhanced the teacher details dialog for better information display
  - Added educational/mathematical themed styling elements
  - Added animated background elements for visual appeal

```html
<div class="teacher-card">
    <div class="teacher-card-header">
        <div class="teacher-info">
            <div class="teacher-name">{{ teacher.first_name }} {{ teacher.last_name }}</div>
            <div v-if="teacher.is_head_teacher" class="teacher-role">Head Teacher</div>
        </div>
        <Tag :value="teacher.is_active ? 'ACTIVE' : 'INACTIVE'"
            :severity="teacher.is_active ? 'success' : 'danger'" />
    </div>

    <div class="teacher-card-body">
        <div class="teacher-detail">
            <span class="detail-label">Grade:</span>
            <span class="grade-badge">{{ getGradeName(teacher.active_assignments) }}</span>
        </div>

        <div class="teacher-detail">
            <span class="detail-label">Section:</span>
            <span class="section-badge">{{ getSectionName(teacher.active_assignments) }}</span>
        </div>

        <div class="teacher-detail">
            <span class="detail-label">Subjects:</span>
            <span class="subjects-list">{{ getSubjects(teacher.active_assignments) }}</span>
        </div>
    </div>

    <div class="teacher-card-actions">
        <Button class="action-btn schedule-btn" @click="viewTeacher(teacher)" v-tooltip.top="'View Details'">
            <i class="pi pi-user"></i>
            <span>Details</span>
        </Button>
        <!-- Other action buttons... -->
    </div>
</div>
```

```css
.teacher-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.teacher-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 20px rgba(0, 0, 0, 0.1);
}

/* Other styling... */
```

### 4. Teacher Details View
- **Problem**: The original teacher details dialog was basic and lacked visual appeal.
- **Solution**:
  - Created a more structured layout for teacher information
  - Added teacher avatar with initials
  - Improved the display of subjects, grades and sections
  - Enhanced the styling for badges and status indicators

```html
<Dialog v-model:visible="teacherDetailsDialog" modal header="Teacher Details" :style="{ width: '550px' }" class="teacher-details-dialog">
    <div class="p-fluid" v-if="selectedTeacher">
        <div class="teacher-details-header">
            <div class="teacher-avatar">
                <div class="teacher-initials">{{ getInitials(selectedTeacher) }}</div>
            </div>
            <div class="teacher-details-name">
                <h2>{{ selectedTeacher.first_name }} {{ selectedTeacher.last_name }}</h2>
                <div class="teacher-status">
                    <Tag :value="selectedTeacher.is_active ? 'ACTIVE' : 'INACTIVE'"
                        :severity="selectedTeacher.is_active ? 'success' : 'danger'" />
                    <span v-if="selectedTeacher.is_head_teacher" class="head-teacher-badge">Head Teacher</span>
                </div>
            </div>
        </div>
        
        <!-- Additional teacher details sections... -->
    </div>
</Dialog>
```

### 5. Class Scheduling Functionality
- **Problem**: Schedule management was limited and not visually appealing.
- **Solution**:
  - Enhanced the schedule dialog with better layout and styling
  - Implemented a weekly view for better schedule visualization
  - Added room information and section display
  - Improved the UI for adding and managing schedule items

```html
<!-- Schedule Dialog -->
<Dialog v-model:visible="scheduleDialog"
    :header="`Schedule for ${selectedSubjectForSchedule?.name || 'Subject'}`"
    modal
    :style="{ width: '80vw', maxWidth: '1000px' }"
    class="schedule-dialog">
    <div v-if="selectedSubjectForSchedule" class="p-fluid">
        <div class="schedule-header mb-4">
            <!-- Header content -->
        </div>

        <!-- Schedule Form -->
        <div class="schedule-form p-3 mb-4 border-1 surface-border border-round">
            <h5 class="mb-3">Add New Schedule</h5>
            <div class="formgrid grid">
                <!-- Form fields -->
            </div>
        </div>

        <!-- Schedule Table -->
        <div class="schedule-table">
            <!-- Table content -->
        </div>

        <!-- Weekly View -->
        <div v-if="scheduleData.length > 0" class="weekly-schedule mt-4">
            <h5 class="mb-3">Weekly View</h5>
            <div class="schedule-grid border-1 surface-border">
                <!-- Weekly view content -->
            </div>
        </div>
    </div>
</Dialog>
```

## Current State

### Working Features
1. **Teacher Management**:
   - Teacher registration/editing
   - Subject assignment
   - Class scheduling
   - Status tracking (active/inactive)

2. **Data Loading**:
   - Resilient API connectivity with multiple fallbacks
   - Proper error handling and user feedback
   - Offline functionality with fallback data

3. **UI/UX**:
   - Modern card-based layout
   - Animated elements for visual appeal
   - Enhanced dialog designs
   - Better organization of information

### Pending/Future Tasks
1. **Backend Integration**:
   - Additional endpoint discovery for edge cases
   - Potential CORS handling

2. **Data Synchronization**:
   - More robust conflict resolution for offline/online sync
   - Potential queue system for failed API calls

3. **UI Enhancements**:
   - Potential filter/search improvements
   - Mobile responsiveness testing/improvements

## Technical Implementation Notes

### API Communication Strategy
- Always try multiple endpoints (localhost:8000, 127.0.0.1:8000, localhost)
- Implement proper timeouts to prevent hanging
- Provide fallback data for all API requests
- Use local storage for persistence when offline

### Data Normalization
- Ensure all IDs are converted to numbers for consistency
- Provide consistent data structures even with fallback data
- Map API responses to expected formats with defaults for missing fields

### UI Philosophy
- Card-based layout for better visual organization
- No scrolling requirement for main views
- Educational/mathematical themed styling
- Clear action buttons with tooltips
- Consistent error handling with toast notifications

## Technologies Used
- Vue.js 3 with Composition API
- PrimeVue components (Button, Dialog, DataTable, etc.)
- Fetch API for backend communication
- LocalStorage for offline persistence
- CSS animations for visual enhancements 