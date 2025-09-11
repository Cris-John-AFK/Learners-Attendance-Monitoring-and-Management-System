import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api'; // Adjust this to your Laravel backend URL

export default {
    /**
     * Get seating arrangement for a section (shared across all subjects)
     */
    async getSeatingArrangement(sectionId, teacherId, subjectId = null) {
        try {
            const params = {
                teacher_id: teacherId
                // Remove subject_id to make seating section-based, not subject-specific
            };

            const response = await axios.get(`${API_BASE_URL}/student-management/sections/${sectionId}/seating-arrangement`, {
                params
            });
            
            return response.data;
        } catch (error) {
            console.error('Error fetching seating arrangement:', error);
            throw error;
        }
    },

    /**
     * Save seating arrangement
     */
    async saveSeatingArrangement(sectionId, subjectId, teacherId, seatingLayout) {
        try {
            const response = await axios.post(`${API_BASE_URL}/student-management/seating-arrangement/save`, {
                section_id: sectionId,
                subject_id: subjectId,
                teacher_id: teacherId,
                seating_layout: seatingLayout
            });
            
            return response.data;
        } catch (error) {
            console.error('Error saving seating arrangement:', error);
            throw error;
        }
    },

    /**
     * Get students for a section
     */
    async getStudentsBySection(sectionId, teacherId) {
        try {
            const response = await axios.get(`${API_BASE_URL}/student-management/sections/${sectionId}/students`, {
                params: {
                    teacher_id: teacherId
                }
            });
            
            return response.data;
        } catch (error) {
            console.error('Error fetching students:', error);
            throw error;
        }
    }
};