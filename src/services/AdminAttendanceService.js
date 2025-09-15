import axios from '@/config/axios';

export class AdminAttendanceService {
    /**
     * Get attendance analytics aggregated by grades
     * @param {string} dateRange - 'current_year', 'last_30_days', 'last_7_days'
     * @param {number} gradeId - Optional grade ID to filter by specific grade
     */
    static async getAttendanceAnalytics(dateRange = 'current_year', gradeId = null) {
        try {
            const params = { date_range: dateRange };
            if (gradeId) {
                params.grade_id = gradeId;
            }

            const response = await axios.get('/api/admin/attendance/analytics', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance analytics:', error);
            throw error;
        }
    }

    /**
     * Get attendance trends over time
     * @param {string} dateRange - 'current_year', 'last_30_days', 'last_7_days'
     * @param {number} gradeId - Optional grade ID to filter by specific grade
     */
    static async getAttendanceTrends(dateRange = 'last_30_days', gradeId = null) {
        try {
            const params = { date_range: dateRange };
            if (gradeId) {
                params.grade_id = gradeId;
            }

            const response = await axios.get('/api/admin/attendance/trends', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance trends:', error);
            throw error;
        }
    }

    /**
     * Transform API data to chart format
     * @param {Object} analyticsData - Data from getAttendanceAnalytics API
     */
    static transformToChartData(analyticsData) {
        if (!analyticsData || !analyticsData.grades || analyticsData.grades.length === 0) {
            return {
                labels: [],
                datasets: [
                    { label: 'Present', backgroundColor: '#10b981', data: [] },
                    { label: 'Absent', backgroundColor: '#ef4444', data: [] },
                    { label: 'Late', backgroundColor: '#f59e0b', data: [] },
                    { label: 'Excused', backgroundColor: '#8b5cf6', data: [] }
                ]
            };
        }

        const labels = analyticsData.grades.map(grade => grade.grade_name);
        const presentData = analyticsData.grades.map(grade => grade.present);
        const absentData = analyticsData.grades.map(grade => grade.absent);
        const lateData = analyticsData.grades.map(grade => grade.late);
        const excusedData = analyticsData.grades.map(grade => grade.excused);

        return {
            labels,
            datasets: [
                { label: 'Present', backgroundColor: '#10b981', data: presentData },
                { label: 'Absent', backgroundColor: '#ef4444', data: absentData },
                { label: 'Late', backgroundColor: '#f59e0b', data: lateData },
                { label: 'Excused', backgroundColor: '#8b5cf6', data: excusedData }
            ]
        };
    }

    /**
     * Calculate summary statistics from analytics data
     * @param {Object} analyticsData - Data from getAttendanceAnalytics API
     */
    static calculateSummaryStats(analyticsData) {
        if (!analyticsData || !analyticsData.summary) {
            return {
                totalStudents: 0,
                averageAttendance: 0,
                warningCount: 0,
                criticalCount: 0
            };
        }

        const summary = analyticsData.summary;
        
        // Calculate students with warning/critical attendance (grades with <80% and <70% attendance)
        let warningCount = 0;
        let criticalCount = 0;
        
        if (analyticsData.grades) {
            analyticsData.grades.forEach(grade => {
                if (grade.attendance_rate < 70) {
                    criticalCount++;
                } else if (grade.attendance_rate < 80) {
                    warningCount++;
                }
            });
        }

        return {
            totalStudents: summary.total_records || 0,
            averageAttendance: summary.overall_attendance_rate || 0,
            warningCount,
            criticalCount
        };
    }

    /**
     * Get insights from analytics data
     * @param {Object} analyticsData - Data from getAttendanceAnalytics API
     */
    static getInsights(analyticsData) {
        if (!analyticsData || !analyticsData.grades || analyticsData.grades.length === 0) {
            return {
                bestPerformingGrade: 'N/A',
                highestAttendanceRate: 0,
                worstPerformingGrade: 'N/A',
                lowestAttendanceRate: 0,
                trendAnalysis: 'No data available'
            };
        }

        const grades = analyticsData.grades;
        
        // Find best and worst performing grades
        let bestGrade = grades[0];
        let worstGrade = grades[0];
        
        grades.forEach(grade => {
            if (grade.attendance_rate > bestGrade.attendance_rate) {
                bestGrade = grade;
            }
            if (grade.attendance_rate < worstGrade.attendance_rate) {
                worstGrade = grade;
            }
        });

        // Generate trend analysis
        const overallRate = analyticsData.summary.overall_attendance_rate || 0;
        let trendAnalysis = '';
        
        if (overallRate >= 90) {
            trendAnalysis = 'Excellent attendance across all grades';
        } else if (overallRate >= 80) {
            trendAnalysis = 'Good attendance with room for improvement';
        } else if (overallRate >= 70) {
            trendAnalysis = 'Moderate attendance - intervention needed';
        } else {
            trendAnalysis = 'Poor attendance - immediate action required';
        }

        return {
            bestPerformingGrade: bestGrade.grade_name,
            highestAttendanceRate: bestGrade.attendance_rate,
            worstPerformingGrade: worstGrade.grade_name,
            lowestAttendanceRate: worstGrade.attendance_rate,
            trendAnalysis
        };
    }
}
