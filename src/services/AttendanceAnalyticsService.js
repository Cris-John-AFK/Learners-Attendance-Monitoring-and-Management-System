import api from '@/config/axios';

export class AttendanceAnalyticsService {
    /**
     * Get attendance overview for admin dashboard
     */
    static async getOverview(dateFrom = null, dateTo = null) {
        try {
            const params = {};
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;

            const response = await api.get('/api/admin/attendance-analytics/overview', { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching attendance overview:', error);
            throw error;
        }
    }

    /**
     * Get grade details with sections breakdown
     */
    static async getGradeDetails(gradeId, dateFrom = null, dateTo = null) {
        try {
            const params = {};
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;

            const response = await api.get(`/api/admin/attendance-analytics/grade/${gradeId}`, { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching grade details:', error);
            throw error;
        }
    }

    /**
     * Get section details with students
     */
    static async getSectionDetails(sectionId, dateFrom = null, dateTo = null) {
        try {
            const params = {};
            if (dateFrom) params.date_from = dateFrom;
            if (dateTo) params.date_to = dateTo;

            const response = await api.get(`/api/admin/attendance-analytics/section/${sectionId}`, { params });
            return response.data;
        } catch (error) {
            console.error('Error fetching section details:', error);
            throw error;
        }
    }

    /**
     * Transform overview data to chart format
     */
    static transformOverviewToChartData(overviewData) {
        if (!overviewData.grade_breakdown || overviewData.grade_breakdown.length === 0) {
            return {
                labels: [],
                datasets: []
            };
        }

        const labels = overviewData.grade_breakdown.map(grade => grade.grade_name);
        
        return {
            labels,
            datasets: [
                {
                    label: 'Present',
                    backgroundColor: '#10b981',
                    borderColor: '#10b981',
                    data: overviewData.grade_breakdown.map(grade => grade.present_count || 0)
                },
                {
                    label: 'Absent', 
                    backgroundColor: '#ef4444',
                    borderColor: '#ef4444',
                    data: overviewData.grade_breakdown.map(grade => grade.absent_count || 0)
                },
                {
                    label: 'Late',
                    backgroundColor: '#f59e0b',
                    borderColor: '#f59e0b',
                    data: overviewData.grade_breakdown.map(grade => grade.late_count || 0)
                }
            ]
        };
    }

    /**
     * Transform attendance trend data to line chart format
     */
    static transformTrendToChartData(trendData) {
        if (!trendData || trendData.length === 0) {
            return {
                labels: [],
                datasets: []
            };
        }

        const labels = trendData.map(day => {
            const date = new Date(day.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });

        return {
            labels,
            datasets: [
                {
                    label: 'Attendance %',
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    data: trendData.map(day => day.attendance_percentage || 0),
                    tension: 0.4,
                    fill: true
                }
            ]
        };
    }

    /**
     * Transform section data to chart format
     */
    static transformSectionToChartData(sectionData) {
        if (!sectionData || sectionData.length === 0) {
            return {
                labels: [],
                datasets: []
            };
        }

        const labels = sectionData.map(section => section.section_name);
        
        return {
            labels,
            datasets: [
                {
                    label: 'Present',
                    backgroundColor: '#10b981',
                    data: sectionData.map(section => section.present_count || 0)
                },
                {
                    label: 'Absent',
                    backgroundColor: '#ef4444', 
                    data: sectionData.map(section => section.absent_count || 0)
                },
                {
                    label: 'Late',
                    backgroundColor: '#f59e0b',
                    data: sectionData.map(section => section.late_count || 0)
                }
            ]
        };
    }

    /**
     * Get chart options for different chart types
     */
    static getChartOptions(type = 'bar') {
        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: 500
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#333',
                    bodyColor: '#555',
                    borderColor: '#e1e1e1',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12
                }
            },
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            }
        };

        if (type === 'line') {
            return {
                ...baseOptions,
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#666'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: { size: 11 },
                            color: '#666',
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            };
        }

        return {
            ...baseOptions,
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: { size: 11 },
                        color: '#666'
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        font: { size: 11 },
                        color: '#666'
                    }
                }
            }
        };
    }

    /**
     * Format date for API requests
     */
    static formatDate(date) {
        if (!date) return null;
        return date.toISOString().split('T')[0];
    }

    /**
     * Get date range presets
     */
    static getDateRangePresets() {
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        const lastWeek = new Date(today);
        lastWeek.setDate(lastWeek.getDate() - 7);
        
        const lastMonth = new Date(today);
        lastMonth.setMonth(lastMonth.getMonth() - 1);

        return {
            today: {
                label: 'Today',
                from: this.formatDate(today),
                to: this.formatDate(today)
            },
            yesterday: {
                label: 'Yesterday', 
                from: this.formatDate(yesterday),
                to: this.formatDate(yesterday)
            },
            lastWeek: {
                label: 'Last 7 Days',
                from: this.formatDate(lastWeek),
                to: this.formatDate(today)
            },
            lastMonth: {
                label: 'Last 30 Days',
                from: this.formatDate(lastMonth),
                to: this.formatDate(today)
            }
        };
    }
}
