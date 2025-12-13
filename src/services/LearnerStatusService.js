import axios from 'axios';

const API_URL = (import.meta.env.VITE_API_BASE_URL || '') + '/api';

export default {
    /**
     * Get students for teacher's learner status management
     * @param {number} teacherId
     * @param {string} viewType - 'section', 'subject', or 'all'
     */
    async getStudentsForTeacher(teacherId, viewType = 'section') {
        try {
            const response = await axios.get(`${API_URL}/teacher/${teacherId}/learner-status/students`, {
                params: { view_type: viewType }
            });
            return response.data;
        } catch (error) {
            console.error('Error fetching students:', error);
            throw error;
        }
    },

    /**
     * Update student enrollment status
     * @param {number} teacherId
     * @param {number} studentId
     * @param {object} statusData - { new_status, reason, reason_category, effective_date, notes }
     */
    async updateStudentStatus(teacherId, studentId, statusData) {
        try {
            const response = await axios.put(`${API_URL}/teacher/${teacherId}/learner-status/students/${studentId}/status`, {
                teacher_id: teacherId,
                ...statusData
            });
            return response.data;
        } catch (error) {
            console.error('Error updating student status:', error);
            throw error;
        }
    },

    /**
     * Get status change history for a student
     * @param {number} teacherId
     * @param {number} studentId
     */
    async getStudentStatusHistory(teacherId, studentId) {
        try {
            const response = await axios.get(`${API_URL}/teacher/${teacherId}/learner-status/students/${studentId}/history`);
            return response.data;
        } catch (error) {
            console.error('Error fetching status history:', error);
            throw error;
        }
    },

    /**
     * Get dropout/transfer reason options based on DepEd guidelines
     */
    getReasonOptions() {
        return {
            dropped_out: [
                {
                    category: 'domestic',
                    label: 'Domestic-Related Factors',
                    reasons: [
                        { value: 'a1', label: 'a.1 Had to take care of siblings' },
                        { value: 'a2', label: 'a.2 Early marriage/pregnancy' },
                        { value: 'a3', label: "a.3 Parents' attitude toward schooling" },
                        { value: 'a4', label: 'a.4 Family problems' }
                    ]
                },
                {
                    category: 'individual',
                    label: 'Individual-Related Factors',
                    reasons: [
                        { value: 'b1', label: 'b.1 Illness' },
                        { value: 'b2', label: 'b.2 Disease' },
                        { value: 'b3', label: 'b.3 Death' },
                        { value: 'b4', label: 'b.4 Disability' },
                        { value: 'b5', label: 'b.5 Poor academic performance' },
                        { value: 'b6', label: 'b.6 Disinterest/lack of ambitions' },
                        { value: 'b7', label: 'b.7 Hunger/Malnutrition' }
                    ]
                },
                {
                    category: 'school',
                    label: 'School-Related Factors',
                    reasons: [
                        { value: 'c1', label: 'c.1 Teacher Factor' },
                        { value: 'c2', label: 'c.2 Physical condition of classroom' },
                        { value: 'c3', label: 'c.3 Peer Factor' }
                    ]
                },
                {
                    category: 'geographical',
                    label: 'Geographical/Environmental',
                    reasons: [
                        { value: 'd1', label: 'd.1 Distance from home to school' },
                        { value: 'd2', label: 'd.2 Armed conflict (incl. Tribal wars & clan feuds)' },
                        { value: 'd3', label: 'd.3 Calamities/disaster' },
                        { value: 'd4', label: 'd.4 Work-Related' },
                        { value: 'd5', label: 'd.5 Transferred/work' }
                    ]
                }
            ],
            transferred_out: [
                {
                    category: 'domestic',
                    label: 'Domestic-Related Factors',
                    reasons: [
                        { value: 'a1', label: 'a.1 Had to take care of siblings' },
                        { value: 'a2', label: 'a.2 Early marriage/pregnancy' },
                        { value: 'a3', label: "a.3 Parents' attitude toward schooling" },
                        { value: 'a4', label: 'a.4 Family problems' }
                    ]
                },
                {
                    category: 'individual',
                    label: 'Individual-Related Factors',
                    reasons: [
                        { value: 'b1', label: 'b.1 Illness' },
                        { value: 'b2', label: 'b.2 Disease' },
                        { value: 'b4', label: 'b.4 Disability' }
                    ]
                },
                {
                    category: 'school',
                    label: 'School-Related Factors',
                    reasons: [
                        { value: 'c1', label: 'c.1 Teacher Factor' },
                        { value: 'c2', label: 'c.2 Physical condition of classroom' },
                        { value: 'c3', label: 'c.3 Peer Factor' }
                    ]
                },
                {
                    category: 'geographical',
                    label: 'Geographical/Environmental',
                    reasons: [
                        { value: 'd1', label: 'd.1 Distance from home to school' },
                        { value: 'd2', label: 'd.2 Armed conflict (incl. Tribal wars & clan feuds)' },
                        { value: 'd3', label: 'd.3 Calamities/disaster' },
                        { value: 'd4', label: 'd.4 Work-Related' },
                        { value: 'd5', label: 'd.5 Transferred/work' }
                    ]
                }
            ]
        };
    }
};
