# Attendance Records Status Filter - November 12, 2025 (11:41 AM)

## Feature Request
Add a status filter dropdown to the Attendance Records page to filter students by their enrollment/attendance status.

## Implementation

### 1. **Removed "Show only students with issues" Checkbox**
**File**: `TeacherAttendanceRecords.vue`

- Removed `showOnlyIssues` variable (was Line 121)
- Removed checkbox UI component
- Removed issues filter logic
- Changed grid layout from 3 columns back to 2 columns

### 2. **Added Status Filter Variable**
**File**: `TeacherAttendanceRecords.vue` (Line 121)

```javascript
const statusFilter = ref(null); // Status filter
```

### 2. **Added Status Filter Dropdown**
**File**: `TeacherAttendanceRecords.vue` (Lines 1516-1536)

Added a new dropdown filter in the filters row with the following options:
- **All Statuses** (default - shows all students)
- **Normal** - Students with good attendance (< 3 absences)
- **At Risk** - Students with attendance concerns (3-4 absences)
- **Warning** - Students with severe attendance issues (5+ absences)
- **Dropped Out** - Students who have dropped out
- **Transferred Out** - Students who transferred to another school

```vue
<!-- Status Filter -->
<div class="field">
    <label for="statusFilter" class="text-sm font-medium mb-2 block">Filter by Status:</label>
    <Dropdown
        id="statusFilter"
        v-model="statusFilter"
        :options="[
            { label: 'All Statuses', value: null },
            { label: 'Normal', value: 'Normal' },
            { label: 'At Risk', value: 'At Risk' },
            { label: 'Warning', value: 'Warning' },
            { label: 'Dropped Out', value: 'Dropped Out' },
            { label: 'Transferred Out', value: 'Transferred Out' }
        ]"
        optionLabel="label"
        optionValue="value"
        placeholder="All Statuses"
        class="w-full"
        showClear
    />
</div>
```

### 3. **Added Filter Logic**
**File**: `TeacherAttendanceRecords.vue` (Lines 302-316)

Added filtering logic to apply the status filter:

```javascript
// Apply status filter
if (statusFilter.value) {
    records = records.filter((record) => {
        // Check enrollment status first (Dropped Out, Transferred Out)
        if (record.enrollment_status && record.enrollment_status.toLowerCase() !== 'active') {
            const enrollmentStatus = record.enrollment_status.toLowerCase();
            if (statusFilter.value === 'Dropped Out' && enrollmentStatus === 'dropped_out') return true;
            if (statusFilter.value === 'Transferred Out' && enrollmentStatus === 'transferred_out') return true;
            return false;
        }
        
        // Otherwise check calculated attendance status (Normal, At Risk, Warning)
        const calculatedStatus = getOverallStatus(record);
        return calculatedStatus === statusFilter.value;
    });
}
```

**Logic**:
1. First checks `enrollment_status` field for "Dropped Out" or "Transferred Out"
2. Then checks calculated attendance status from `getOverallStatus()` for "Normal", "At Risk", or "Warning"
3. `getOverallStatus()` calculates status based on total absences:
   - **Normal**: < 3 absences
   - **At Risk**: 3-4 absences
   - **Warning**: 5+ absences

## UI Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ Section: Gumamela (Homeroom)  │  Subject: English              │
│ Start Date: 2025-11-06         │  End Date: 2025-11-12          │
│ Search: [Search by name or ID...]                               │
├─────────────────────────────────────────────────────────────────┤
│ Quick Ranges: [Today] [This Week] [This Month] [Last 7 Days]   │
├─────────────────────────────────────────────────────────────────┤
│ Filters:                                                         │
│ ┌──────────────────────────────────────────────────────────┐   │
│ │ Filter by Status: [All Statuses ▼]                       │   │
│ └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Status Filter Options

| Status | Description | Criteria | Color Indicator |
|--------|-------------|----------|-----------------|
| **All Statuses** | Shows all students (default) | - | - |
| **Normal** | Students with good attendance | < 3 absences | Green badge |
| **At Risk** | Students with attendance concerns | 3-4 absences | Orange badge |
| **Warning** | Students with severe issues | 5+ absences | Red badge |
| **Dropped Out** | Students who dropped out | enrollment_status = 'dropped_out' | Gray badge |
| **Transferred Out** | Students who transferred | enrollment_status = 'transferred_out' | Gray badge |

## How It Works

1. **Default State**: Shows all students (statusFilter = null)
2. **Select Status**: User selects a status from dropdown
3. **Filter Applied**: Table shows only students matching that status
4. **Clear Filter**: Click X button or select "All Statuses" to reset

## Combining Filters

The status filter works **in combination** with other filters:

- **Status + Search**: Search for specific students within a status
- **Status + Date Range**: View status for specific time period

Example: Select "At Risk" + Search "Carlos" = Shows only at-risk students named Carlos

## Testing Instructions

1. **Go to**: Teacher Dashboard → Attendance Records
2. **Locate**: "Filter by Status" dropdown (below date range)
3. **Test Each Status**:
   - Select "Normal" → Should show only students with < 3 absences
   - Select "At Risk" → Should show only students with 3-4 absences
   - Select "Warning" → Should show only students with 5+ absences
   - Select "Dropped Out" → Should show dropped out students
   - Select "Transferred Out" → Should show transferred students
   - Select "All Statuses" → Should show all students
4. **Test Clear**: Click X button → Should reset to "All Statuses"
5. **Test Combined**: 
   - Select "At Risk"
   - Search for a student name
   - Should show only at-risk students matching search

## Files Modified

1. **TeacherAttendanceRecords.vue** (Lines 121, 301-306, 1508-1529)
   - Removed `showOnlyIssues` variable and checkbox
   - Removed issues filter logic
   - Added `statusFilter` ref variable
   - Added status filter logic
   - Added dropdown UI component
   - Grid layout remains 2 columns (was briefly 3, now back to 2)

## Benefits

✅ **Quick Filtering**: Instantly filter students by status
✅ **Better Organization**: Focus on specific student groups
✅ **Combined Filters**: Works with existing filters
✅ **Clear UI**: Dropdown with clear labels
✅ **Easy Reset**: Clear button to show all students

---

**Status**: ✅ COMPLETE - Ready for testing
