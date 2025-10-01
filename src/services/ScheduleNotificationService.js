import TeacherAuthService from './TeacherAuthService';
import NotificationService from './NotificationService';
import axios from 'axios';

class ScheduleNotificationService {
    constructor() {
        this.schedules = [];
        this.currentSchedule = null;
        this.currentSchedules = []; // Initialize current schedules array
        this.notifications = []; // Initialize notifications array for duplicate checking
        this.notificationService = NotificationService;
        this.checkInterval = null;
        this.notifiedSchedules = new Set(); // Track schedules we've already notified about
        this.processedScheduleEnds = new Set(); // Track schedules that have ended and been processed
        this.listeners = []; // Initialize listeners array
        this.timers = new Map(); // Initialize timers map for schedule notifications
    }

    /**
     * Initialize the schedule notification system
     */
    async initialize() {
        if (this.isActive) return;
        
        console.log('Initializing Schedule Notification Service');
        
        try {
            // Check if teacher is authenticated
            if (!TeacherAuthService.isAuthenticated()) {
                console.log('⚠️ Teacher not authenticated, skipping schedule notifications');
                return;
            }

            this.isActive = true;
            
            // Request notification permission
            await this.requestNotificationPermission();
            
            // Start checking for schedules
            await this.checkSchedules();
            
            // Set up periodic checking (every 10 seconds for faster auto-recording)
            this.checkInterval = setInterval(() => {
                this.checkSchedules();
            }, 10000);
            
            console.log('✅ Schedule Notification Service initialized');
        } catch (error) {
            console.error('❌ Error initializing schedule notifications:', error);
        }
    }

    /**
     * Request browser notification permission
     */
    async requestNotificationPermission() {
        if ('Notification' in window) {
            if (Notification.permission === 'default') {
                const permission = await Notification.requestPermission();
                console.log('📱 Notification permission:', permission);
            }
        }
    }

    /**
     * Check for upcoming schedules and send notifications
     */
    async checkSchedules() {
        try {
            const teacherData = TeacherAuthService.getTeacherData();
            if (!teacherData?.teacher?.id) return;

            const response = await axios.get(`/api/schedule-notifications/teacher/${teacherData.teacher.id}/upcoming`);
            const schedules = response.data.data || [];
            
            this.currentSchedules = schedules;
            this.processScheduleNotifications(schedules);
            this.notifyListeners(schedules);
            
        } catch (error) {
            console.error('Error checking schedules:', error);
        }
    }

    /**
     * Process schedule notifications
     */
    processScheduleNotifications(schedules) {
        schedules.forEach(schedule => {
            const scheduleKey = `${schedule.id}_${schedule.status}`;
            
            // Clear existing timer for this schedule
            if (this.timers.has(scheduleKey)) {
                clearTimeout(this.timers.get(scheduleKey));
            }

            switch (schedule.notification_type) {
                case 'starting_soon':
                    this.sendNotification({
                        type: 'starting_soon',
                        title: '📚 Class Starting Soon',
                        message: `${schedule.subject_name} in ${schedule.section_name} starts in ${Math.abs(schedule.minutes_to_start)} minutes`,
                        schedule: schedule,
                        priority: 'high'
                    });
                    break;

                case 'ending_soon':
                    this.sendNotification({
                        type: 'ending_soon',
                        title: '⏰ Class Ending Soon',
                        message: `${schedule.subject_name} ends in ${Math.abs(schedule.minutes_to_end)} minutes. Students without attendance will be marked absent.`,
                        schedule: schedule,
                        priority: 'warning'
                    });
                    break;

                case 'ended':
                    // Automatically mark absent students when schedule ends
                    this.handleScheduleEnd(schedule);
                    break;

                case 'in_progress':
                    // Check if there's an active session
                    this.checkActiveSession(schedule);
                    break;
            }
        });
    }

    /**
     * Check if there's an active session for a schedule
     */
    async checkActiveSession(schedule) {
        try {
            const response = await axios.get(`/api/schedule-notifications/schedule/${schedule.id}/active-session`);
            const hasActiveSession = response.data.has_session;
            
            if (!hasActiveSession) {
                this.sendNotification({
                    type: 'no_active_session',
                    title: '📝 No Active Session',
                    message: `${schedule.subject_name} is scheduled now but no attendance session is active. Start taking attendance?`,
                    schedule: schedule,
                    priority: 'info',
                    actions: [
                        {
                            label: 'Start Session',
                            action: 'start_session',
                            data: schedule
                        }
                    ]
                });
            }
        } catch (error) {
            console.error('Error checking active session:', error);
        }
    }

