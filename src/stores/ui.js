import { defineStore } from 'pinia'

/**
 * UI Store
 * 
 * Manages global UI state:
 * - Loading indicators
 * - Notifications
 * - Modals
 * - Toast messages
 * 
 * REPLACES: useGlobalLoader composable (but that still works too!)
 */
export const useUIStore = defineStore('ui', {
    state: () => ({
        // Global loading state
        globalLoading: false,
        loadingText: 'Loading...',
        loadingSize: 'medium',
        
        // Notifications
        notifications: [],
        unreadCount: 0,
        
        // Modals
        activeModals: [],
        
        // Toast queue
        toasts: [],
        
        // Sidebar state
        sidebarVisible: true,
        sidebarCollapsed: false,
        
        // Theme
        darkMode: false
    }),

    getters: {
        /**
         * Check if loading
         */
        isLoading: (state) => state.globalLoading,

        /**
         * Get unread notifications
         */
        unreadNotifications: (state) => {
            return state.notifications.filter(n => !n.read)
        },

        /**
         * Check if any modal is open
         */
        hasOpenModal: (state) => state.activeModals.length > 0,

        /**
         * Get current theme
         */
        currentTheme: (state) => state.darkMode ? 'dark' : 'light'
    },

    actions: {
        /**
         * Show global loader
         * REPLACES: useGlobalLoader().showGlobalLoader()
         */
        showLoader(text = 'Loading...', size = 'medium') {
            this.globalLoading = true
            this.loadingText = text
            this.loadingSize = size
            console.log('🔄 Loading:', text)
        },

        /**
         * Hide global loader
         * REPLACES: useGlobalLoader().hideGlobalLoader()
         */
        hideLoader() {
            this.globalLoading = false
            console.log('✅ Loading complete')
        },

        /**
         * Update loading text
         */
        setLoadingText(text) {
            this.loadingText = text
        },

        /**
         * Add notification
         */
        addNotification(notification) {
            this.notifications.unshift({
                id: Date.now(),
                read: false,
                timestamp: new Date().toISOString(),
                ...notification
            })
            this.unreadCount++
        },

        /**
         * Mark notification as read
         */
        markAsRead(notificationId) {
            const notification = this.notifications.find(n => n.id === notificationId)
            if (notification && !notification.read) {
                notification.read = true
                this.unreadCount = Math.max(0, this.unreadCount - 1)
            }
        },

        /**
         * Mark all notifications as read
         */
        markAllAsRead() {
            this.notifications.forEach(n => n.read = true)
            this.unreadCount = 0
        },

        /**
         * Clear all notifications
         */
        clearNotifications() {
            this.notifications = []
            this.unreadCount = 0
        },

        /**
         * Open modal
         */
        openModal(modalId) {
            if (!this.activeModals.includes(modalId)) {
                this.activeModals.push(modalId)
            }
        },

        /**
         * Close modal
         */
        closeModal(modalId) {
            const index = this.activeModals.indexOf(modalId)
            if (index > -1) {
                this.activeModals.splice(index, 1)
            }
        },

        /**
         * Close all modals
         */
        closeAllModals() {
            this.activeModals = []
        },

        /**
         * Toggle sidebar
         */
        toggleSidebar() {
            this.sidebarVisible = !this.sidebarVisible
        },

        /**
         * Collapse/expand sidebar
         */
        toggleSidebarCollapse() {
            this.sidebarCollapsed = !this.sidebarCollapsed
        },

        /**
         * Toggle dark mode
         */
        toggleDarkMode() {
            this.darkMode = !this.darkMode
            // Apply to document
            if (this.darkMode) {
                document.documentElement.classList.add('app-dark')
            } else {
                document.documentElement.classList.remove('app-dark')
            }
        },

        /**
         * Add toast message
         */
        addToast(toast) {
            this.toasts.push({
                id: Date.now(),
                ...toast
            })
        },

        /**
         * Remove toast
         */
        removeToast(toastId) {
            const index = this.toasts.findIndex(t => t.id === toastId)
            if (index > -1) {
                this.toasts.splice(index, 1)
            }
        }
    },

    // Persist UI preferences
    persist: {
        key: 'ui-store',
        storage: localStorage,
        paths: ['sidebarCollapsed', 'darkMode']
    }
})
