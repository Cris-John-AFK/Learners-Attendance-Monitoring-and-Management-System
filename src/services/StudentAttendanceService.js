import axios from 'axios';

class StudentAttendanceService {
    constructor() {
        this.api = axios.create({
            baseURL: '/api',
            timeout: 10000
        });
    }

    /**
     * Get attendance records for specific students in a subject
     */
    async getSubjectAttendanceRecords(studentIds, subjectId, month, year) {
        try {
            const response = await this.api.get('/attendance/records', {
                params: {
                    student_ids: Array.isArray(studentIds) ? studentIds.join(',') : studentIds,
                    subject_id: subjectId,
                    month: month,
                    year: year
                }
            });
            
            if (response.data.success) {
                return {
                    success: true,
                    records: response.data.records || []
                };
            } else {
                console.error('API returned error:', response.data.message);
                return {
                    success: false,
                    records: []
                };
            }
        } catch (error) {
            console.error('Error fetching attendance records:', error);
            return {
                success: false,
                records: []
            };
        }
    }

    /**
     * Get weekly attendance data for a subject
     */
    async getWeeklyAttendanceData(subjectId, month, year) {
        try {
            const response = await this.api.get('/attendance/weekly', {
                params: {
                    subject_id: subjectId,
                    month: month,
                    year: year
                }
            });
            return response.data.weekly_data || [];
        } catch (error) {
            console.error('Error fetching weekly attendance data:', error);
            // Return mock data for now
            return [
                { week: 'Week 1', present: 4, absent: 1, late: 0, percentage: 80 },
                { week: 'Week 2', present: 5, absent: 0, late: 0, percentage: 100 },
                { week: 'Week 3', present: 3, absent: 2, late: 0, percentage: 60 },
                { week: 'Week 4', present: 4, absent: 0, late: 1, percentage: 80 }
            ];
        }
    }

    /**
     * Get student attendance summary
     */
    async getStudentAttendanceSummary(studentId, subjectId = null) {
        try {
            const params = { student_id: studentId };
            if (subjectId) {
                params.subject_id = subjectId;
            }

            const response = await this.api.get('/attendance/student/summary', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching student attendance summary:', error);
            return {
                total_present: 0,
                total_absent: 0,
                total_late: 0,
                total_excused: 0,
                attendance_rate: 0
            };
        }
    }
}

export { StudentAttendanceService };
export default new StudentAttendanceService();
