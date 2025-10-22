/**
 * Pinia Stores Index
 * 
 * Central export for all stores
 * Makes imports cleaner: import { useAuthStore } from '@/stores'
 */

export { useAuthStore } from './auth'
export { useAttendanceStore } from './attendance'
export { useUIStore } from './ui'

/**
 * USAGE EXAMPLES:
 * 
 * 1. Authentication:
 *    import { useAuthStore } from '@/stores'
 *    const authStore = useAuthStore()
 *    await authStore.login(username, password)
 * 
 * 2. Attendance (solves performance issues):
 *    import { useAttendanceStore } from '@/stores'
 *    const attendanceStore = useAttendanceStore()
 *    await attendanceStore.loadStudents(sectionId, subjectId)
 *    // No duplicate API calls! Cached automatically!
 * 
 * 3. UI State:
 *    import { useUIStore } from '@/stores'
 *    const uiStore = useUIStore()
 *    uiStore.showLoader('Loading students...')
 *    // ... do work
 *    uiStore.hideLoader()
 * 
 * BACKWARD COMPATIBILITY:
 * Your existing services still work! These stores WRAP them.
 * Migrate components gradually at your own pace.
 */
