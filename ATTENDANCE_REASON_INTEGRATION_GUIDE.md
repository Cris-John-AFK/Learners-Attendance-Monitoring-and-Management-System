# Attendance Reason Integration Guide

## ✅ Completed Backend Work

### Database
- ✅ `attendance_reasons` table created with 25 predefined reasons
- ✅ `attendance_records.reason_id` and `attendance_records.reason_notes` columns added
- ✅ API endpoints created:
  - `GET /api/attendance/reasons` - All reasons
  - `GET /api/attendance/reasons/{type}` - Reasons by type (late/excused)

### Controllers Updated
- ✅ `AttendanceController::markSingleAttendance()` - Accepts reason_id + reason_notes
- ✅ `AttendanceController::markAttendance()` - Bulk marking with reasons
- ✅ `AttendanceSessionController::markAttendance()` - Session marking with reasons

### Component Created
- ✅ `AttendanceReasonDialog.vue` - Reusable dialog for selecting reasons

---

## 📋 Frontend Integration Steps

### 1. **TeacherSubjectAttendance.vue** - Seat Plan & Roll Call

#### Import the Dialog Component
```javascript
import AttendanceReasonDialog from '@/components/AttendanceReasonDialog.vue';
```

#### Add to Component Registration
```javascript
components: {
    AttendanceReasonDialog,
    // ... other components
}
```

#### Add Data Properties
```javascript
data() {
    return {
        // ... existing data
        showReasonDialog: false,
        reasonDialogType: 'late', // 'late' or 'excused'
        pendingAttendanceUpdate: null, // Store student info while dialog is open
    }
}
```

#### Modify the Attendance Marking Logic

**For Seat Plan (when clicking seat):**
```javascript
markStudentFromSeat(row, col) {
    const seat = this.seatPlan[row][col];
    if (!seat.isOccupied) return;
    
    const studentId = seat.studentId;
    const student = this.students.find(s => s.id === studentId);
    
    // Cycle through: null → present → late → excused → absent
    let newStatus = 'present';
    
    if (seat.status === null) {
        newStatus = 'present';
    } else if (seat.status === 'present') {
        // SHOW REASON DIALOG FOR LATE
        this.showReasonDialogForStatus(student, 'late', row, col);
        return;
    } else if (seat.status === 'late') {
        // SHOW REASON DIALOG FOR EXCUSED
        this.showReasonDialogForStatus(student, 'excused', row, col);
        return;
    } else if (seat.status === 'excused') {
        newStatus = 'absent';
    } else if (seat.status === 'absent') {
        newStatus = null;
    }
    
    // Update seat
    this.seatPlan[row][col].status = newStatus;
    this.updateAttendanceStatusForStudent(studentId, newStatus);
}
```

#### Add Method to Show Reason Dialog
```javascript
showReasonDialogForStatus(student, statusType, row, col) {
    this.pendingAttendanceUpdate = {
        student,
        statusType,
        row,
        col
    };
    this.reasonDialogType = statusType;
    this.showReasonDialog = true;
},

onReasonConfirmed(reasonData) {
    if (!this.pendingAttendanceUpdate) return;
    
    const { student, statusType, row, col } = this.pendingAttendanceUpdate;
    
    // Update seat with reason
    if (row !== undefined && col !== undefined) {
        this.seatPlan[row][col].status = statusType;
        this.seatPlan[row][col].reason_id = reasonData.reason_id;
        this.seatPlan[row][col].reason_notes = reasonData.reason_notes;
    }
    
    // Update attendance status for student
    this.updateAttendanceStatusForStudent(student.id, statusType, reasonData);
    
    // Clear pending
    this.pendingAttendanceUpdate = null;
},
```

#### Update the API Call to Include Reasons
```javascript
async updateAttendanceStatusForStudent(studentId, status, reasonData = null) {
    const student = this.students.find(s => s.id === studentId);
    if (!student) return;
    
    // Map status to attendance_status_id
    const statusMap = {
        'present': 1,  // Adjust IDs based on your attendance_statuses table
        'late': 3,
        'excused': 4,
        'absent': 2
    };
    
    const payload = {
        student_id: studentId,
        section_id: this.section_id,
        subject_id: this.resolved_subject_id,
        teacher_id: this.teacher_id,
        attendance_status_id: statusMap[status] || 1,
        date: this.currentDate,
        remarks: student.remarks || null
    };
    
    // Add reason if provided
    if (reasonData) {
        payload.reason_id = reasonData.reason_id;
        payload.reason_notes = reasonData.reason_notes;
    }
    
    try {
        await axios.post('http://localhost:8000/api/attendance/mark-single', payload);
        console.log(`✅ Attendance marked: ${status} for student ${studentId}`);
    } catch (error) {
        console.error('Failed to mark attendance:', error);
    }
}
```

#### Add Dialog to Template
```vue
<template>
    <!-- ... existing template -->
    
    <!-- Attendance Reason Dialog -->
    <AttendanceReasonDialog
        v-model="showReasonDialog"
        :status-type="reasonDialogType"
        :student-name="pendingAttendanceUpdate?.student?.name || 
                       pendingAttendanceUpdate?.student?.firstName + ' ' + 
                       pendingAttendanceUpdate?.student?.lastName"
        @confirm="onReasonConfirmed"
    />
</template>
```

---

### 2. **Attendance Sessions Edit** (if using sessions view)

Similar integration as above, but when editing an existing record:

```javascript
editAttendanceRecord(record) {
    this.pendingRecordEdit = record;
    
    // If changing to Late or Excused, show reason dialog
    if (newStatus === 'late' || newStatus === 'excused') {
        this.reasonDialogType = newStatus;
        this.showReasonDialog = true;
    }
}
```

---

## 🎯 Testing Checklist

- [ ] Mark student as Late → Reason dialog appears
- [ ] Select reason → Additional notes field shows
- [ ] Select "Other" → Notes field becomes required
- [ ] Confirm reason → Attendance saved with reason_id and reason_notes
- [ ] Mark student as Excused → Different reason list appears
- [ ] View attendance history → Reasons are displayed
- [ ] Edit existing attendance → Previous reason is shown

---

## 📊 Database Seeded Reasons

### Late Reasons (12)
1. Far distance from home to school
2. Muddy/impassable road
3. Flooded road/area
4. No available transportation
5. Helping with farm/household chores before school
6. Heavy rain
7. Strong typhoon/storm
8. Illness (mild)
9. Medical appointment
10. Family emergency
11. Took care of younger sibling
12. Other

### Excused Reasons (13)
1. Illness
2. Medical appointment
3. Medical procedure/treatment
4. Recovering from illness
5. Family emergency
6. Family bereavement
7. Family obligation/event
8. Taking care of sick family member
9. Typhoon/storm
10. Flooding (area inaccessible)
11. Road completely impassable
12. School-sanctioned activity
13. Other

---

## 🚀 Next Steps
1. Integrate dialog into TeacherSubjectAttendance.vue
2. Test with real data
3. Add reason display in attendance history/reports
4. Use reason data for ML predictions (future)
