import axios from 'axios';

export const SectionService = {
    async getSections() {
        const response = await axios.get('/api/sections');
        return response.data;
    },

    async createSection(sectionData) {
        const response = await axios.post('/api/sections', sectionData);
        return response.data;
    },

    async updateSection(id, sectionData) {
        const response = await axios.put(`/api/sections/${id}`, sectionData);
        return response.data;
    },

    async deleteSection(id) {
        const response = await axios.delete(`/api/sections/${id}`);
        return response.data;
    }
};