    /**
     * Handle schedule end - automatically mark absent students
     */
    async handleScheduleEnd(schedule) {
        try {
            // Create a unique key for this schedule end event (schedule_id + date)
            const today = new Date().toISOString().split('T')[0];
            const scheduleEndKey = `${schedule.id}_${today}`;
            
            // Check if we've already processed this schedule end today
            if (this.processedScheduleEnds.has(scheduleEndKey)) {
                return; // Already processed, skip
            }
            
            console.log('🏁 Schedule ended, checking for auto-absent marking:', schedule.subject_name);
            
            // Check if there's an active session for this schedule
            const sessionResponse = await axios.get(`/api/schedule-notifications/schedule/${schedule.id}/active-session`);
            
            if (sessionResponse.data.has_session && sessionResponse.data.session) {
                const session = sessionResponse.data.session;
                console.log('📋 Found active session, marking unmarked students as absent:', session.id);
                
                // Call API to auto-mark absent students
                const markAbsentResponse = await axios.post(`/api/attendance-sessions/${session.id}/auto-mark-absent`, {
                    schedule_id: schedule.id,
                    subject_id: schedule.subject_id,
                    section_id: schedule.section_id
                });
                
                if (markAbsentResponse.data.success) {
                    const markedCount = markAbsentResponse.data.marked_absent_count || 0;
                    
                    // Send notification about auto-marking
                    this.sendNotification({
                        type: 'session_auto_completed',
                        title: '✅ Session Auto-Completed',
                        message: `${schedule.subject_name} session ended. ${markedCount} students automatically marked absent.`,
                        schedule: schedule,
                        priority: 'info'
                    });
                    
                    console.log(`✅ Auto-marked ${markedCount} students as absent for ${schedule.subject_name}`);
                } else {
                    console.warn('⚠️ Failed to auto-mark absent students:', markAbsentResponse.data.message);
                }
            } else {
                console.log('⚠️ No active session found - creating session and marking all students absent:', schedule.subject_name);
                
                // No session exists, create one automatically and mark all students absent
                const today = new Date().toISOString().split('T')[0]; // Get current date in YYYY-MM-DD format
                const createSessionResponse = await axios.post('/api/schedule-notifications/auto-create-session', {
                    schedule_id: schedule.id,
                    teacher_id: schedule.teacher_id,
                    subject_id: schedule.subject_id,
                    section_id: schedule.section_id,
                    schedule_date: today,
                    start_time: schedule.start_time,
                    end_time: schedule.end_time
                });
                
                if (createSessionResponse.data.success) {
                    const markedCount = createSessionResponse.data.marked_absent_count || 0;
                    const allMarked = createSessionResponse.data.all_marked || false;
                    
                    // Send notification based on what happened
                    if (markedCount > 0) {
                        this.sendNotification({
                            type: 'session_auto_created',
                            title: '⚠️ Attendance Auto-Recorded',
                            message: `${schedule.subject_name} ended. Auto-marked ${markedCount} unmarked student${markedCount > 1 ? 's' : ''} as absent.`,
                            schedule: schedule,
                            priority: 'warning'
                        });
                        console.log(`✅ Auto-marked ${markedCount} students as absent for ${schedule.subject_name}`);
                    } else if (allMarked) {
                        // All students already marked - no notification needed, just log
                        console.log(`✅ ${schedule.subject_name} ended - all students already marked by teacher`);
                    } else {
                        // Session created but no students
                        console.log(`✅ Session auto-completed for ${schedule.subject_name}`);
                    }
                    
                    // Mark as processed to prevent duplicate attempts
                    this.processedScheduleEnds.add(scheduleEndKey);
                } else {
                    throw new Error(createSessionResponse.data.message || 'Failed to create auto-session');
                }
            }
            
            // Mark as processed even if we found an active session (to prevent repeated checks)
            this.processedScheduleEnds.add(scheduleEndKey);
        } catch (error) {
            console.error('❌ Error handling schedule end:', error);
            
            // Send error notification
            this.sendNotification({
                type: 'session_end_error',
                title: '⚠️ Auto-Completion Failed',
                message: `Failed to auto-mark absent students for ${schedule.subject_name}. Please check manually.`,
                schedule: schedule,
                priority: 'warning'
            });
        }
    }

