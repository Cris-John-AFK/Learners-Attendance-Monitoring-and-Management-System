<template>
    <div class="card h-full">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-xl">Next Class Session</h3>
            <span class="text-sm font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded">Live</span>
        </div>

        <!-- Show calendar event if there is one -->
        <div v-if="calendarEvent" class="bg-gradient-to-r from-green-50 to-blue-50 rounded-lg p-4 border-l-4 border-green-500">
            <div class="flex items-center mb-3">
                <i class="pi pi-calendar text-green-600 mr-2 text-2xl"></i>
                <span class="text-lg font-semibold text-green-800">{{ calendarEvent.icon }} {{ calendarEvent.event_title }}</span>
            </div>

            <div class="mb-4">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-sm font-medium bg-green-100 text-green-800 px-2 py-1 rounded">{{ formatEventType(calendarEvent.event_type) }}</span>
                </div>
                <p class="text-gray-700 font-medium">
                    <i class="pi pi-info-circle mr-2 text-green-600"></i>
                    No attendance required today
                </p>
            </div>

            <div class="mt-4 pt-3 border-t border-green-200">
                <p class="text-sm text-gray-600">
                    <i class="pi pi-check-circle text-green-600 mr-1"></i>
                    Enjoy your day off!
                </p>
            </div>
        </div>

        <!-- Show regular class schedule if no calendar event -->
        <div v-else class="bg-white rounded-lg p-4 border-l-4 border-primary">
            <div class="flex items-center mb-3">
                <i class="pi pi-clock text-primary mr-2"></i>
                <span class="text-lg font-semibold">{{ currentTime }}</span>
            </div>

            <div class="mb-4">
                <h4 class="font-medium text-lg text-gray-800">{{ nextClass.subject }}</h4>
                <p class="text-gray-600">Grade {{ nextClass.grade }} - Section {{ nextClass.section }}</p>
                <div class="flex items-center mt-2">
                    <i class="pi pi-calendar text-gray-500 mr-2"></i>
                    <span class="text-gray-600">{{ nextClass.time }}</span>
                </div>
                <div class="flex items-center mt-1">
                    <i class="pi pi-map-marker text-gray-500 mr-2"></i>
                    <span class="text-gray-600">{{ nextClass.room }}</span>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t">
                <p class="text-sm text-gray-500">
                    <i class="pi pi-info-circle mr-1"></i>
                    Next class starts in {{ nextClass.startsIn }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import api from '@/config/axios';

export default {
    data() {
        return {
            currentTime: '',
            calendarEvent: null,
            nextClass: {
                subject: 'Mathematics',
                grade: '3',
                section: 'Sampaguita',
                time: '10:30 AM - 11:30 AM',
                room: 'Room 304',
                startsIn: '25 minutes'
            }
        };
    },
    mounted() {
        this.updateTime();
        setInterval(this.updateTime, 1000);
        this.checkCalendarEvent();
    },
    methods: {
        updateTime() {
            const now = new Date();
            this.currentTime = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        },
        async checkCalendarEvent() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const response = await api.get('/api/calendar/events');
                
                if (response.data.success && response.data.events) {
                    // Find event for today
                    const todayEvent = response.data.events.find(event => {
                        const startDate = event.start_date.split('T')[0];
                        const endDate = event.end_date.split('T')[0];
                        return today >= startDate && today <= endDate && event.is_active;
                    });
                    
                    if (todayEvent) {
                        this.calendarEvent = {
                            event_title: todayEvent.title,
                            event_type: todayEvent.event_type,
                            affects_attendance: todayEvent.affects_attendance,
                            icon: this.getEventIcon(todayEvent.event_type)
                        };
                    }
                }
            } catch (error) {
                console.error('Error checking calendar event:', error);
            }
        },
        getEventIcon(eventType) {
            const icons = {
                'holiday': '🎄',
                'half_day': '⏰',
                'early_dismissal': '🏠',
                'no_classes': '📋',
                'school_event': '🎉',
                'teacher_training': '👨‍🏫',
                'exam_day': '📝'
            };
            return icons[eventType] || '📅';
        },
        formatEventType(eventType) {
            return eventType.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }
    }
};
</script>

<style scoped>
.border-primary {
    border-color: var(--primary-color);
}
.text-primary {
    color: var(--primary-color);
}
</style>
