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
            // Then get the sections for that curriculum grade
            const response = await axios.get(`${this.baseUrl}/sections/curriculum-grade/${curriculumGrade.id}`);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }

    /**
     * Add a new section
     * @param {Object} sectionData
     * @returns {Promise}
     */
    async addSection(sectionData) {
        try {
            const response = await axios.post(`${this.baseUrl}/sections`, sectionData);
            return response.data;
        } catch (error) {
            throw this.handleError(error);
        }
    }
}

export default new CurriculumService();
