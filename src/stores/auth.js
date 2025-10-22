import { defineStore } from 'pinia'
import TeacherAuthService from '@/services/TeacherAuthService'

/**
 * Authentication Store
 * 
 * IMPORTANT: This store WRAPS your existing TeacherAuthService
 * - All existing code keeps working
 * - This provides reactive state management on top
 * - Migrate components gradually at your own pace
 */
export const useAuthStore = defineStore('auth', {
    state: () => ({
        // Teacher data
        teacher: null,
        user: null,
        assignments: [],
        
        // Authentication state
        token: null,
        isAuthenticated: false,
        
        // UI state
        loginTime: null
    }),

    getters: {
        /**
         * Get current teacher info
         */
        currentTeacher: (state) => state.teacher,

        /**
         * Get current user info
         */
        currentUser: (state) => state.user,

        /**
         * Get unique subjects for teacher
         * Replicates TeacherAuthService.getUniqueSubjects() logic
         */
        uniqueSubjects: (state) => {
            if (!state.assignments || state.assignments.length === 0) {
                return []
            }

            const subjectSectionMap = new Map()
            const homeroomSeen = new Set()

            if (state.teacher?.homeroom_section) {
                homeroomSeen.add('homeroom')
                homeroomSeen.add(null)
            }

            state.assignments.forEach(assignment => {
                const subjectId = assignment.subject_id
                
                // Skip homeroom assignments
                if (!subjectId || homeroomSeen.has(subjectId)) {
                    return
                }
                
                const subjectName = assignment.subject_name || 
                                  (assignment.subject && assignment.subject.name) || 
                                  'Unknown Subject'
                
                let gradeName = assignment.grade_name || 
                              (assignment.section?.curriculum_grade?.grade?.name) ||
                              (assignment.section?.grade?.name) ||
                              'Unknown'
                
                if (gradeName.startsWith('Grade ')) {
                    gradeName = gradeName.replace('Grade ', '')
                }
                
                const sectionName = assignment.section_name || 
                                  (assignment.section && assignment.section.name) || 
                                  'Unknown Section'
                const sectionId = assignment.section_id || 
                                (assignment.section && assignment.section.id)
                
                const key = `${subjectId}_${sectionId}`
                
                if (!subjectSectionMap.has(key)) {
                    subjectSectionMap.set(key, {
                        id: subjectId,
                        name: subjectName,
                        sectionId: sectionId,
                        sectionName: sectionName,
                        grade: gradeName,
                        originalSubject: {
                            id: subjectId,
                            name: subjectName,
                            sectionId: sectionId
                        }
                    })
                }
            })

            return Array.from(subjectSectionMap.values())
        },

        /**
         * Check if teacher has homeroom section
         */
        hasHomeroom: (state) => !!state.teacher?.homeroom_section,

        /**
         * Get homeroom section
         */
        homeroomSection: (state) => state.teacher?.homeroom_section || null
    },

    actions: {
        /**
         * Login action - WRAPS existing TeacherAuthService
         * Your existing service keeps working!
         */
        async login(username, password) {
            try {
                // Call your EXISTING service - NO CHANGES to service needed!
                const result = await TeacherAuthService.login(username, password)
                
                if (result.success) {
                    // Store in Pinia state (reactive)
                    this.setAuthData(result.data)
                }
                
                return result
            } catch (error) {
                console.error('Store login error:', error)
                return {
                    success: false,
                    message: error.message || 'Login failed'
                }
            }
        },

        /**
         * Logout action
         */
        async logout() {
            try {
                // Call existing service
                await TeacherAuthService.logout()
                
                // Clear store state
                this.$reset()
            } catch (error) {
                console.error('Store logout error:', error)
            }
        },

        /**
         * Set authentication data
         */
        setAuthData(data) {
            this.teacher = data.teacher
            this.user = data.user
            this.assignments = data.assignments || []
            this.token = localStorage.getItem('teacher_token') || null
            this.isAuthenticated = true
            this.loginTime = new Date().toISOString()
        },

        /**
         * Initialize from existing localStorage
         * This allows gradual migration - old and new code work together
         */
        initializeFromLocalStorage() {
            const teacherData = TeacherAuthService.getTeacherData()
            const token = TeacherAuthService.getToken()
            
            if (teacherData && token) {
                this.teacher = teacherData.teacher
                this.user = teacherData.user
                this.assignments = teacherData.assignments || []
                this.token = token
                this.isAuthenticated = true
                this.loginTime = teacherData.loginTime
            }
        },

        /**
         * Check authentication status
         */
        checkAuth() {
            // Use existing service for compatibility
            return TeacherAuthService.isAuthenticated()
        }
    },

    // Persist state to localStorage automatically
    // This replaces manual localStorage.setItem calls
    persist: {
        key: 'auth-store',
        storage: localStorage,
        paths: ['teacher', 'user', 'assignments', 'token', 'isAuthenticated', 'loginTime']
    }
})
