import axios from 'axios';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

class AttendanceHeatmapService {
    /**
     * Get attendance reasons heatmap data with location correlations
     */
    async getAttendanceReasonsHeatmap(teacherId, options = {}) {
        try {
            const params = {
                teacher_id: teacherId,
                period: options.period || 'week',
                subject_id: options.subjectId || null
            };

            const response = await axios.get(`${API_BASE_URL}/attendance/heatmap/reasons`, {
                params
            });

            return response.data;
        } catch (error) {
            console.error('Error fetching attendance reasons heatmap:', error);
            throw error;
        }
    }

    /**
     * Process heatmap data for chart visualization
     */
    processHeatmapForChart(heatmapData) {
        if (!heatmapData || !heatmapData.data) {
            return null;
        }

        const data = heatmapData.data;

        // Prepare data for TRUE HEATMAP visualization
        const chartData = {
            // Matrix heatmap data (reasons vs locations)
            heatmapMatrix: this.createHeatmapMatrix(data),

            // Reasons frequency data
            reasonsChart: {
                labels: [...data.late_reasons.slice(0, 5).map((r) => `Late: ${r.reason}`), ...data.excused_reasons.slice(0, 5).map((r) => `Excused: ${r.reason}`)],
                datasets: [
                    {
                        label: 'Incidents',
                        data: [...data.late_reasons.slice(0, 5).map((r) => r.count), ...data.excused_reasons.slice(0, 5).map((r) => r.count)],
                        backgroundColor: [...Array(Math.min(5, data.late_reasons.length)).fill('rgba(255, 193, 7, 0.8)'), ...Array(Math.min(5, data.excused_reasons.length)).fill('rgba(13, 202, 240, 0.8)')],
                        borderColor: [...Array(Math.min(5, data.late_reasons.length)).fill('rgba(255, 193, 7, 1)'), ...Array(Math.min(5, data.excused_reasons.length)).fill('rgba(13, 202, 240, 1)')],
                        borderWidth: 1
                    }
                ]
            },

            // Location correlation data
            locationChart: {
                labels: data.location_correlations.slice(0, 10).map((l) => l.location),
                datasets: [
                    {
                        label: 'Late',
                        data: data.location_correlations.slice(0, 10).map((l) => l.late_count),
                        backgroundColor: 'rgba(255, 193, 7, 0.8)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Excused',
                        data: data.location_correlations.slice(0, 10).map((l) => l.excused_count),
                        backgroundColor: 'rgba(13, 202, 240, 0.8)',
                        borderColor: 'rgba(13, 202, 240, 1)',
                        borderWidth: 1
                    }
                ]
            },

            // Timeline data
            timelineChart: {
                labels: data.timeline_data.map((t) => new Date(t.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [
                    {
                        label: 'Late',
                        data: data.timeline_data.map((t) => t.late),
                        borderColor: 'rgba(255, 193, 7, 1)',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Excused',
                        data: data.timeline_data.map((t) => t.excused),
                        borderColor: 'rgba(13, 202, 240, 1)',
                        backgroundColor: 'rgba(13, 202, 240, 0.1)',
                        fill: true,
                        tension: 0.4
                    }
                ]
            },

            // Summary stats
            summary: data.summary,

            // Raw data for detailed views
            rawData: data
        };

        return chartData;
    }

    /**
     * Create heatmap matrix data for true heatmap visualization
     */
    createHeatmapMatrix(data) {
        // Create a matrix of reasons vs locations with intensity values
        const lateReasons = data.late_reasons.slice(0, 8);
        const excusedReasons = data.excused_reasons.slice(0, 8);
        const reasons = [...lateReasons, ...excusedReasons];
        const reasonTypes = [...lateReasons.map(() => 'Late'), ...excusedReasons.map(() => 'Excused')];
        const locations = data.location_correlations.slice(0, 10);

        const matrix = [];

        reasons.forEach((reason, reasonIndex) => {
            const reasonType = reasonTypes[reasonIndex];
            const rowDivider = reasonIndex > 0 && reasonType !== reasonTypes[reasonIndex - 1];
            locations.forEach((location, locationIndex) => {
                // Calculate intensity based on correlation
                const isLate = reasonIndex < data.late_reasons.length;
                const intensity = isLate ? location.late_count : location.excused_count;

                matrix.push({
                    x: locationIndex,
                    y: reasonIndex,
                    v: intensity, // intensity value
                    reason: reason.reason,
                    location: location.location,
                    type: isLate ? 'Late' : 'Excused',
                    count: intensity,
                    rowDivider
                });
            });
        });

        return {
            data: matrix,
            xLabels: locations.map((l) => l.location),
            yLabels: reasons.map((r) => r.reason),
            reasonTypes,
            maxValue: Math.max(...matrix.map((m) => m.v))
        };
    }

    /**
     * Get chart options for different chart types
     */
    getChartOptions(chartType) {
        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            }
        };

        switch (chartType) {
            case 'reasons':
                return {
                    ...baseOptions,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents'
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Reasons'
                            }
                        }
                    },
                    plugins: {
                        ...baseOptions.plugins,
                        title: {
                            display: true,
                            text: 'Most Common Reasons for Late/Excused'
                        }
                    }
                };

            case 'location':
                return {
                    ...baseOptions,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Locations'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents'
                            }
                        }
                    },
                    plugins: {
                        ...baseOptions.plugins,
                        title: {
                            display: true,
                            text: 'Attendance Issues by Location'
                        }
                    }
                };

            case 'timeline':
                return {
                    ...baseOptions,
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents'
                            }
                        }
                    },
                    plugins: {
                        ...baseOptions.plugins,
                        title: {
                            display: true,
                            text: 'Attendance Issues Timeline'
                        }
                    }
                };

            default:
                return baseOptions;
        }
    }
}

export default new AttendanceHeatmapService();
