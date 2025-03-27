import { reactive } from 'vue';
import { AttendanceService } from './Students';
import { SubjectService } from './Subjects';

// Validate that required methods exist in the imported services
console.log('AttendanceService methods:', Object.keys(AttendanceService));
console.log('SubjectService methods:', Object.keys(SubjectService));

// Check if getData exists and what type it returns
if (typeof AttendanceService.getData !== 'function') {
    console.error('AttendanceService.getData is not a function');
} else {
    console.log('AttendanceService.getData type:', typeof AttendanceService.getData());
    // If it returns a promise, log the resolved value
    if (AttendanceService.getData() instanceof Promise) {
        AttendanceService.getData()
            .then((data) => console.log('AttendanceService.getData result type:', typeof data, Array.isArray(data)))
            .catch((err) => console.error('Error getting attendance data:', err));
    }
}

// Create a reactive state for attendance data
const state = reactive({
    // Track all attendance records for students by subject
    attendanceRecords: [],
    // Cache for quick lookups
    recordsByMonth: {}
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
        // Fetch students in the grade
        const students = await AttendanceService.getStudentsInGrade(gradeId);

        // In a real system, you'd check enrollment records for the subject
        // For simplicity, we'll assume all students in a grade take all subjects
        return students;
    },

    // Generate mock attendance data
    async generateMockData() {
        if (state.attendanceRecords.length > 0) {
            return; // Don't regenerate if we already have data
        }

        try {
            // Make sure to await the data retrieval
            const students = await AttendanceService.getData();

            // Validate that students is an array before proceeding
            if (!Array.isArray(students)) {
                console.error('Expected students to be an array but got:', typeof students);
                return;
            }

            const subjects = await SubjectService.getSubjects();
            if (!Array.isArray(subjects)) {
                console.error('Expected subjects to be an array but got:', typeof subjects);
                return;
            }

            const records = [];

            // Current month
            const now = new Date();
            const currentMonth = now.getMonth();
            const currentYear = now.getFullYear();

            // Generate records for each student for each subject
            students.forEach((student) => {
                subjects
                    .filter((subject) => subject.grade === `Grade ${student.gradeLevel}`)
                    .forEach((subject) => {
                        // Generate about 20 days worth of records for the current month
                        for (let day = 1; day <= 20; day++) {
                            // Skip weekends (Saturday=6, Sunday=0)
                            const date = new Date(currentYear, currentMonth, day);
                            const dayOfWeek = date.getDay();
                            if (dayOfWeek === 0 || dayOfWeek === 6) continue;

                            // Random attendance status
                            // 85% present, 10% absent, 5% late
                            const rand = Math.random() * 100;
                            let status = 'PRESENT';

                            // Students with IDs divisible by 5 have worse attendance
                            const poorAttendance = student.id % 5 === 0;

                            if (poorAttendance) {
                                // 60% present, 30% absent, 10% late
                                if (rand > 60) status = 'ABSENT';
                                else if (rand > 30) status = 'LATE';
                            } else {
                                if (rand > 90) status = 'ABSENT';
                                else if (rand > 85) status = 'LATE';
                            }

                            records.push({
                                id: records.length + 1,
                                studentId: student.id,
                                subjectId: subject.id,
                                date: new Date(currentYear, currentMonth, day).toISOString().split('T')[0],
                                status: status,
                                remarks: status === 'ABSENT' ? 'Unexcused absence' : '',
                                timestamp: new Date().toISOString()
                            });
                        }
                    });
            });

            state.attendanceRecords = records;
            console.log(`Generated ${records.length} mock attendance records`);
        } catch (error) {
            console.error('Error generating mock attendance data:', error);
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

// Initialize with some mock data - but do it properly with async/await
(async () => {
    try {
        await StudentAttendanceService.generateMockData();
    } catch (error) {
        console.error('Failed to generate mock attendance data:', error);
    }
})();