    /**
     * Send notification (both browser and in-app)
     */
    sendNotification(notification) {
        // Prevent duplicate notifications by checking if similar notification was sent recently
        const recentNotifications = this.notifications.filter(n => 
            n.type === notification.type && 
            n.schedule?.subject_id === notification.schedule?.subject_id &&
            Date.now() - new Date(n.timestamp).getTime() < 300000 // Within last 5 minutes
        );
        
        if (recentNotifications.length > 0) {
            console.log('🚫 Skipping duplicate notification:', notification.title);
            return;
        }
        
        // Add timestamp and ID
        notification.id = Date.now() + Math.random();
        notification.timestamp = new Date();
        
        // Add to local notifications for duplicate checking
        this.notifications.unshift(notification);
        
        // Keep only last 10 notifications
        if (this.notifications.length > 10) {
            this.notifications = this.notifications.slice(0, 10);
        }
        
        // Send to main notification system (AppTopbar)
        let teacherData = TeacherAuthService.getTeacherData();
        console.log('🔍 Teacher data retrieved for notification:', teacherData);
        
        // Fallback: try to get teacher data from localStorage directly
        if (!teacherData || !teacherData.id) {
            try {
                // Try multiple possible localStorage keys
                let storedTeacherData = localStorage.getItem('teacherData');
                if (!storedTeacherData) {
                    storedTeacherData = localStorage.getItem('teacher_data');
                }
                if (!storedTeacherData) {
                    storedTeacherData = localStorage.getItem('user_data');
                }
                
                if (storedTeacherData) {
                    const parsedData = JSON.parse(storedTeacherData);
                    console.log('🔄 Parsed data from localStorage:', parsedData);
                    
                    // Check if it's an array and extract the first item, or use as is
                    if (Array.isArray(parsedData)) {
                        teacherData = parsedData[0] || parsedData.find(item => item.id);
                        console.log('🔄 Extracted teacher from array:', teacherData);
                    } else if (parsedData && typeof parsedData === 'object') {
                        teacherData = parsedData;
                        console.log('🔄 Using teacher object:', teacherData);
                    }
                }
            } catch (error) {
                console.error('❌ Error parsing teacher data from localStorage:', error);
            }
        }
        
        if (teacherData && teacherData.id) {
            const notificationData = {
                id: notification.id,
                title: notification.title,
                message: notification.message,
                type: 'schedule',
                priority: notification.type === 'no_active_session' ? 'high' : 'medium',
                teacher_id: teacherData.id,
                created_at: notification.timestamp.toISOString(),
                timestamp: notification.timestamp.toISOString(), // Add timestamp field for UI
                is_read: false,
                metadata: {
                    teacherId: teacherData.id,
                    userId: teacherData.id,
                    subjectId: notification.schedule?.subject_id,
                    sectionId: notification.schedule?.section_id,
                    schedule_type: notification.type,
                    source: 'schedule_notification_service'
                }
            };
            
            console.log('📬 Sending schedule notification to AppTopbar:', notificationData);
            console.log('✅ Teacher ID being set:', teacherData.id);
            
            // Save notification to database via API
            this.saveNotificationToDatabase(notificationData);
            
            // Also add to in-memory for immediate display
            NotificationService.addNotification(notificationData);
        } else {
            console.warn('⚠️ No teacher data found or missing ID, cannot send notification to AppTopbar');
            console.warn('⚠️ Teacher data:', teacherData);
        }
        
        // Send browser notification (less frequently)
        if (notification.type !== 'no_active_session') {
            this.sendBrowserNotification(notification);
        }
        
        // Notify listeners
        this.notifyListeners(this.currentSchedules, notification);
        
        console.log('🔔 Notification sent:', notification.title);
    }

    /**
     * Save notification to database via API
     */
    async saveNotificationToDatabase(notificationData) {
        try {
            const response = await axios.post('/api/notifications', {
                user_id: notificationData.teacher_id,
                type: notificationData.type,
                title: notificationData.title,
                message: notificationData.message,
                data: notificationData.metadata,
                priority: notificationData.priority || 'medium'
            });
            
            console.log('💾 Notification saved to database:', response.data);
        } catch (error) {
            console.error('❌ Failed to save notification to database:', error);
        }
    }

    /**
     * Send browser notification
     */
    sendBrowserNotification(notification) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const browserNotification = new Notification(notification.title, {
                body: notification.message,
                icon: '/favicon.ico',
                badge: '/favicon.ico',
                tag: `schedule_${notification.schedule?.id}`,
                requireInteraction: notification.priority === 'warning'
            });

            // Handle notification click
            browserNotification.onclick = () => {
                window.focus();
                this.handleNotificationClick(notification);
                browserNotification.close();
            };

