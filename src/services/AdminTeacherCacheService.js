/**
 * Performance optimization service for Admin Teacher page
 * Provides caching and batch loading capabilities
 */

class AdminTeacherCacheService {
    constructor() {
        this.cache = new Map();
        this.CACHE_DURATION = 5 * 60 * 1000; // 5 minutes
        this.loadingStates = new Map();
    }

    /**
     * Get cached data if still valid
     */
    getCachedData(key) {
        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.timestamp < this.CACHE_DURATION) {
            console.log(`✅ Using cached data for ${key}`);
            return cached.data;
        }
        return null;
    }

    /**
     * Set data in cache
     */
    setCachedData(key, data) {
        this.cache.set(key, { data, timestamp: Date.now() });
        console.log(`💾 Cached data for ${key} (${Array.isArray(data) ? data.length : 'object'} items)`);
    }

    /**
     * Clear specific cache or all cache
     */
    clearCache(key = null) {
        if (key) {
            this.cache.delete(key);
            console.log(`🗑️ Cleared cache for ${key}`);
        } else {
            this.cache.clear();
            console.log('🗑️ Cleared all cache');
        }
    }

    /**
     * Prevent duplicate API calls by tracking loading states
     */
    async withLoadingState(key, asyncFunction) {
        // If already loading, wait for the existing promise
        if (this.loadingStates.has(key)) {
            console.log(`⏳ Waiting for existing ${key} request...`);
            return await this.loadingStates.get(key);
        }

        // Check cache first
        const cached = this.getCachedData(key);
        if (cached) {
            return cached;
        }

        // Create new loading promise
        const loadingPromise = asyncFunction();
        this.loadingStates.set(key, loadingPromise);

        try {
            const result = await loadingPromise;
            this.setCachedData(key, result);
            return result;
        } finally {
            this.loadingStates.delete(key);
        }
    }

    /**
     * Batch load all admin teacher data efficiently
     */
    async batchLoadAdminData(api, API_BASE_URL) {
        console.log('🚀 Starting batch load of admin teacher data...');
        const startTime = Date.now();

        try {
            // Load all data in parallel with caching
            const [teachers, sections, subjects, grades] = await Promise.all([
                this.withLoadingState('teachers', async () => {
                    const response = await api.get(`${API_BASE_URL}/teachers`);
                    return response.data;
                }),
                this.withLoadingState('sections', async () => {
                    const response = await api.get(`${API_BASE_URL}/sections`);
                    return response.data;
                }),
                this.withLoadingState('subjects', async () => {
                    const response = await api.get(`${API_BASE_URL}/subjects`);
                    return response.data;
                }),
                this.withLoadingState('grades', async () => {
                    const response = await api.get(`${API_BASE_URL}/grades`);
                    return response.data;
                })
            ]);

            const loadTime = Date.now() - startTime;
            console.log(`✅ Batch load completed in ${loadTime}ms`);
            console.log(`📊 Loaded: ${teachers?.length || 0} teachers, ${sections?.length || 0} sections, ${subjects?.length || 0} subjects, ${grades?.length || 0} grades`);

            return { teachers, sections, subjects, grades };
        } catch (error) {
            console.error('❌ Batch load failed:', error);
            throw error;
        }
    }

    /**
     * Get cache statistics for debugging
     */
    getCacheStats() {
        const stats = {
            totalCached: this.cache.size,
            activeLoading: this.loadingStates.size,
            cacheKeys: Array.from(this.cache.keys()),
            loadingKeys: Array.from(this.loadingStates.keys())
        };
        console.log('📈 Cache Stats:', stats);
        return stats;
    }

    /**
     * Preload teacher assignments for faster dialog opening
     */
    async preloadTeacherAssignments(api, teacherIds) {
        console.log(`🔄 Preloading assignments for ${teacherIds.length} teachers...`);

        const promises = teacherIds.map((teacherId) =>
            this.withLoadingState(`teacher_assignments_${teacherId}`, async () => {
                try {
                    const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
                    const response = await fetch(`${apiUrl}/api/teachers/${teacherId}/assignments`);
                    return response.ok ? await response.json() : [];
                } catch (error) {
                    console.warn(`Failed to preload assignments for teacher ${teacherId}:`, error);
                    return [];
                }
            })
        );

        await Promise.allSettled(promises);
        console.log('✅ Teacher assignments preloaded');
    }
}

// Create singleton instance
const adminTeacherCache = new AdminTeacherCacheService();

export default adminTeacherCache;
