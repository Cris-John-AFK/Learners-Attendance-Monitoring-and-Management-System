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
                { 
                    label: 'Present', 
                    backgroundColor: '#10b981', 
                    borderColor: '#10b981',
                    borderWidth: 1,
                    data: presentData,
                    stack: 'attendance'
                },
                { 
                    label: 'Absent', 
                    backgroundColor: '#ef4444', 
                    borderColor: '#ef4444',
                    borderWidth: 1,
                    data: absentData,
                    stack: 'attendance'
                },
                { 
                    label: 'Late', 
                    backgroundColor: '#f59e0b', 
                    borderColor: '#f59e0b',
                    borderWidth: 1,
                    data: lateData,
                    stack: 'attendance'
                },
                { 
                    label: 'Excused', 
                    backgroundColor: '#8b5cf6', 
                    borderColor: '#8b5cf6',
                    borderWidth: 1,
                    data: excusedData,
                    stack: 'attendance'
                }
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
            totalStudents: summary.total_students || 0,
            averageAttendance: summary.overall_attendance_rate || 0,
            warningCount,
            criticalCount
        };
    }

    /**
     * Get insights and recommendations from analytics data
     * @param {Object} analyticsData - Data from getAttendanceAnalytics API
     * @param {Object} currentGrade - Optional specific grade data for grade-specific insights
     */
    static getInsights(analyticsData, currentGrade = null) {
        if (!analyticsData || !analyticsData.grades || analyticsData.grades.length === 0) {
            return {
                bestPerformingGrade: 'No data available',
                highestAttendanceRate: 0,
                worstPerformingGrade: 'No data available',
                lowestAttendanceRate: 0,
                trendAnalysis: 'Insufficient data for analysis'
            };
        }

        // Find best and worst performing grades
        let bestGrade = analyticsData.grades[0];
        let worstGrade = analyticsData.grades[0];

        analyticsData.grades.forEach(grade => {
            if (grade.attendance_rate > bestGrade.attendance_rate) {
                bestGrade = grade;
            }
            if (grade.attendance_rate < worstGrade.attendance_rate) {
                worstGrade = grade;
            }
        });

        // Generate trend analysis based on current grade or overall rate
        const rateToAnalyze = currentGrade ? currentGrade.attendance_rate : (analyticsData.summary.overall_attendance_rate || 0);
        let trendAnalysis = '';
        
        if (rateToAnalyze >= 90) {
            trendAnalysis = currentGrade ? 'Excellent attendance for this grade' : 'Excellent attendance across all grades';
        } else if (rateToAnalyze >= 80) {
            trendAnalysis = currentGrade ? 'Good attendance with room for improvement' : 'Good attendance with room for improvement';
        } else if (rateToAnalyze >= 70) {
            trendAnalysis = currentGrade ? 'Moderate attendance - monitor closely' : 'Moderate attendance - intervention needed';
        } else {
            trendAnalysis = currentGrade ? 'Poor attendance - immediate action required' : 'Poor attendance - immediate action required';
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
