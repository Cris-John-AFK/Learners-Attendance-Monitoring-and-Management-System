import axios from 'axios';

const API_BASE_URL = 'http://127.0.0.1:8000/api';

export class AttendanceRecordsService {
    // Cache for storing frequently accessed data
    static cache = new Map();
    static cacheTimeout = 5 * 60 * 1000; // 5 minutes

    /**
     * Get from cache or fetch fresh data
     */
    static async getCachedData(key, fetchFunction) {
        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
            return cached.data;
        }

        const data = await fetchFunction();
        this.cache.set(key, { data, timestamp: Date.now() });
        return data;
    }

    /**
     * Get attendance report for a section with date range and subject filtering
     */
    static async getAttendanceReport(sectionId, params = {}) {
        try {
            const response = await axios.get(`${API_BASE_URL}/attendance-records/section/${sectionId}`, {
                params: {
                    start_date: params.startDate,
                    end_date: params.endDate,
                    subject_id: params.subjectId
                }
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance report:', error);
            throw error;
        }
    }

    /**
     * Get weekly attendance report using attendance sessions
     */
    static async getWeeklyReport(sectionId, weekStart, subjectId = null) {
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
     * Get teacher's homeroom sections only (not all teaching assignments)
     */
    static async getTeacherHomeroomSections(teacherId) {
        try {
            const cacheKey = `teacher_homeroom_${teacherId}`;
            return await this.getCachedData(cacheKey, async () => {
                const response = await axios.get(`${API_BASE_URL}/teachers/${teacherId}/assignments`);
                console.log('Raw API response:', response.data);
                
                if (!response.data.success) {
                    throw new Error(response.data.message || 'Failed to fetch assignments');
                }
                
                const assignments = response.data.assignments || [];
                
                // Transform assignments into sections format and get homeroom sections
                // First, get all sections this teacher is assigned to
                const sectionsResponse = await axios.get(`${API_BASE_URL}/sections`);
                const allSections = sectionsResponse.data.sections || sectionsResponse.data || [];
                
                // Filter to only homeroom sections (where teacher is homeroom_teacher_id)
                const homeroomSections = allSections.filter(section => 
                    section.homeroom_teacher_id === parseInt(teacherId)
                );
                
                // Add subjects to each homeroom section from assignments
                const sectionsWithSubjects = homeroomSections.map(section => {
                    const sectionAssignments = assignments.find(assignment => 
                        assignment.section_id === section.id
                    );
                    
                    return {
                        id: section.id,
                        name: section.name,
                        homeroom_teacher_id: section.homeroom_teacher_id,
                        subjects: sectionAssignments?.subjects || []
                    };
                });
                
                return {
                    success: true,
                    sections: sectionsWithSubjects
                };
            });
        } catch (error) {
            console.error('Error fetching teacher homeroom sections:', error);
            throw error;
        }
    }

    /**
     * Get all sections teacher has access to (for student search)
     */
    static async getAllTeacherSections(teacherId) {
        try {
            const cacheKey = `teacher_all_sections_${teacherId}`;
            return await this.getCachedData(cacheKey, async () => {
                const response = await axios.get(`${API_BASE_URL}/teachers/${teacherId}/assignments`);
                return response.data;
            });
        } catch (error) {
            console.error('Error fetching all teacher sections:', error);
            throw error;
        }
    }

    /**
     * Get attendance session dates for a section (for date picker highlighting)
     */
    static async getAttendanceSessionDates(sectionId, startDate, endDate) {
        try {
            const cacheKey = `session_dates_${sectionId}_${startDate}_${endDate}`;
            return await this.getCachedData(cacheKey, async () => {
                const response = await axios.get(`${API_BASE_URL}/attendance-sessions/dates`, {
                    params: {
                        section_id: sectionId,
                        start_date: startDate,
                        end_date: endDate
                    }
                });
                return response.data;
            });
        } catch (error) {
            console.error('Error fetching session dates:', error);
            return { dates: [] }; // Fallback to empty array
        }
    }

    /**
     * Search students across multiple sections by name and ID
     */
    static async searchStudents(teacherId, searchQuery) {
        try {
            const cacheKey = `student_search_${teacherId}_${searchQuery}`;
            return await this.getCachedData(cacheKey, async () => {
                const response = await axios.get(`${API_BASE_URL}/teachers/${teacherId}/students/search`, {
                    params: { q: searchQuery }
                });
                return response.data;
            });
        } catch (error) {
            console.error('Error searching students:', error);
            return { students: [] };
        }
    }

    /**
     * Get attendance session dates for a section (for date picker highlighting)
     */
    static async getAttendanceSessionDates(sectionId, startDate, endDate) {
        try {
            const cacheKey = `session_dates_${sectionId}_${startDate}_${endDate}`;
            return await this.getCachedData(cacheKey, async () => {
                // Use weekly report endpoint to get session dates
                const response = await axios.get(`${API_BASE_URL}/attendance-sessions/reports/weekly`, {
                    params: {
                        section_id: sectionId,
                        week_start: startDate
                    }
                });
                
                // Extract dates from the weekly report
                const sessions = response.data.sessions || [];
                const dates = sessions.map(session => session.session_date).filter(Boolean);
                
                return { dates: [...new Set(dates)] }; // Remove duplicates
            });
        } catch (error) {
            console.error('Error fetching session dates:', error);
            return { dates: [] }; // Fallback to empty array
        }
    }

    /**
     * Get students in a section
     */
    static async getStudentsInSection(sectionId, teacherId = 1) {
        try {
            // Use the correct endpoint from student-management routes with required teacher_id
            const response = await axios.get(`${API_BASE_URL}/student-management/sections/${sectionId}/students`, {
                params: {
                    teacher_id: teacherId
                }
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching students in section:', error);
            throw error;
        }
    }

    /**
     * Get attendance sessions for a section within date range
     */
    static async getAttendanceSessions(sectionId, startDate, endDate, subjectId = null) {
        try {
            const params = {
                section_id: sectionId,
                start_date: startDate,
                end_date: endDate
            };
            
            if (subjectId) {
                params.subject_id = subjectId;
            }

            const response = await axios.get(`${API_BASE_URL}/attendance-sessions`, {
                params
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance sessions:', error);
            throw error;
        }
    }

    /**
     * Transform attendance sessions data into matrix format for display
     */
    static transformToMatrix(sessions, students, dateRange) {
        const matrix = [];
        
        // Create a map of attendance records by student, date, and subject
        const attendanceMap = new Map();
        
        sessions.forEach(session => {
            const sessionDate = session.session_date;
            
            session.attendance_records?.forEach(record => {
                const studentKey = `${record.student_id}-${sessionDate}`;
                
                if (!attendanceMap.has(studentKey)) {
                    attendanceMap.set(studentKey, []);
                }
                
                attendanceMap.get(studentKey).push({
                    status: record.attendance_status?.name || 'Unmarked',
                    status_code: record.attendance_status?.code || 'U',
                    arrival_time: record.arrival_time,
                    remarks: record.remarks,
                    subject: session.subject?.name || 'General',
                    subject_id: session.subject?.id,
                    marked_at: record.marked_at,
                    session_id: session.id
                });
            });
        });

        // Create matrix rows for each student
        students.forEach(student => {
            const row = {
                id: student.id,
                name: student.name || `${student.firstName} ${student.lastName}`,
                gradeLevel: student.gradeLevel,
                section: student.section?.name || 'Unknown'
            };

            // Add attendance data for each date
            dateRange.forEach(date => {
                const studentKey = `${student.id}-${date}`;
                const dayRecords = attendanceMap.get(studentKey) || [];
                
                if (dayRecords.length === 0) {
                    row[date] = null;
                } else if (dayRecords.length === 1) {
                    // Single subject - show direct status
                    row[date] = dayRecords[0].status;
                } else {
                    // Multiple subjects - calculate overall status
                    const statuses = dayRecords.map(r => r.status);
                    const presentCount = statuses.filter(s => s === 'Present').length;
                    const absentCount = statuses.filter(s => s === 'Absent').length;
                    const lateCount = statuses.filter(s => s === 'Late').length;
                    const excusedCount = statuses.filter(s => s === 'Excused').length;
                    
                    // Determine overall status
                    if (absentCount === 0 && lateCount === 0) {
                        row[date] = 'Present'; // All present/excused
                    } else if (presentCount === 0 && excusedCount === 0 && lateCount === 0) {
                        row[date] = 'Absent'; // All absent
                    } else if (lateCount > 0 && absentCount === 0) {
                        row[date] = 'Late'; // Has late but no absent
                    } else {
                        row[date] = 'Mixed'; // Mixed attendance
                    }
                }
                
                // Store detailed records for expandable view
                row[`${date}_details`] = dayRecords;
            });

            matrix.push(row);
        });

        return matrix;
    }

    /**
     * Generate date range array between start and end dates
     */
    static generateDateRange(startDate, endDate) {
        const dates = [];
        const current = new Date(startDate);
        const end = new Date(endDate);

        while (current <= end) {
            dates.push(current.toISOString().split('T')[0]);
            current.setDate(current.getDate() + 1);
        }

        return dates;
    }

    /**
     * Calculate attendance statistics for a student
     */
    static calculateStudentStats(studentRow, dateColumns) {
        let present = 0;
        let absent = 0;
        let late = 0;
        let excused = 0;
        let mixed = 0;
        let total = 0;

        dateColumns.forEach(date => {
            const status = studentRow[date];
            if (status) {
                total++;
                switch (status) {
                    case 'Present':
                        present++;
                        break;
                    case 'Absent':
                        absent++;
                        break;
                    case 'Late':
                        late++;
                        break;
                    case 'Excused':
                        excused++;
                        break;
                    case 'Mixed':
                        mixed++;
                        // For mixed days, count as 1 absence if any subject was absent (consistent with main table)
                        const details = studentRow[`${date}_details`] || [];
                        const hasAbsent = details.some(record => record.status === 'Absent');
                        const hasLate = details.some(record => record.status === 'Late');
                        if (hasAbsent) absent++;
                        if (hasLate) late++;
                        break;
                }
            }
        });

        const attendanceRate = total > 0 ? Math.round(((present + late + excused + mixed) / total) * 100) : 0;
        const absentRate = total > 0 ? Math.round((absent / total) * 100) : 0;

        return {
            present,
            absent,
            late,
            excused,
            mixed,
            total,
            attendanceRate,
            absentRate,
            absentDays: absent,
            lateDays: late,
            lateRate: total > 0 ? Math.round((late / total) * 100) : 0,
            hasIssues: absentRate >= 20 || late >= 3,
            issueLevel: absentRate >= 30 || late >= 5 ? 'Warning' : 'Normal'
        };
    }
}
