/**
 * Attendance Indexing Service
 * Handles pre-loading, caching, and indexing of attendance data for all subjects
 * Provides instant subject switching with cached data
 */

import CacheService from '@/services/CacheService';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
import { AttendanceSummaryService } from '@/services/AttendanceSummaryService';

class AttendanceIndexingService {
    // Cache TTL for different data types
    static TTL = {
        SUBJECTS: 30 * 60 * 1000,  // 30 minutes for subject list
        STUDENTS: 10 * 60 * 1000,  // 10 minutes for student data
        TRENDS: 5 * 60 * 1000,     // 5 minutes for attendance trends
        SUMMARY: 5 * 60 * 1000     // 5 minutes for summary data
    };

    // Indexed data store for quick access
    static indexedData = new Map();

    /**
     * Pre-load and index all attendance data for a teacher
     * This creates a complete cache of all subjects' data
     */
    static async preloadAllData(teacherId, teacherSubjects) {
        console.log('📚 Pre-loading attendance data for all subjects...');
        const startTime = Date.now();
        
        try {
            // Create promises for parallel loading
            const loadPromises = [];
            
            // Add "All Students" view
            loadPromises.push(
                this.loadSubjectData(teacherId, null, null, 'all_students')
            );

            // Load data for each subject
            for (const subject of teacherSubjects) {
                if (subject && subject.id) {
                    loadPromises.push(
                        this.loadSubjectData(
                            teacherId, 
                            subject.sectionId, 
                            subject.id, 
                            'subject',
                            subject.name
                        )
                    );
                }
            }

            // Execute all loads in parallel
            const results = await Promise.allSettled(loadPromises);
            
            // Count successful loads
            const successCount = results.filter(r => r.status === 'fulfilled').length;
            const failCount = results.filter(r => r.status === 'rejected').length;
            
            const loadTime = Date.now() - startTime;
            console.log(`✅ Pre-loading complete: ${successCount} successful, ${failCount} failed in ${loadTime}ms`);
            
            return {
                success: successCount > 0,
                loaded: successCount,
                failed: failCount,
                loadTime
            };
        } catch (error) {
            console.error('❌ Error pre-loading attendance data:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Load and cache data for a specific subject
     */
    static async loadSubjectData(teacherId, sectionId, subjectId, viewType = 'subject', subjectName = '') {
        const indexKey = this.generateIndexKey(teacherId, sectionId, subjectId, viewType);
        
        try {
            // Check if already indexed
            if (this.indexedData.has(indexKey)) {
                const cached = this.indexedData.get(indexKey);
                if (cached && cached.timestamp > Date.now() - this.TTL.SUMMARY) {
                    console.log(`📦 Using indexed data for ${subjectName || 'All Students'}`);
                    return cached.data;
                }
            }

            console.log(`🌐 Loading fresh data for ${subjectName || 'All Students'}...`);

            // Determine correct viewType and parameters
            let actualViewType = viewType;
            let actualSubjectId = subjectId;
            
            // Handle "All Subjects" case
            if (subjectId === null && subjectName === 'All Subjects') {
                actualViewType = 'all_students';
                actualSubjectId = null;
            }

            // Parallel load all data types
            const [students, summary, weekTrends, monthTrends, dayTrends] = await Promise.all([
                // Load students - skip for "All Subjects" since we get them from summary
                (actualSubjectId && actualViewType === 'subject') ? 
                    TeacherAttendanceService.getStudentsForTeacherSubject(teacherId, sectionId, actualSubjectId) :
                    Promise.resolve({ success: true, students: [] }),
                
                // Load summary
                AttendanceSummaryService.getTeacherAttendanceSummary(teacherId, {
                    period: 'week',
                    viewType: actualViewType,
                    subjectId: actualSubjectId
                }),
                
                // Load trends for different periods
                AttendanceSummaryService.getAttendanceTrends(teacherId, 'week', actualViewType, actualSubjectId),
                AttendanceSummaryService.getAttendanceTrends(teacherId, 'month', actualViewType, actualSubjectId),
                AttendanceSummaryService.getAttendanceTrends(teacherId, 'day', actualViewType, actualSubjectId)
            ]);

            // Process and structure the data
            let studentData = students?.data || [];
            
            // For "All Subjects" view, get students from summary data
            if (actualViewType === 'all_students' && summary?.data?.students) {
                studentData = summary.data.students;
            }
            
            const processedData = {
                students: studentData,
                summary: summary?.data || {},
                trends: {
                    week: weekTrends?.data || {},
                    month: monthTrends?.data || {},
                    day: dayTrends?.data || {}
                },
                subjectId: actualSubjectId,
                subjectName,
                viewType: actualViewType,
                timestamp: Date.now()
            };

            // Store in index
            this.indexedData.set(indexKey, {
                data: processedData,
                timestamp: Date.now()
            });

            // Also cache individually for backward compatibility
            const cacheParams = { teacherId, sectionId, subjectId: actualSubjectId, viewType: actualViewType };
            CacheService.set(
                CacheService.generateKey('attendance_data', cacheParams),
                processedData,
                this.TTL.SUMMARY
            );

            console.log(`✅ Indexed data for ${subjectName || 'All Students'}:`, {
                studentsCount: studentData.length,
                summaryTotalStudents: processedData.summary?.total_students || 0,
                hasStudents: !!processedData.students,
                hasSummary: !!processedData.summary,
                hasTrends: !!processedData.trends
            });
            return processedData;

        } catch (error) {
            console.error(`❌ Error loading data for ${subjectName}:`, error);
            throw error;
        }
    }

    /**
     * Get indexed data for a subject (instant retrieval)
     */
    static getIndexedData(teacherId, sectionId, subjectId, viewType) {
        const indexKey = this.generateIndexKey(teacherId, sectionId, subjectId, viewType);
        
        if (this.indexedData.has(indexKey)) {
            const cached = this.indexedData.get(indexKey);
            if (cached && cached.timestamp > Date.now() - this.TTL.SUMMARY) {
                console.log('⚡ Retrieved indexed data instantly:', {
                    studentsCount: cached.data?.students?.length || 0,
                    summaryTotalStudents: cached.data?.summary?.total_students || 0,
                    hasStudents: !!cached.data?.students,
                    hasSummary: !!cached.data?.summary,
                    hasTrends: !!cached.data?.trends
                });
                return cached.data;
            }
        }
        
        // Fallback to regular cache
        const cacheParams = { teacherId, sectionId, subjectId, viewType };
        const cacheKey = CacheService.generateKey('attendance_data', cacheParams);
        const fallbackData = CacheService.get(cacheKey);
        
        if (fallbackData) {
            console.log('📦 Retrieved from cache fallback:', {
                studentsCount: fallbackData?.students?.length || 0,
                summaryTotalStudents: fallbackData?.summary?.total_students || 0,
                hasStudents: !!fallbackData?.students,
                hasSummary: !!fallbackData?.summary
            });
            return fallbackData;
        }
        
        console.log('❌ No indexed or cached data found for key:', indexKey);
        return null;
    }

    /**
     * Update specific subject data
     */
    static async refreshSubjectData(teacherId, sectionId, subjectId, viewType = 'subject', subjectName = '') {
        console.log(`🔄 Refreshing data for ${subjectName || 'All Students'}...`);
        return await this.loadSubjectData(teacherId, sectionId, subjectId, viewType, subjectName);
    }

    /**
     * Clear all indexed data
     */
    static clearAllIndexedData() {
        console.log('🧹 Clearing all indexed attendance data');
        this.indexedData.clear();
        
        // Also clear from CacheService
        const cacheKeys = CacheService.getKeys();
        cacheKeys.forEach(key => {
            if (key.includes('attendance_data')) {
                CacheService.delete(key);
            }
        });
    }

    /**
     * Clear indexed data for a specific subject
     */
    static clearSubjectData(teacherId, sectionId, subjectId, viewType = 'subject') {
        const indexKey = this.generateIndexKey(teacherId, sectionId, subjectId, viewType);
        this.indexedData.delete(indexKey);
        
        // Also clear from cache
        const cacheParams = { teacherId, sectionId, subjectId, viewType };
        const cacheKey = CacheService.generateKey('attendance_data', cacheParams);
        CacheService.delete(cacheKey);
    }

    /**
     * Generate a unique index key
     */
    static generateIndexKey(teacherId, sectionId, subjectId, viewType) {
        return `teacher_${teacherId}_section_${sectionId || 'all'}_subject_${subjectId || 'all'}_view_${viewType}`;
    }

    /**
     * Get all students for a teacher (across all sections/subjects)
     */
    static async getAllStudents(teacherId) {
        try {
            // Get all teacher assignments
            const assignments = await TeacherAttendanceService.getTeacherAssignments(teacherId);
            if (!assignments.success || !assignments.data) {
                return { success: false, data: [] };
            }

            // Collect unique students from all assignments
            const allStudents = new Map();
            
            for (const assignment of assignments.data) {
                const studentsResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(
                    teacherId,
                    assignment.section_id,
                    assignment.subject_id
                );
                
                if (studentsResponse.success && studentsResponse.data) {
                    studentsResponse.data.forEach(student => {
                        if (!allStudents.has(student.id)) {
                            allStudents.set(student.id, student);
                        }
                    });
                }
            }

            return {
                success: true,
                data: Array.from(allStudents.values())
            };
        } catch (error) {
            console.error('Error getting all students:', error);
            return { success: false, data: [] };
        }
    }

    /**
     * Get cache statistics
     */
    static getCacheStats() {
        const stats = {
            totalIndexed: this.indexedData.size,
            subjects: [],
            memoryUsage: 0
        };

        this.indexedData.forEach((value, key) => {
            const data = value.data;
            stats.subjects.push({
                key,
                subjectName: data.subjectName || 'All Students',
                studentsCount: data.students?.length || 0,
                timestamp: new Date(value.timestamp).toLocaleTimeString(),
                age: Math.floor((Date.now() - value.timestamp) / 1000) + 's'
            });

            // Rough memory estimate
            stats.memoryUsage += JSON.stringify(value).length;
        });

        stats.memoryUsageMB = (stats.memoryUsage / 1024 / 1024).toFixed(2);
        return stats;
    }

    /**
     * Prefetch next likely subjects based on usage pattern
     */
    static async prefetchLikelySubjects(teacherId, currentSubjectId, teacherSubjects) {
        // Implement smart prefetching based on patterns
        // For now, just prefetch adjacent subjects
        const currentIndex = teacherSubjects.findIndex(s => s.id === currentSubjectId);
        if (currentIndex === -1) return;

        const prefetchIndices = [
            currentIndex - 1,
            currentIndex + 1
        ].filter(i => i >= 0 && i < teacherSubjects.length);

        for (const index of prefetchIndices) {
            const subject = teacherSubjects[index];
            const indexKey = this.generateIndexKey(teacherId, subject.sectionId, subject.id, 'subject');
            
            // Only prefetch if not already indexed
            if (!this.indexedData.has(indexKey)) {
                // Note: Prefetching can be added later for further optimization
                setTimeout(() => {
                    this.loadSubjectData(
                        teacherId,
                        subject.sectionId,
                        subject.id,
                        'subject',
                        subject.name
                    ).catch(err => console.log('Prefetch error:', err));
                }, 1000); // Delay to not interfere with main loading
            }
        }
    }
}

export default AttendanceIndexingService;
