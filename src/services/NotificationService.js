class NotificationService {
    constructor() {
        this.notifications = [];
        this.listeners = [];
        this.currentTeacherId = null;
        this.teacherAssignments = null;
        this.baseURL = 'http://localhost:8000/api';
        this.refreshInterval = null;
        // Load notifications asynchronously
        this.loadNotifications().catch(console.error);
        // Start auto-refresh every 30 seconds (optimized for performance)
        this.startAutoRefresh();
    }

    /**
     * Set current teacher and load their assignments
     */
    async setCurrentTeacher(teacherId) {
        if (this.currentTeacherId !== teacherId) {
            this.currentTeacherId = teacherId;
            this.teacherAssignments = null;
            
            // Load teacher assignments to filter notifications
            try {
                const response = await fetch(`http://localhost:8000/api/teachers/${teacherId}/assignments`);
                if (response.ok) {
                    const data = await response.json();
                    this.teacherAssignments = data.assignments || [];
                    console.log('Loaded teacher assignments for notifications:', this.teacherAssignments);
                    
                    // Clean up old notifications that don't have proper teacher metadata
                    this.cleanupOldNotifications();
                }
            } catch (error) {
                console.error('Error loading teacher assignments for notifications:', error);
            }
            
            // Reload notifications for this teacher
            await this.loadNotifications();
            this.notifyListeners();
        }
    }

    /**
     * Clean up old notifications that don't have proper teacher metadata
     */
    cleanupOldNotifications() {
        console.log('Cleaning up old notifications without proper teacher metadata');
        const beforeCount = this.notifications.length;
        
        this.notifications = this.notifications.filter(notification => {
            // Keep system notifications
            if (notification.type === 'system_update') {
                return true;
            }
            
            // Keep notifications that have proper teacher metadata
            const metadata = notification.metadata || {};
            return metadata.teacherId && (metadata.subjectId || metadata.sectionId);
        });
        
        const afterCount = this.notifications.length;
        console.log(`Cleaned up ${beforeCount - afterCount} old notifications`);
        
        if (beforeCount !== afterCount) {
            this.saveNotifications();
        }
    }

    /**
     * Mark notification as read in database
     */
    async markAsRead(notificationId) {
        try {
            // Update local state first
            const notification = this.notifications.find(n => n.id === notificationId);
            if (notification) {
                notification.is_read = true;
                notification.read = true;
            }

            // Only try to update database for non-schedule notifications
            if (notification && notification.metadata?.source !== 'schedule_notification_service') {
                const token = this.getAuthToken();
                if (!token) {
                    console.warn('No auth token available for marking notification as read');
                    this.notifyListeners();
                    return;
                }

                const response = await fetch(`${this.baseURL}/notifications/${notificationId}/mark-read`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            } else {
                console.log('📝 Schedule notification marked as read locally only');
            }

            this.notifyListeners();
        } catch (error) {
            console.error('Failed to mark notification as read in database:', error);
            
            // Local state is already updated, just notify listeners
            this.notifyListeners();
        }
    }

    /**
     * Mark all notifications as read
     */
    async markAllAsRead() {
        try {
            const response = await fetch(`${this.baseURL}/notifications/mark-all-read`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: this.currentTeacherId
                })
            });

            if (response.ok) {
                // Update local notifications
                this.notifications.forEach(n => n.read = true);
                this.notifyListeners();
                console.log('All notifications marked as read in database');
            } else {
                console.error('Failed to mark all notifications as read in database');
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }

    /**
     * Remove notification
     */
    removeNotification(notificationId) {
        this.notifications = this.notifications.filter(n => n.id !== notificationId);
        this.saveNotifications();
        this.notifyListeners();
    }

    /**
     * Get all notifications filtered for current teacher
     */
    getNotifications() {
        return this.filterNotificationsForCurrentTeacher(this.notifications);
    }

    /**
     * Filter notifications based on current teacher's assignments
     */
    filterNotificationsForCurrentTeacher(notifications) {
        if (!this.currentTeacherId || !this.teacherAssignments) {
            console.log('No teacher ID or assignments for filtering:', {
                teacherId: this.currentTeacherId,
                assignments: this.teacherAssignments
            });
            return notifications;
        }

        return notifications.filter(notification => {
            // Always show system notifications
            if (notification.type === 'system_update') {
                return true;
            }

            // Check if notification belongs to this teacher
            const metadata = notification.metadata || {};
            const teacherIdMatch = metadata.teacherId === this.currentTeacherId;
            const userIdMatch = metadata.userId === this.currentTeacherId;
            const directTeacherIdMatch = notification.teacher_id === this.currentTeacherId;
            
            // Special handling for schedule notifications - bypass all other checks
            if (notification.type === 'schedule') {
                return directTeacherIdMatch || teacherIdMatch || userIdMatch;
            }
            
            // First priority: Direct teacher ID matches
            if (teacherIdMatch || userIdMatch || directTeacherIdMatch) {
                return true;
            }
            
            // If notification has explicit teacher ID mismatch, filter out
            if (metadata.teacherId && !teacherIdMatch && !userIdMatch) {
                return false;
            }

            // Secondary: If notification has subject/section info, check if teacher is assigned
            if (metadata.subjectId && metadata.sectionId) {
                return this.teacherAssignments.some(assignment => 
                    assignment.subject_id === metadata.subjectId && 
                    assignment.section_id === metadata.sectionId
                );
            }

            // Default: show notification if no specific filtering rules apply
            return true;
        });
    }

    /**
     * Get unread notifications count for current teacher
     */
    getUnreadCount() {
        const filteredNotifications = this.filterNotificationsForCurrentTeacher(this.notifications);
        return filteredNotifications.filter(n => !n.read).length;
    }

    /**
     * Clear old notifications (older than 7 days)
     */
    clearOldNotifications() {
        const sevenDaysAgo = new Date();
        sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

        this.notifications = this.notifications.filter(n => 
            new Date(n.timestamp) > sevenDaysAgo
        );
        
        this.saveNotifications();
        this.notifyListeners();
    }

    /**
     * Subscribe to notification changes
     */
    subscribe(callback) {
        this.listeners.push(callback);
        
        // Return unsubscribe function
        return () => {
            this.listeners = this.listeners.filter(listener => listener !== callback);
        };
    }

    /**
     * Notify all listeners of changes
     */
    notifyListeners() {
        const filteredNotifications = this.filterNotificationsForCurrentTeacher(this.notifications);
        console.log('Notifying listeners, filtered notifications for teacher:', filteredNotifications.length);
        this.listeners.forEach((callback, index) => {
            try {
                console.log(`Calling listener ${index}:`, callback);
                callback([...filteredNotifications]); // Pass filtered notifications
            } catch (error) {
                console.error('Error in notification listener:', error);
            }
        });
    }

    /**
     * Save notifications to localStorage
     */
    saveNotifications() {
        try {
            localStorage.setItem('lamms_notifications', JSON.stringify(this.notifications));
        } catch (error) {
            console.error('Error saving notifications:', error);
        }
    }

    /**
     * Get authentication token for API calls
     */
    getAuthToken() {
        // First try the teacher_token key used by TeacherAuthService
        let token = localStorage.getItem('teacher_token');
        
        if (!token) {
            // Fallback to sessionStorage
            token = sessionStorage.getItem('teacher_token');
        }
        
        if (!token) {
            // Fallback to teacher_data structure
            const teacherData = JSON.parse(localStorage.getItem('teacher_data') || '{}');
            token = teacherData.token || teacherData.access_token || '';
        }
        
        console.log('NotificationService.getAuthToken():', token ? 'Token found' : 'No token found');
        return token || '';
    }

    /**
     * Load notifications from database
     */
    async loadNotifications() {
        try {
            if (!this.currentTeacherId) {
                console.log('No teacher ID set, cannot load notifications');
                return;
            }

            const response = await fetch(`${this.baseURL}/notifications?user_id=${this.currentTeacherId}`, {
                headers: {
                    'Authorization': `Bearer ${this.getAuthToken()}`,
                    'Content-Type': 'application/json'
                }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data.notifications) {
                    // Transform database notifications to match frontend format
                    this.notifications = result.data.notifications.all.map(notification => ({
                        id: notification.id,
                        type: notification.type,
                        title: notification.title,
                        message: notification.message,
                        timestamp: notification.created_at,
                        read: notification.is_read,
                        priority: notification.priority,
                        data: notification.data,
                        metadata: {
                            ...notification.data,
                            teacherId: notification.user_id,  // Map user_id to teacherId for filtering
                            userId: notification.user_id
                        }
                    }));
                    
                    console.log('Loaded notifications from database:', this.notifications.length);
                } else {
                    console.log('No notifications found in database response');
                    this.notifications = [];
                }
            } else {
                console.error('Failed to load notifications from database');
                // Fallback to localStorage for backward compatibility
                await this.loadFromLocalStorage();
            }
            
            this.notifyListeners();
        } catch (error) {
            console.error('Error loading notifications from database:', error);
            // Fallback to localStorage for backward compatibility
            await this.loadFromLocalStorage();
        }
    }

    /**
     * Fallback method to load from localStorage
     */
    async loadFromLocalStorage() {
        try {
            const saved = localStorage.getItem('lamms_notifications');
            if (saved) {
                this.notifications = JSON.parse(saved);
                this.clearOldNotifications();
            } else {
                this.notifications = [];
            }
        } catch (error) {
            console.error('Error loading from localStorage:', error);
            this.notifications = [];
        }
    }

    /**
     * Add only system notifications without overwriting existing ones
     */
    async addSystemNotifications() {
        try {
            // Add a recent system update notification
            const systemNotification = {
                id: Date.now() + Math.random(),
                type: 'system_update',
                title: 'System Update',
                message: 'Attendance tracking dashboard has been updated with real-time data',
                timestamp: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
                read: false,
                priority: 'low'
            };

            this.notifications.push(systemNotification);
            this.saveNotifications();
            console.log('System notification added without overwriting existing notifications');
        } catch (error) {
            console.error('Error adding system notifications:', error);
        }
    }

    /**
     * Create dynamic notifications based on real data
     */
    async createSampleNotifications() {
        try {
            const dynamicNotifications = [];
            
            // Generate attendance alerts based on real student data
            if (this.currentTeacherId) {
                const response = await fetch(`http://localhost:8000/api/attendance/summary?teacher_id=${this.currentTeacherId}&period=week&view_type=subject&subject_id=2`);
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.data.students) {
                        // Create notifications for students with attendance issues
                        data.data.students.forEach(student => {
                            if (student.total_absences >= 3) {
                                dynamicNotifications.push({
                                    id: Date.now() + Math.random(),
                                    type: 'attendance_alert',
                                    title: 'Student Attendance Alert',
                                    message: `${student.name} has ${student.total_absences} absences (${student.severity} level)`,
                                    timestamp: new Date().toISOString(),
                                    read: false,
                                    priority: student.severity === 'critical' ? 'high' : 'medium',
                                    studentName: student.name,
                                    section: student.section_name || 'Kinder One',
                                    subject: 'English',
                                    teacherId: this.currentTeacherId
                                });
                            }
                        });
                    }
                }
            }
            
            // Add a recent system update notification
            dynamicNotifications.push({
                id: Date.now() + Math.random(),
                type: 'system_update',
                title: 'System Update',
                message: 'Attendance tracking dashboard has been updated with real-time data',
                timestamp: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
                read: false,
                priority: 'low'
            });

            this.notifications = dynamicNotifications;
            this.saveNotifications();
            console.log('Dynamic notifications created:', this.notifications.length);
        } catch (error) {
            console.error('Error creating dynamic notifications:', error);
            // Fallback to empty array if API fails
            this.notifications = [];
        }
    }

    /**
     * Clear all notifications
     */
    clearAll() {
        this.notifications = [];
        this.saveNotifications();
        this.notifyListeners();
    }

    /**
     * Get notifications by type for current teacher
     */
    getNotificationsByType(type) {
        const filteredNotifications = this.filterNotificationsForCurrentTeacher(this.notifications);
        return filteredNotifications.filter(n => n.type === type);
    }

    /**
     * Check if there are unread notifications
     */
    hasUnreadNotifications() {
        return this.getUnreadCount() > 0;
    }

    /**
     * Add a new notification programmatically
     */
    addNotification(notification) {
        // Check if we should stack this notification with an existing one
        const existingNotificationIndex = this.notifications.findIndex(n => 
            n.type === notification.type && 
            n.title === notification.title &&
            n.metadata?.subjectId === notification.metadata?.subjectId &&
            n.metadata?.schedule_type === notification.metadata?.schedule_type
        );
        
        if (existingNotificationIndex !== -1) {
            // Update existing notification instead of creating a new one
            const existingNotification = this.notifications[existingNotificationIndex];
            
            // Update the count if it exists, otherwise set to 2
            existingNotification.count = (existingNotification.count || 1) + 1;
            existingNotification.timestamp = notification.timestamp;
            existingNotification.created_at = notification.created_at;
            existingNotification.is_read = false; // Mark as unread since it's updated
            
            // Update the message to show count
            if (existingNotification.count > 1) {
                const baseMessage = existingNotification.message.replace(/ \(\d+x\)$/, '');
                existingNotification.message = `${baseMessage} (${existingNotification.count}x)`;
            }
            
            console.log('📚 Stacked notification:', notification.title, `(${existingNotification.count}x)`);
        } else {
            // Add new notification
            notification.count = 1;
            this.notifications.unshift(notification);
            
            console.log('📬 Added new notification:', notification.title);
        }
        
        // Keep only last 50 notifications
        if (this.notifications.length > 50) {
            this.notifications = this.notifications.slice(0, 50);
        }
        
        console.log('📊 Total notifications after add:', this.notifications.length);
        console.log('🔍 Notification details:', {
            id: notification.id,
            type: notification.type,
            teacher_id: notification.teacher_id,
            metadata: notification.metadata
        });
        
        // Notify listeners immediately
        this.notifyListeners();
    }

    /**
     * Start auto-refresh for notifications
     * Optimized: Only refreshes if teacher is set and page is visible
     */
    startAutoRefresh() {
        // Clear any existing interval
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        // Refresh every 30 seconds (balanced for performance)
        this.refreshInterval = setInterval(() => {
            // Only refresh if teacher is set and page is visible
            if (this.currentTeacherId && !document.hidden) {
                this.loadNotifications().catch(console.error);
            }
        }, 30000); // 30 seconds
    }

    /**
     * Stop auto-refresh (cleanup)
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
}

// Create singleton instance
const notificationService = new NotificationService();

export default notificationService;
