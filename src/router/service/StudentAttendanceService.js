import { reactive } from 'vue';
import { AttendanceService } from './Students';

// Create a reactive state for attendance data
const state = reactive({
    attendanceRecords: [],
    recordsByMonth: {},
    initialized: false
});

export const StudentAttendanceService = {
    // Get all attendance records
    getAllRecords() {
        return state.attendanceRecords;
    },

    // Add a new attendance record
    addRecord(record) {
        // Generate a unique ID if not provided
        if (!record.id) {
            record.id = Date.now();
        }

        // Add timestamp if not provided
        if (!record.timestamp) {
            record.timestamp = new Date().toISOString();
        }

        state.attendanceRecords.push(record);

        // Clear the cache as data has changed
        state.recordsByMonth = {};

        return record;
    },

    // Add batch records
    addRecords(records) {
        records.forEach((record) => {
            this.addRecord(record);
        });
        return records;
    },

    // Get records for a specific student
    getStudentRecords(studentId) {
        return state.attendanceRecords.filter((record) => record.studentId === studentId);
    },

    // Get records for students in a subject for a specific month
    getSubjectAttendanceRecords(studentIds, subjectId, month, year) {
        const key = `${subjectId}-${month}-${year}`;

        // Check cache first
        if (state.recordsByMonth[key]) {
            return state.recordsByMonth[key].filter((record) => studentIds.includes(record.studentId));
        }

        // If not in cache, filter from all records
        const startDate = new Date(year, month, 1);
        const endDate = new Date(year, month + 1, 0);

        const records = state.attendanceRecords.filter((record) => {
            const recordDate = new Date(record.date);
            return record.subjectId === subjectId && recordDate >= startDate && recordDate <= endDate;
        });

        // Cache the results
        state.recordsByMonth[key] = records;

        return records.filter((record) => studentIds.includes(record.studentId));
    },

    // Get students by subject
    async getStudentsBySubject(gradeId, subjectId) {
        try {
            // Fetch students in the grade
            const students = await AttendanceService.getStudentsInGrade(gradeId);
            return students;
        } catch (error) {
            console.error('Error fetching students by subject:', error);
            throw error;
        }
    },

    // Get weekly attendance counts for a subject
    getWeeklyAttendanceData(subjectId, month, year) {
        const records = this.getSubjectRecords(subjectId);
        const startDate = new Date(year, month, 1);
        const endDate = new Date(year, month + 1, 0);

        // Filter records for the specified month
        const monthRecords = records.filter((record) => {
            const recordDate = new Date(record.date);
            return recordDate >= startDate && recordDate <= endDate;
        });

        // Organize by weeks (assuming 4 weeks per month)
        const weeks = [
            { present: 0, absent: 0, late: 0 },
            { present: 0, absent: 0, late: 0 },
            { present: 0, absent: 0, late: 0 },
            { present: 0, absent: 0, late: 0 }
        ];

        monthRecords.forEach((record) => {
            const day = new Date(record.date).getDate();
            // Determine which week (roughly)
            const weekIndex = Math.min(Math.floor((day - 1) / 7), 3);

            // Count by status
            if (record.status === 'PRESENT') {
                weeks[weekIndex].present++;
            } else if (record.status === 'ABSENT') {
                weeks[weekIndex].absent++;
            } else if (record.status === 'LATE') {
                weeks[weekIndex].late++;
            }
        });

        // Format for chart data
        return {
            present: weeks.map((w) => w.present),
            absent: weeks.map((w) => w.absent),
            late: weeks.map((w) => w.late)
        };
    }
};
