class NotificationService {
    constructor() {
        this.notifications = [];
        this.listeners = [];
        this.loadNotifications();
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
        
        const notification = {
            type: 'session_completed',
            title: 'Attendance Session Completed',
            message: `${sessionData.subject_name || 'Homeroom'} - ${sessionData.statistics?.present || sessionData.present_count || 0} present, ${sessionData.statistics?.absent || sessionData.absent_count || 0} absent`,
            data: sessionData
        };

        console.log('Notification object created:', notification);
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
     * Get all notifications
     */
    getNotifications() {
        return this.notifications;
    }

    /**
     * Get unread notifications count
     */
    getUnreadCount() {
        return this.notifications.filter(n => !n.read).length;
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
        console.log('Notifying listeners, current notifications:', this.notifications.length);
        this.listeners.forEach((callback, index) => {
            try {
                console.log(`Calling listener ${index}:`, callback);
                callback([...this.notifications]); // Pass a copy to ensure reactivity
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
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
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
     * Get notifications by type
     */
    getNotificationsByType(type) {
        return this.notifications.filter(n => n.type === type);
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
