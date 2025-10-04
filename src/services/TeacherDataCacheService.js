/**
 * Teacher Data Cache Service
 * Provides intelligent caching for teacher-specific data to improve performance
 */

class TeacherDataCacheService {
    constructor() {
        this.cache = new Map();
        this.CACHE_DURATION = 10 * 60 * 1000; // 10 minutes for teacher data
        this.STUDENT_CACHE_DURATION = 30 * 60 * 1000; // 30 minutes for student data (changes less frequently)
        this.loadingPromises = new Map(); // Prevent duplicate API calls
    }

    /**
     * Generate cache key for teacher-specific data
     */
    getCacheKey(teacherId, dataType, additionalParams = '') {
        return `teacher_${teacherId}_${dataType}_${additionalParams}`;
    }

    /**
     * Get cached data if still valid
     */
    getCachedData(key) {
        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.timestamp < cached.duration) {
            console.log(`✅ Using cached data for ${key}`);
            return cached.data;
        }
        return null;
    }

    /**
     * Set data in cache with appropriate duration
     */
    setCachedData(key, data, isStudentData = false) {
        const duration = isStudentData ? this.STUDENT_CACHE_DURATION : this.CACHE_DURATION;
        this.cache.set(key, { 
            data, 
            timestamp: Date.now(),
            duration 
        });
        console.log(`💾 Cached data for ${key} (${Array.isArray(data) ? data.length : 'object'} items, ${duration/1000/60}min TTL)`);
    }

    /**
     * Clear specific cache or all cache for a teacher
     */
    clearCache(teacherId = null, dataType = null) {
        if (teacherId && dataType) {
            const key = this.getCacheKey(teacherId, dataType);
            this.cache.delete(key);
            console.log(`🗑️ Cleared cache for ${key}`);
        } else if (teacherId) {
            // Clear all cache for a specific teacher
            for (const [key] of this.cache) {
                if (key.startsWith(`teacher_${teacherId}_`)) {
                    this.cache.delete(key);
                }
            }
            console.log(`🗑️ Cleared all cache for teacher ${teacherId}`);
        } else {
            this.cache.clear();
            console.log('🗑️ Cleared all cache');
        }
    }

    /**
     * Prevent duplicate API calls with loading state management
     */
    async withLoadingState(key, asyncFunction) {
        // If already loading, wait for the existing promise
        if (this.loadingPromises.has(key)) {
            console.log(`⏳ Waiting for existing ${key} request...`);
            return await this.loadingPromises.get(key);
        }

        // Check cache first
        const cached = this.getCachedData(key);
        if (cached) {
            return cached;
        }

        // Create new loading promise
        const loadingPromise = asyncFunction();
        this.loadingPromises.set(key, loadingPromise);

        try {
            const result = await loadingPromise;
            const isStudentData = key.includes('students') || key.includes('attendance');
            this.setCachedData(key, result, isStudentData);
            return result;
        } finally {
            this.loadingPromises.delete(key);
        }
    }

    /**
     * Get teacher assignments with caching
     */
    async getTeacherAssignments(teacherId, api) {
        const key = this.getCacheKey(teacherId, 'assignments');
        
        return await this.withLoadingState(key, async () => {
            console.log(`🔄 Loading assignments for teacher ${teacherId}...`);
            const response = await api.get(`/api/teachers/${teacherId}/assignments`);
            return Array.isArray(response.data) ? response.data : (response.data.assignments || []);
        });
    }

    /**
     * Get sections with caching
     */
    async getSections(api) {
        const key = 'sections_all';
        
        return await this.withLoadingState(key, async () => {
            console.log('🔄 Loading sections...');
            const response = await api.get('/api/sections');
            return response.data.sections || response.data || [];
        });
    }

    /**
     * Get students for a section with caching
     */
    async getStudentsForSection(sectionId, api) {
        const key = this.getCacheKey('section', 'students', sectionId);
        
        return await this.withLoadingState(key, async () => {
            console.log(`🔄 Loading students for section ${sectionId}...`);
            const response = await api.get(`/api/sections/${sectionId}/students`);
            return response.data;
        });
    }

    /**
     * Get attendance records with caching (shorter cache duration)
     */
    async getAttendanceRecords(teacherId, sectionId, subjectId, startDate, endDate, api) {
        const dateRange = `${startDate}_${endDate}`;
        const key = this.getCacheKey(teacherId, 'attendance', `${sectionId}_${subjectId}_${dateRange}`);
        
        // Attendance data has shorter cache (5 minutes)
        const cached = this.cache.get(key);
        if (cached && Date.now() - cached.timestamp < 5 * 60 * 1000) {
            console.log(`✅ Using cached attendance data for ${key}`);
            return cached.data;
        }

        return await this.withLoadingState(key, async () => {
            console.log(`🔄 Loading attendance records for teacher ${teacherId}, section ${sectionId}, subject ${subjectId}...`);
            const response = await api.get('/api/teacher/attendance-records', {
                params: {
                    teacher_id: teacherId,
                    section_id: sectionId,
                    subject_id: subjectId,
                    start_date: startDate,
                    end_date: endDate
                }
            });
            
            // Cache with 5-minute duration for attendance data
            this.cache.set(key, { 
                data: response.data, 
                timestamp: Date.now(),
                duration: 5 * 60 * 1000 
            });
            
            return response.data;
        });
    }

    /**
     * Preload common data for faster navigation
     */
    async preloadTeacherData(teacherId, api) {
        console.log(`🚀 Preloading data for teacher ${teacherId}...`);
        
        try {
            // Load assignments and sections in parallel
            const [assignments, sections] = await Promise.all([
                this.getTeacherAssignments(teacherId, api),
                this.getSections(api)
            ]);

            // Find homeroom section
            const homeroomSection = sections.find(section => 
                section.homeroom_teacher_id === parseInt(teacherId)
            );

            // Try to preload students for homeroom section (optional - don't fail if endpoint doesn't exist)
            if (homeroomSection) {
                try {
                    await this.getStudentsForSection(homeroomSection.id, api);
                    console.log('✅ Students preloaded for section:', homeroomSection.name);
                } catch (studentError) {
                    console.warn('⚠️ Could not preload students (endpoint may not exist):', studentError.message);
                    // Don't throw error - students can be loaded later when needed
                }
            }

            console.log('✅ Teacher data preloaded successfully');
            return { assignments, sections, homeroomSection };
        } catch (error) {
            console.error('❌ Error preloading teacher data:', error);
            throw error;
        }
    }

    /**
     * Get cache statistics for debugging
     */
    getCacheStats() {
        const stats = {
            totalCached: this.cache.size,
            activeLoading: this.loadingPromises.size,
            cacheKeys: Array.from(this.cache.keys()),
            loadingKeys: Array.from(this.loadingPromises.keys())
        };
        console.log('📈 Teacher Cache Stats:', stats);
        return stats;
    }

    /**
     * Clear expired cache entries
     */
    cleanupExpiredCache() {
        const now = Date.now();
        let cleanedCount = 0;
        
        for (const [key, cached] of this.cache) {
            if (now - cached.timestamp >= cached.duration) {
                this.cache.delete(key);
                cleanedCount++;
            }
        }
        
        if (cleanedCount > 0) {
            console.log(`🧹 Cleaned up ${cleanedCount} expired cache entries`);
        }
    }
}

// Create singleton instance
const teacherDataCache = new TeacherDataCacheService();

// Cleanup expired cache every 5 minutes
setInterval(() => {
    teacherDataCache.cleanupExpiredCache();
}, 5 * 60 * 1000);

export default teacherDataCache;
