<script setup>
import { useLayout } from '@/layout/composables/layout';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { computed, ref } from 'vue';
const { toggleMenu } = useLayout();

const isCalendarOpen = ref(false); // Modal control

// Sample school activities
const activities = ref([
    { description: 'Christmas Holiday', dates: '2025-12-25', color: 'red' },
    { description: 'New Year Holiday', dates: '2025-01-01', color: 'red' },
    { description: 'Teacher Meeting', dates: '2025-04-10', color: 'blue' },
    { description: 'Final Exams Start', dates: '2025-06-05', color: 'green' },
    { description: 'Parent-Teacher Conference', dates: '2025-05-20', color: 'orange' }
]);

const attributes = computed(() => {
    // Extract holiday dates
    const holidayDates = activities.value.map((event) => event.dates);

    // Generate teacher workdays for Mon-Fri, but exclude holidays
    const teacherWorkdays = [];
    let currentDate = new Date('2025-01-01'); // Start of the year
    const endDate = new Date('2025-12-31'); // End of the year

    while (currentDate <= endDate) {
        const isoDate = currentDate.toISOString().split('T')[0]; // Format YYYY-MM-DD

        // If it's Mon-Fri and NOT a holiday, add it as a teacher workday
        if ([1, 2, 3, 4, 5].includes(currentDate.getDay()) && !holidayDates.includes(isoDate)) {
            teacherWorkdays.push(isoDate);
        }

        // Move to the next day
        currentDate.setDate(currentDate.getDate() + 1);
    }

    return [
        // Mark no-class days (holidays)
        ...activities.value.map((event) => ({
            dates: event.dates,
            dot: { color: event.color },
            popover: { label: event.description }
        })),
        // Mark filtered teacher workdays
        {
            dates: teacherWorkdays, // Only includes valid Mon-Fri workdays, excluding holidays
            highlight: { color: 'blue', fillMode: 'light' },
            popover: { label: 'Teacher Workday' }
        }
    ];
});

const isProfileOpen = ref(false); // Controls dropdown visibility

const isLogoutSuccess = ref(false); // Controls the log out confirmation modal

const logout = () => {
    isLogoutSuccess.value = true; // Show the success message
};
</script>

<template>
    <div>
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                    <i class="pi pi-bars"></i>
                </button>
                <router-link to="/teacher" class="layout-topbar-logo">
                    <img src="/demo/images/logo.svg" alt="Logo" />
                    <span>NCS- for Teachers</span>
                </router-link>
            </div>

            <div class="layout-topbar-center">
                <Button class="back-button" @click="$router.push('/')">
                    <i class="pi pi-arrow-left"></i>
                    Back to Login
                </Button>
            </div>

            <div class="layout-topbar-actions">
                <button type="button" class="layout-topbar-action" @click="isCalendarOpen = true">
                    <i class="pi pi-calendar"></i>
                    <span>Calendar</span>
                </button>

                <button type="button" class="layout-topbar-action" @click="$router.push('/pages/settings')">
                    <i class="pi pi-cog"></i>
                    <span>Settings</span>
                </button>

                <!-- Profile Button with Dropdown -->
                <div class="relative">
                    <button type="button" class="layout-topbar-action" @click="isProfileOpen = !isProfileOpen">
                        <i class="pi pi-user"></i>
                        <span>Profile</span>
                    </button>
                    <!-- Styled Dropdown Menu -->
                    <div v-if="isProfileOpen" class="profile-dropdown">
                        <button class="logout-button" @click="logout"><i class="pi pi-sign-out"></i> Log Out</button>
                    </div>
                </div>
            </div>
        </div>

        <Dialog v-model:visible="isCalendarOpen" header="School Activities Calendar" :modal="true" :style="{ width: '290px', maxWidth: '90vw' }">
            <VCalendar is-expanded :attributes="attributes" first-day-of-week="1" theme-styling="rounded border shadow-lg bg-white" />
        </Dialog>

        <!-- Log Out Confirmation Modal -->
        <Dialog v-model="isLogoutSuccess" header="Success" :modal="true" :style="{ width: '250px' }">
            <p>You have successfully logged out.</p>
            <template #footer>
                <button class="p-button p-button-primary" @click="isLogoutSuccess = false">OK</button>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.layout-topbar {
    position: fixed;
    height: 4rem;
    z-index: 997;
    left: 0;
    top: 0;
    width: 100%;
    padding: 0 2rem;
    background-color: var(--surface-card);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.layout-topbar-logo-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 200px;
}

.layout-topbar-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: var(--text-color);
}

.layout-topbar-logo img {
    height: 2.5rem;
}

.layout-topbar-center {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
}

.layout-topbar-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 200px;
    justify-content: flex-end;
}

.layout-topbar-action {
    width: auto;
    height: 2.5rem;
    padding: 0 0.5rem;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-color);
    transition: background-color 0.2s;
}

.layout-topbar-action:hover {
    background-color: var(--surface-hover);
}

.layout-topbar-action i {
    font-size: 1.25rem;
}

.back-button {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border: none;
    padding: 0.75rem 1.5rem;
    color: white;
    border-radius: 8px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.back-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.back-button:active {
    transform: scale(0.98);
}

.profile-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 120px;
    z-index: 100;
    border-radius: 6px;
    overflow: hidden;
}

.logout-button {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: #f56565;
    color: white;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
}

.logout-button:hover {
    background: #c53030;
}

.logout-button i {
    margin-right: 6px;
}

@media (max-width: 991px) {
    .layout-topbar {
        padding: 0 1rem;
    }

    .layout-topbar-logo-container {
        min-width: auto;
    }

    .layout-topbar-actions {
        min-width: auto;
    }

    .layout-topbar-action span {
        display: none;
    }
}
</style>
