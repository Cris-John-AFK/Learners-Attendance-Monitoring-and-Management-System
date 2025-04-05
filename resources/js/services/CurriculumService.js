import axios from 'axios';

// Initialize cache for section subjects
const sectionSubjectsCache = {};

export const CurriculumService = {
    /**
     * Get subjects for a section
     * @param {Number|String} sectionId The ID of the section
     * @returns {Promise<Array>} A promise that resolves to an array of subjects
     */
    async getSubjectsBySection(sectionId) {
        try {
            // Skip invalid section IDs
            if (!sectionId || sectionId.toString().startsWith('fallback')) {
                console.log('Invalid sectionId:', sectionId);
                return [];
            }

            console.log('Getting ONLY user-added subjects for section:', sectionId);

            // Clear any existing cached data
            try {
                const cacheKey = `section_subjects_${sectionId}`;
                localStorage.removeItem(cacheKey);
                localStorage.removeItem(`${cacheKey}_timestamp`);
            } catch (e) {
                console.warn('Could not clear localStorage cache:', e);
            }

            // Use the direct-subjects endpoint which ONLY returns manually added subjects
            const response = await axios.get(`/api/sections/${sectionId}/direct-subjects`, {
                params: {
                    user_added_only: true,
                    force: true,
                    no_fallback: true,
                    timestamp: Date.now() // Cache busting
                },
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    Pragma: 'no-cache',
                    Expires: '0'
                }
            });

            console.log('Got subjects response:', response.status, 'Data length:', response.data?.length || 0);

            if (!response.data || !Array.isArray(response.data)) {
                console.log('No subjects or invalid response for section:', sectionId);
                return [];
            }

            if (response.data.length === 0) {
                console.log('No user-added subjects found for this section');
                return [];
            }

            // Process the subjects
            const subjects = response.data.map((subject) => {
                // Make sure all subjects have valid IDs
                return {
                    id: subject.id || `fallback-${Date.now()}-${Math.random()}`,
                    name: subject.name || '',
                    description: subject.description || '',
                    ...subject
                };
            });

            console.log('Processed subjects:', subjects.map((s) => s.name).join(', '));

            // Cache in memory
            this.sectionSubjectsCache = this.sectionSubjectsCache || {};
            this.sectionSubjectsCache[sectionId] = subjects;

            // Try to cache in localStorage
            try {
                localStorage.setItem(`section_subjects_${sectionId}`, JSON.stringify(subjects));
            } catch (e) {
                console.warn('Error caching section subjects in localStorage:', e);
            }

            return subjects;
        } catch (error) {
            console.error('Error fetching subjects for section:', error);
            return [];
        }
    }
};
