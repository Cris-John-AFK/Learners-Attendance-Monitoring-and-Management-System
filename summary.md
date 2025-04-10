# Sakai LAMMS Project Summary

## Project Overview
This project involves enhancing a Learning and Academic Management System (LAMMS) built with Vue.js frontend and Laravel backend. The focus has been on improving the curriculum management module, specifically addressing issues with grade management, teacher assignments, and scheduling.

## Recent Technical Solutions

### 1. Grade-Specific Teacher Assignment
We implemented conditional display of the "Assign Teacher" button for grades 3-6 only. This was achieved by:
- Modifying the `requiresSubjectTeachers` function to include Grade 3 in the list of grades requiring subject teachers
- Adding a computed property `currentGradeHasSubjectTeachers` to check if the selected grade requires subject teachers
- Conditionally rendering the teacher assignment UI based on this property

```javascript
// Calculate if a grade requires subject teachers (Grade 3-6)
const requiresSubjectTeachers = (gradeCode) => {
    return ['G3', 'G4', 'G5', 'G6'].includes(gradeCode);
};

// Computed property to determine if the current grade should have subject teachers
const currentGradeHasSubjectTeachers = computed(() => {
    if (!selectedGrade.value || !selectedGrade.value.code) {
        return false;
    }
    return requiresSubjectTeachers(selectedGrade.value.code);
});
```

### 2. Teacher Assignment Dialog Implementation
We created a Teacher Assignment Dialog component for assigning teachers to subjects:
- Added a teleported dialog component with proper z-index handling
- Included conditional fields for selecting a teacher
- Enhanced the `openTeacherDialog` function with better error handling
- Implemented pre-selection of existing teachers if a subject already has one assigned
- Added validation to prevent submitting without selecting a teacher

### 3. Schedule Conflict Prevention System
We implemented a system to prevent scheduling conflicts:
- Created a `checkForScheduleConflicts` function to detect if schedules overlap
- Added a helper function `convertTimeToMinutes` to simplify time comparisons
- Modified the `saveSchedule` function to check for conflicts before saving

```javascript
// Function to check for schedule conflicts
const checkForScheduleConflicts = (newSchedule) => {
    if (!selectedSubjects.value || !selectedSection.value) return false;
    
    // Convert the new schedule's times to minutes for easier comparison
    const newStartMinutes = convertTimeToMinutes(newSchedule.start_time);
    const newEndMinutes = convertTimeToMinutes(newSchedule.end_time);
    const newDay = newSchedule.day;
    
    // Check each subject's schedules for conflicts
    for (const subject of selectedSubjects.value) {
        // Skip the current subject being edited
        if (subject.id === selectedSubjectForSchedule.value?.id) continue;
        
        if (subject.schedules && subject.schedules.length > 0) {
            for (const existingSchedule of subject.schedules) {
                // Only check schedules for the same day
                if (existingSchedule.day !== newDay) continue;
                
                const existingStartMinutes = convertTimeToMinutes(existingSchedule.start_time);
                const existingEndMinutes = convertTimeToMinutes(existingSchedule.end_time);
                
                // Check for overlap
                if (
                    // New schedule starts during an existing schedule
                    (newStartMinutes >= existingStartMinutes && newStartMinutes < existingEndMinutes) ||
                    // New schedule ends during an existing schedule
                    (newEndMinutes > existingStartMinutes && newEndMinutes <= existingEndMinutes) ||
                    // New schedule completely contains an existing schedule
                    (newStartMinutes <= existingStartMinutes && newEndMinutes >= existingEndMinutes)
                ) {
                    return {
                        hasConflict: true,
                        conflictWith: subject.name,
                        existingTime: `${existingSchedule.start_time} - ${existingSchedule.end_time}`
                    };
                }
            }
        }
    }
    
    return { hasConflict: false };
};

// Helper function to convert time string (HH:MM) to minutes for comparison
const convertTimeToMinutes = (timeString) => {
    const [hours, minutes] = timeString.split(':').map(Number);
    return hours * 60 + minutes;
};
```

### 4. Automatic Time Slot Suggestion
We added intelligent time slot suggestions:
- Implemented `getNextAvailableTimeSlot` function to find available time slots
- Updated `openScheduleDialog` to suggest non-conflicting times
- Added a watcher to update suggested times when day selection changes

