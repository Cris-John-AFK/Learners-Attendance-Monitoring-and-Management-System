class CurriculumService {
    constructor() {
        this.baseUrl = import.meta.env.VITE_API_URL;
    }

    handleError(error) {
        console.error('API Error:', error);
        if (error.response && error.response.data && error.response.data.message) {
            throw new Error(error.response.data.message);
        }
        throw error;
    }

    /**
     * Get curriculum grade by curriculum and grade IDs
     * @param {number} curriculumId
     * @param {number} gradeId
     * @returns {Promise}
     */
    async getCurriculumGrade(curriculumId, gradeId) {
        try {
            const response = await axios.get(`${this.baseUrl}/curriculum-grade/${curriculumId}/${gradeId}`);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get sections by curriculum grade
     * @param {number} curriculumId
     * @param {number} gradeId
     * @returns {Promise}
     */
    async getSectionsByCurriculumGrade(curriculumId, gradeId) {
        try {
            // First get the curriculum_grade_id
            const curriculumGrade = await this.getCurriculumGrade(curriculumId, gradeId);
            console.log('Got curriculum grade:', curriculumGrade);
            // Then get the sections for that curriculum grade
            const response = await axios.get(`${this.baseUrl}/sections/curriculum-grade/${curriculumGrade.id}`);
            console.log('Sections API response:', response.data);
            return response.data;
        } catch (error) {
            console.error('Error in getSectionsByCurriculumGrade:', error);
            throw this.handleError(error);
        }
    }

    /**
     * Alias for getSectionsByCurriculumGrade for backward compatibility
     * @param {number} curriculumId
     * @param {number} gradeId
     * @returns {Promise}
     */
    async getSectionsByGrade(curriculumId, gradeId) {
        return this.getSectionsByCurriculumGrade(curriculumId, gradeId);
    }

    /**
     * Add a new section
     * @param {Object} sectionData
     * @returns {Promise}
     */
    async addSection(sectionData) {
        try {
            console.log('Sending section data:', sectionData);
            const response = await axios.post(`${this.baseUrl}/sections`, sectionData);
            return response.data;
        } catch (error) {
            console.error('Full error object:', error);
            console.error('Error response data:', error.response?.data);
            console.error('Error status:', error.response?.status);
            console.error('Error headers:', error.response?.headers);
            throw error;
        }
    }

    /**
     * Get subjects by section
     * @param {number} curriculumId
     * @param {number} gradeId
     * @param {number} sectionId
     * @returns {Promise}
     */
    async getSubjectsBySection(curriculumId, gradeId, sectionId) {
        try {
            const response = await axios.get(`${this.baseUrl}/curriculum/${curriculumId}/grade/${gradeId}/section/${sectionId}/subjects`);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Add a schedule to a subject in a section
     * @param {number} curriculumId
     * @param {number} gradeId
     * @param {number} sectionId
     * @param {number} subjectId
     * @param {Object} scheduleData - { day, start_time, end_time, teacher_id }
     * @returns {Promise}
     */
    async addScheduleToSubject(curriculumId, gradeId, sectionId, subjectId, scheduleData) {
        try {
            const response = await axios.post(`${this.baseUrl}/curriculum/${curriculumId}/grade/${gradeId}/section/${sectionId}/subject/${subjectId}/schedule`, scheduleData);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get schedule for a subject in a section
     * @param {number} sectionId
     * @param {number} subjectId
     * @returns {Promise}
     */
    async getSubjectSchedule(sectionId, subjectId) {
        try {
            const response = await axios.get(`${this.baseUrl}/section/${sectionId}/subject/${subjectId}/schedule`);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Get all teachers
     * @returns {Promise}
     */
    async getTeachers() {
        try {
            const response = await axios.get(`${this.baseUrl}/teachers`);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Assign homeroom teacher to section
     * @param {number} sectionId
     * @param {number} teacherId
     * @returns {Promise}
     */
    async assignHomeroomTeacher(sectionId, teacherId) {
        try {
            const response = await axios.patch(`${this.baseUrl}/sections/${sectionId}`, {
                homeroom_teacher_id: teacherId
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Remove homeroom teacher from section
     * @param {number} sectionId
     * @returns {Promise}
     */
    async removeHomeroomTeacher(sectionId) {
        try {
            const response = await axios.patch(`${this.baseUrl}/sections/${sectionId}`, {
                homeroom_teacher_id: null
            });
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }
}

export default new CurriculumService();
