import { defineStore } from 'pinia';
import axios from 'axios';

const API_BASE_URL = (typeof import !== 'undefined' && import.meta?.env?.VITE_API_BASE_URL) ? import.meta.env.VITE_API_BASE_URL + '/api' : 'http://localhost:8000/api';

export const useNotificationStore = defineStore('notifications', {
    state: () => ({
        notifications: [],
        currentTeacherId: null,
        teacherAssignments: [],
        loading: false,
        lastFetch: null,
        autoRefreshInterval: null
    }),

    getters: {
        // Get unread notifications count
        unreadCount: (state) => {
            return state.notifications.filter(n => !n.read && !n.is_read).length;
        },

        // Get notifications sorted by date (newest first)
        sortedNotifications: (state) => {
            return [...state.notifications].sort((a, b) => {
                const dateA = new Date(a.created_at || a.timestamp);
                const dateB = new Date(b.created_at || b.timestamp);
                return dateB - dateA;
            });
        },

        // Get only unread notifications
        unreadNotifications: (state) => {
            return state.notifications.filter(n => !n.read && !n.is_read);
        },

        // Check if data is fresh (less than 30 seconds old)
        isFresh: (state) => {
            if (!state.lastFetch) return false;
            const now = Date.now();
            const age = now - state.lastFetch;
            return age < 30000; // 30 seconds
        }
    },

    actions: {
        /**
         * Set current teacher and load their data
         */
        async setCurrentTeacher(teacherId) {
            if (this.currentTeacherId === teacherId && this.isFresh) {
                console.log('📦 Teacher already set and data is fresh');
                return;
            }

            this.currentTeacherId = teacherId;

            // Load teacher assignments
            try {
                const response = await axios.get(`${API_BASE_URL}/teachers/${teacherId}/assignments`);
                this.teacherAssignments = response.data.assignments || [];
                console.log('✅ Loaded teacher assignments:', this.teacherAssignments.length);
            } catch (error) {
                console.error('❌ Error loading teacher assignments:', error);
            }

            // Load notifications
            await this.loadNotifications();
        },

        /**
         * Load notifications from database with caching
         */
        async loadNotifications() {
            // Skip if data is fresh
            if (this.isFresh && this.notifications.length > 0) {
                console.log('📦 Using cached notifications (fresh)');
                return;
            }

            if (!this.currentTeacherId) {
                console.warn('⚠️ No teacher ID set, cannot load notifications');
                return;
            }

            this.loading = true;

            try {
                const token = this.getAuthToken();
                if (!token) {
                    console.warn('⚠️ No auth token available');
                    this.loading = false;
                    return;
                }

                const response = await axios.get(`${API_BASE_URL}/notifications`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    },
                    params: {
                        teacher_id: this.currentTeacherId
                    }
                });

                // Filter notifications for current teacher
                const allNotifications = response.data.notifications || [];
                this.notifications = this.filterNotificationsForTeacher(allNotifications);
                this.lastFetch = Date.now();

                console.log(`✅ Loaded ${this.notifications.length} notifications from database`);
            } catch (error) {
                console.error('❌ Error loading notifications:', error);
            } finally {
                this.loading = false;
            }
        },

        /**
         * Filter notifications for current teacher
         */
        filterNotificationsForTeacher(notifications) {
            if (!this.currentTeacherId) return [];

            return notifications.filter(notification => {
                // Keep system notifications
                if (notification.type === 'system_update') {
                    return true;
                }

                // Check if notification belongs to this teacher
                const metadata = notification.metadata || {};
                const data = notification.data || {};

                return (
                    metadata.teacherId === this.currentTeacherId ||
                    data.teacher_id === this.currentTeacherId ||
                    notification.teacher_id === this.currentTeacherId
                );
            });
        },

        /**
         * Mark notification as read
         */
        async markAsRead(notificationId) {
            const notification = this.notifications.find(n => n.id === notificationId);
            if (!notification) return;

            // Update local state immediately
            notification.read = true;
            notification.is_read = true;

            // Update in database
            try {
                const token = this.getAuthToken();
                if (!token) return;

                await axios.post(
                    `${API_BASE_URL}/notifications/${notificationId}/mark-read`,
                    {},
                    {
                        headers: {
                            'Authorization': `Bearer ${token}`
                        }
                    }
                );

                console.log('✅ Marked notification as read:', notificationId);
            } catch (error) {
                console.error('❌ Error marking notification as read:', error);
            }
        },

        /**
         * Mark all notifications as read
         */
        async markAllAsRead() {
            // Update local state
            this.notifications.forEach(n => {
                n.read = true;
                n.is_read = true;
            });

            // Update in database
            try {
                const token = this.getAuthToken();
                if (!token) return;

                await axios.post(
                    `${API_BASE_URL}/notifications/mark-all-read`,
                    { teacher_id: this.currentTeacherId },
                    {
                        headers: {
                            'Authorization': `Bearer ${token}`
                        }
                    }
                );

                console.log('✅ Marked all notifications as read');
            } catch (error) {
                console.error('❌ Error marking all as read:', error);
            }
        },

        /**
         * Remove notification
         */
        async removeNotification(notificationId) {
            // Remove from local state
            const index = this.notifications.findIndex(n => n.id === notificationId);
            if (index > -1) {
                this.notifications.splice(index, 1);
            }

            // Remove from database
            try {
                const token = this.getAuthToken();
                if (!token) return;

                await axios.delete(`${API_BASE_URL}/notifications/${notificationId}`, {
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
                });

                console.log('✅ Removed notification:', notificationId);
            } catch (error) {
                console.error('❌ Error removing notification:', error);
            }
        },

        /**
         * Force refresh notifications (bypass cache)
         */
        async forceRefresh() {
            this.lastFetch = null;
            await this.loadNotifications();
        },

        /**
         * Start auto-refresh interval
         */
        startAutoRefresh() {
            // Clear existing interval
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
            }

            // Refresh every 30 seconds
            this.autoRefreshInterval = setInterval(() => {
                if (this.currentTeacherId) {
                    this.loadNotifications();
                }
            }, 30000);

            console.log('✅ Auto-refresh started (30s interval)');
        },

        /**
         * Stop auto-refresh
         */
        stopAutoRefresh() {
            if (this.autoRefreshInterval) {
                clearInterval(this.autoRefreshInterval);
                this.autoRefreshInterval = null;
                console.log('🛑 Auto-refresh stopped');
            }
        },

        /**
         * Get auth token from localStorage
         */
        getAuthToken() {
            try {
                const teacherData = JSON.parse(localStorage.getItem('teacher_data') || '{}');
                return teacherData.token || localStorage.getItem('teacher_token') || null;
            } catch (error) {
                console.error('Error getting auth token:', error);
                return null;
            }
        }
    }
});