```javascript
// Function to suggest next available time slot for a day
const getNextAvailableTimeSlot = (day) => {
    if (!selectedSubjects.value || selectedSubjects.value.length === 0) {
        return { start_time: '08:00', end_time: '09:00' }; // Default if no schedules exist
    }
    
    // Collect all schedules for the given day
    const daySchedules = [];
    for (const subject of selectedSubjects.value) {
        if (subject.schedules && subject.schedules.length > 0) {
            for (const schedule of subject.schedules) {
                if (schedule.day === day) {
                    daySchedules.push({
                        start: convertTimeToMinutes(schedule.start_time),
                        end: convertTimeToMinutes(schedule.end_time)
                    });
                }
            }
        }
    }
    
    if (daySchedules.length === 0) {
        return { start_time: '08:00', end_time: '09:00' }; // Default if no schedules for this day
    }
    
    // Sort schedules by start time
    daySchedules.sort((a, b) => a.start - b.start);
    
    // Find latest end time
    const latestEnd = Math.max(...daySchedules.map(s => s.end));
    
    // Convert minutes back to HH:MM format
    const startHour = Math.floor(latestEnd / 60);
    const startMinute = latestEnd % 60;
    const endHour = Math.floor((latestEnd + 60) / 60); // Default to 1 hour later
    const endMinute = (latestEnd + 60) % 60;
    
    // Format times with leading zeros
    const formatTime = (h, m) => `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
    
    return {
        start_time: formatTime(startHour, startMinute),
        end_time: formatTime(endHour, endMinute)
    };
};
```

### 5. UI Enhancements
We improved the user interface in several ways:
- Added helper messages explaining scheduling rules
- Improved error notifications with specific conflict information
- Made teacher selection in schedules conditional on grade level
- Enhanced display of teacher information based on grade requirements

## Project Requirements & Specifications

1. **Grade-specific Teacher Requirements**
   - Grades K-2 (Kinder 1, Kinder 2, Grade 1, Grade 2) should not display teacher assignment options
   - Only Grades 3-6 should have subject teachers and teacher assignment functionality
   - Teacher information should only be displayed for relevant grade levels

2. **Schedule Management Rules**
   - No scheduling conflicts allowed (two subjects can't be scheduled at the same time on the same day)
   - System should automatically suggest non-conflicting time slots
   - Clear error messages should be displayed when conflicts occur
   - The scheduling UI should adapt based on grade level requirements

3. **Subject Display Requirements**
   - Subjects from the database should be properly associated with sections
   - Administrators should have full control over which subjects to add to a section
   - The UI should clearly display subjects assigned to each section

## Technical Decisions

1. **Conditional UI Rendering**
   - We used computed properties to determine what UI elements to show based on grade level
   - This approach allows for clean template code while maintaining grade-specific logic

2. **Schedule Conflict Prevention**
   - We implemented time-based conflict detection to ensure no overlapping schedules
   - We added automatic time slot suggestions to improve user experience
   - We used a watcher to update time slots when day selection changes

3. **Teleported Dialogs**
   - We used Vue's Teleport feature to render dialogs at the body level
   - This ensures proper z-index handling and prevents stacking context issues

4. **Error Handling**
   - We improved error handling throughout the application
   - We added specific error messages to guide users when conflicts or issues occur

## Pending Tasks

1. **Further Testing**
   - Test the scheduling conflict detection with various edge cases
   - Verify that teacher assignments are properly saved to the database
   - Test the system with different grade levels to ensure conditional logic works correctly

2. **UI/UX Improvements**
   - Consider adding visual indicators for time slot availability
   - Explore options for a visual schedule view (calendar-like interface)
   - Improve mobile responsiveness

3. **Performance Optimization**
   - Evaluate if there are any performance bottlenecks in the current implementation
   - Consider adding caching for frequently accessed data

## System Architecture

1. **Frontend**
   - Vue.js with Composition API
   - PrimeVue components for UI
   - Axios for API communication

2. **Backend**
   - Laravel API endpoints
   - MySQL database with relationship tables

3. **Key Components**
   - `Curriculum.vue` - Main component for curriculum management
   - `TeacherService` - Service for teacher-related operations
   - `CurriculumService` - Service for curriculum operations

4. **API Endpoints**
   - `/api/sections/${sectionId}/subjects/${subjectId}/teacher` - For teacher assignment
   - `/api/sections/${sectionId}/subjects/${subjectId}/schedule` - For subject scheduling