            // Auto-close after 10 seconds (except warnings)
            if (notification.priority !== 'warning') {
                setTimeout(() => {
                    browserNotification.close();
                }, 10000);
            }
        }
    }

    /**
     * Handle notification click
     */
    handleNotificationClick(notification) {
        if (notification.actions && notification.actions.length > 0) {
            const action = notification.actions[0];
            if (action.action === 'start_session') {
                this.startAttendanceSession(action.data);
            }
        }
    }

    /**
     * Start attendance session for a schedule
     */
    async startAttendanceSession(schedule) {
        try {
            // Navigate to attendance session creation
            const router = window.$router || this.router;
            if (router) {
                router.push({
                    name: 'teacher-attendance-sessions',
                    query: {
                        autoCreate: 'true',
                        scheduleId: schedule.id,
                        sectionId: schedule.section_id,
                        subjectId: schedule.subject_id
                    }
                });
            }
        } catch (error) {
            console.error('Error starting attendance session:', error);
        }
    }

    /**
     * Get current schedule status for display
     */
    getCurrentScheduleStatus() {
        const now = new Date();
        const currentSchedule = this.currentSchedules.find(schedule => {
            const startTime = new Date(schedule.schedule_datetime_start);
            const endTime = new Date(schedule.schedule_datetime_end);
            return now >= startTime && now <= endTime;
        });

        if (currentSchedule) {
            return {
                isActive: true,
                schedule: currentSchedule,
                timeRemaining: Math.max(0, Math.floor((new Date(currentSchedule.schedule_datetime_end) - now) / 60000))
            };
        }

        // Find next upcoming schedule
        const nextSchedule = this.currentSchedules
            .filter(schedule => new Date(schedule.schedule_datetime_start) > now)
            .sort((a, b) => new Date(a.schedule_datetime_start) - new Date(b.schedule_datetime_start))[0];

        if (nextSchedule) {
            return {
                isActive: false,
                nextSchedule: nextSchedule,
                timeToNext: Math.floor((new Date(nextSchedule.schedule_datetime_start) - now) / 60000)
            };
        }

        return {
            isActive: false,
            nextSchedule: null,
            timeToNext: null
        };
    }

    /**
     * Add listener for schedule updates
     */
    addListener(callback) {
        this.listeners.push(callback);
    }

    /**
     * Remove listener
     */
    removeListener(callback) {
        const index = this.listeners.indexOf(callback);
        if (index > -1) {
            this.listeners.splice(index, 1);
        }
    }

    /**
     * Notify all listeners
     */
    notifyListeners(schedules, newNotification = null) {
        this.listeners.forEach(callback => {
            try {
                callback({
                    schedules: schedules,
                    notifications: this.notifications,
                    newNotification: newNotification,
                    currentStatus: this.getCurrentScheduleStatus()
                });
            } catch (error) {
                console.error('Error in schedule notification listener:', error);
            }
        });
    }

    /**
     * Get all notifications
     */
    getNotifications() {
        return this.notifications;
    }

    /**
     * Clear a notification
     */
    clearNotification(notificationId) {
        this.notifications = this.notifications.filter(n => n.id !== notificationId);
        this.notifyListeners(this.currentSchedules);
    }

    /**
     * Clear all notifications
     */
    clearAllNotifications() {
        this.notifications = [];
        this.notifyListeners(this.currentSchedules);
    }

    /**
     * Validate session timing
     */
    async validateSessionTiming(sectionId, subjectId) {
        try {
            const teacherData = TeacherAuthService.getTeacherData();
            if (!teacherData?.teacher?.id) {
                throw new Error('Teacher not authenticated');
            }

            const response = await axios.post('/api/schedule-notifications/validate-timing', {
                teacher_id: teacherData.teacher.id,
                section_id: sectionId,
                subject_id: subjectId
            });

            return response.data;
        } catch (error) {
            console.error('Error validating session timing:', error);
            return {
                is_valid: false,
                warning_type: 'error',
                message: 'Unable to validate session timing',
                can_proceed: true
            };
        }
    }

    /**
     * Destroy the service
     */
    destroy() {
        this.isActive = false;
        
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
        
        // Clear all timers
        this.timers.forEach(timer => clearTimeout(timer));
        this.timers.clear();
        
        this.listeners = [];
        this.notifications = [];
        this.currentSchedules = [];
        
        console.log('🔔 Schedule Notification Service destroyed');
    }
}

// Create singleton instance
const scheduleNotificationService = new ScheduleNotificationService();

export default scheduleNotificationService;
