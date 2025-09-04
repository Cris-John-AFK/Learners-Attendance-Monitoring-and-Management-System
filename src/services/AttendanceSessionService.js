import axios from 'axios';

const API_BASE_URL = 'http://127.0.0.1:8000/api';

class AttendanceSessionService {
    /**
     * Create a new attendance session
     */
    async createSession(sessionData) {
        try {
            const response = await axios.post(`${API_BASE_URL}/attendance-sessions`, {
                teacher_id: sessionData.teacherId,
                section_id: sessionData.sectionId,
                subject_id: sessionData.subjectId,
                session_date: sessionData.date,
                session_start_time: sessionData.startTime,
                session_type: sessionData.type || 'regular',
                metadata: sessionData.metadata || {}
            });
            return response.data;
        } catch (error) {
            console.error('Error creating attendance session:', error);
            throw error;
        }
    }

    /**
     * Get active sessions for a teacher
     */
    async getActiveSessionsForTeacher(teacherId) {
        try {
            const response = await axios.get(`${API_BASE_URL}/attendance-sessions/teacher/${teacherId}/active`);
            return response.data.sessions;
        } catch (error) {
            console.error('Error fetching active sessions:', error);
            throw error;
        }
    }

    /**
     * Mark attendance for multiple students in a session
     */
    async markSessionAttendance(sessionId, attendanceData) {
        try {
            const response = await axios.post(`${API_BASE_URL}/attendance-sessions/${sessionId}/attendance`, {
                attendance: attendanceData
            });
            return response.data;
        } catch (error) {
            console.error('Error marking session attendance:', error);
            throw error;
        }
    }

    /**
     * Mark single student attendance via QR scan
     */
    async markQRAttendance(sessionId, studentData) {
        try {
            const response = await axios.post(`${API_BASE_URL}/attendance-sessions/${sessionId}/qr-attendance`, {
                student_id: studentData.studentId,
                attendance_status_id: studentData.statusId,
                arrival_time: studentData.arrivalTime,
                location_data: studentData.locationData
            });
            return response.data;
        } catch (error) {
            console.error('Error marking QR attendance:', error);
            throw error;
        }
    }

    /**
     * Complete an attendance session
     */
    async completeSession(sessionId) {
        try {
            const response = await axios.post(`${API_BASE_URL}/attendance-sessions/${sessionId}/complete`);
            return response.data;
        } catch (error) {
            console.error('Error completing session:', error);
            throw error;
        }
    }

    /**
     * Get session summary with statistics
     */
    async getSessionSummary(sessionId) {
        try {
            const response = await axios.get(`${API_BASE_URL}/attendance-sessions/${sessionId}/summary`);
            return response.data;
        } catch (error) {
            console.error('Error fetching session summary:', error);
            throw error;
        }
    }

    /**
     * Get attendance statuses (Present, Absent, Late, Excused)
     */
    async getAttendanceStatuses() {
        try {
            const response = await axios.get(`${API_BASE_URL}/attendance/statuses`);
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance statuses:', error);
            throw error;
        }
    }

    /**
     * Get weekly attendance report
     */
    async getWeeklyReport(sectionId, weekStart, subjectId = null) {
        try {
            const params = {
                section_id: sectionId,
                week_start: weekStart
            };
            if (subjectId) {
                params.subject_id = subjectId;
            }

            const response = await axios.get(`${API_BASE_URL}/attendance-sessions/reports/weekly`, {
                params
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching weekly report:', error);
            throw error;
        }
    }

    /**
     * Get monthly attendance report
     */
    async getMonthlyReport(sectionId, month, subjectId = null) {
        try {
            const params = {
                section_id: sectionId,
                month: month // Format: YYYY-MM
            };
            if (subjectId) {
                params.subject_id = subjectId;
            }

            const response = await axios.get(`${API_BASE_URL}/attendance-sessions/reports/monthly`, {
                params
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching monthly report:', error);
            throw error;
        }
    }

    /**
     * Helper method to format attendance data for API
     */
    formatAttendanceForAPI(studentId, statusId, remarks = null, arrivalTime = null) {
        return {
            student_id: studentId,
            attendance_status_id: statusId,
            arrival_time: arrivalTime || new Date().toTimeString().split(' ')[0],
            remarks: remarks,
            marking_method: 'manual'
        };
    }

    /**
     * Helper method to format QR attendance data
     */
    formatQRAttendanceForAPI(studentId, statusId = null, locationData = null) {
        return {
            student_id: studentId,
            attendance_status_id: statusId,
            arrival_time: new Date().toTimeString().split(' ')[0],
            location_data: locationData,
            marking_method: 'qr_scan'
        };
    }

    /**
     * Batch mark attendance for seat plan
     */
    async markSeatPlanAttendance(sessionId, seatPlan, attendanceStatuses) {
        try {
            const attendanceData = [];
            
            // Extract attendance data from seat plan
            seatPlan.forEach(row => {
                row.forEach(seat => {
                    if (seat.isOccupied && seat.studentId && seat.status !== null) {
                        // Map seat status to attendance status ID
                        let statusId;
                        switch (seat.status) {
                            case 1: // Present
                                statusId = attendanceStatuses.find(s => s.code === 'P')?.id;
                                break;
                            case 2: // Absent
                                statusId = attendanceStatuses.find(s => s.code === 'A')?.id;
                                break;
                            case 3: // Late
                                statusId = attendanceStatuses.find(s => s.code === 'L')?.id;
                                break;
                            case 4: // Excused
                                statusId = attendanceStatuses.find(s => s.code === 'E')?.id;
                                break;
                            default:
                                statusId = attendanceStatuses.find(s => s.code === 'P')?.id; // Default to Present
                        }

                        if (statusId) {
                            attendanceData.push(this.formatAttendanceForAPI(
                                seat.studentId,
                                statusId,
                                seat.remarks || null
                            ));
                        }
                    }
                });
            });

            if (attendanceData.length === 0) {
                throw new Error('No attendance data to submit');
            }

            return await this.markSessionAttendance(sessionId, attendanceData);
        } catch (error) {
            console.error('Error marking seat plan attendance:', error);
            throw error;
        }
    }
}

export default new AttendanceSessionService();
