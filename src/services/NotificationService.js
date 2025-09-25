class NotificationService {
    constructor() {
        this.notifications = [];
        this.listeners = [];
        this.currentTeacherId = null;
        this.teacherAssignments = null;
        this.loadNotifications();
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
            this.loadNotifications();
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
     * Add a new notification
     */
    addNotification(notification) {
        console.log('Adding notification:', notification);
        
        const newNotification = {
            id: Date.now() + Math.random(),
            timestamp: new Date().toISOString(),
            read: false,
            ...notification
        };

        console.log('New notification created:', newNotification);
        this.notifications.unshift(newNotification);
        console.log('Total notifications after add:', this.notifications.length);
        
        this.saveNotifications();
        console.log('Notifications saved to localStorage');
        
        this.notifyListeners();
        console.log('Listeners notified');
        
        return newNotification;
    }

    /**
     * Add session completion notification
     */
    addSessionCompletionNotification(sessionData) {
        console.log('Creating session completion notification with data:', sessionData);
        console.log('Session data keys:', Object.keys(sessionData));
        console.log('Current teacher ID:', this.currentTeacherId);
        console.log('Teacher assignments:', this.teacherAssignments);
        
        // Ensure we have teacher ID - use current teacher if not provided in session data
        const teacherId = sessionData.teacher_id || this.currentTeacherId;
        
        const notification = {
            type: 'session_completed',
            title: 'Attendance Session Completed',
            message: `${sessionData.subject_name || 'Homeroom'} - ${sessionData.statistics?.present || sessionData.present_count || 0} present, ${sessionData.statistics?.absent || sessionData.absent_count || 0} absent`,
            sessionId: sessionData.session_id || sessionData.id, // Include session ID for database lookup
            subject: sessionData.subject_name,
            section: sessionData.section_name,
            method: sessionData.method || 'Manual Entry',
            metadata: {
                sessionId: sessionData.session_id || sessionData.id,
                teacherId: teacherId,
                sectionId: sessionData.section_id,
                subjectId: sessionData.subject_id
            },
            data: sessionData
        };

        console.log('Notification object created:', notification);
        console.log('Notification metadata:', notification.metadata);
        console.log('Using teacher ID:', teacherId);
        const result = this.addNotification(notification);
        console.log('addNotification returned:', result);
        
        return result;
    }

    /**
     * Mark notification as read
     */
    markAsRead(notificationId) {
        const notification = this.notifications.find(n => n.id === notificationId);
        if (notification) {
            notification.read = true;
            this.saveNotifications();
            this.notifyListeners();
        }
    }

    /**
     * Mark all notifications as read
     */
    markAllAsRead() {
        this.notifications.forEach(n => n.read = true);
        this.saveNotifications();
        this.notifyListeners();
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
            
            console.log('Filtering notification:', {
                type: notification.type,
                metadata: metadata,
                currentTeacherId: this.currentTeacherId,
                teacherAssignments: this.teacherAssignments
            });
            
            // If notification has teacher ID, check if it matches
            if (metadata.teacherId && metadata.teacherId !== this.currentTeacherId) {
                console.log('Notification filtered out: teacher ID mismatch');
                return false;
            }

            // If notification has subject/section info, check if teacher is assigned
            if (metadata.subjectId && metadata.sectionId) {
                const isAssigned = this.teacherAssignments.some(assignment => 
                    assignment.subject_id === metadata.subjectId && 
                    assignment.section_id === metadata.sectionId
                );
                console.log('Assignment check result:', isAssigned);
                return isAssigned;
            }

            // If no specific assignment info, show if it belongs to this teacher
            const belongsToTeacher = metadata.teacherId === this.currentTeacherId;
            console.log('Belongs to teacher check:', belongsToTeacher);
            return belongsToTeacher;
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
     * Load notifications from localStorage
     */
    loadNotifications() {
        try {
            const saved = localStorage.getItem('lamms_notifications');
            if (saved) {
                this.notifications = JSON.parse(saved);
                // Clean up old notifications on load
                this.clearOldNotifications();
            } else {
                // Create sample notifications for demo
                this.createSampleNotifications();
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
            this.notifications = [];
            this.createSampleNotifications();
        }
    }

    /**
     * Create sample notifications for demo
     */
    createSampleNotifications() {
        const sampleNotifications = [
            {
                id: Date.now() + 1,
                type: 'attendance_alert',
                title: 'Student Attendance Alert',
                message: 'Cris John has been absent for 3 consecutive days',
                timestamp: new Date().toISOString(),
                read: false,
                priority: 'high',
                studentName: 'Cris John',
                section: 'Grade 3-A',
                subject: 'Mathematics'
            },
            {
                id: Date.now() + 2,
                type: 'session_completed',
                title: 'Attendance Session Completed',
                message: 'Mathematics - 18 present, 2 absent',
                timestamp: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
                read: false,
                priority: 'medium',
                subject: 'Mathematics',
                section: 'Grade 3-A',
                method: 'QR Code Scan'
            },
            {
                id: Date.now() + 3,
                type: 'system_update',
                title: 'System Update',
                message: 'New attendance tracking features are now available',
                timestamp: new Date(Date.now() - 7200000).toISOString(), // 2 hours ago
                read: true,
                priority: 'low'
            }
        ];

        this.notifications = sampleNotifications;
        this.saveNotifications();
        console.log('Sample notifications created:', this.notifications.length);
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
}

// Create singleton instance
const notificationService = new NotificationService();

export default notificationService;
