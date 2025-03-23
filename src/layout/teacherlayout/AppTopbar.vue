<script setup>
import { useLayout } from '@/layout/composables/layout';
import Button from 'primevue/button';
import { computed, ref } from 'vue';
const { toggleMenu } = useLayout();

const isCalendarOpen = ref(false); // Modal control

// Sample school activities
const activities = ref([
    { description: 'Christmas Holiday', dates: '2025-12-25', color: 'red' },
    { description: 'New Year Holiday', dates: '2025-01-01', color: 'red' },
    { description: 'Teacher Meeting', dates: '2025-04-10', color: 'blue' },
    { description: 'Final Exams Start', dates: '2025-06-05', color: 'green' },
    { description: 'Parent-Teacher Conference', dates: '2025-05-20', color: 'orange' },
]);

const attributes = computed(() => {
    // Extract holiday dates
    const holidayDates = activities.value.map(event => event.dates);

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
        ...activities.value.map(event => ({
            dates: event.dates,
            dot: { color: event.color },
            popover: { label: event.description },
        })),
        // Mark filtered teacher workdays
        {
            dates: teacherWorkdays, // Only includes valid Mon-Fri workdays, excluding holidays
            highlight: { color: 'blue', fillMode: 'light' },
            popover: { label: 'Teacher Workday' },
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
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu" >
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/teacher" class="layout-topbar-logo">
                <img src="/demo/images/logo.svg" alt="Logo" />

                <span>NCS- for Teachers</span>
            </router-link>
            <Button class="back-button" @click="$router.push('/')">
                    <i class="pi pi-arrow-left"></i> Back to Login
            </Button>
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button type="button" class="layout-topbar-action" @click="isCalendarOpen = true">
                        <i class="pi pi-calendar"></i>
                        <span>Calendar</span>
                    </button>
                    <!-- Profile Button with Dropdown -->
                    <div class="relative">
                        <button type="button" class="layout-topbar-action" @click="isProfileOpen = !isProfileOpen">
                            <i class="pi pi-user"></i>
                            <span>Profile</span>
                        </button>
                        <!-- Styled Dropdown Menu -->
                        <div v-if="isProfileOpen" class="profile-dropdown">
                            <button class="logout-button" @click="logout">
                                <i class="pi pi-sign-out"></i> Log Out
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <Dialog v-model:visible="isCalendarOpen" header="School Activities Calendar" :modal="true" :style="{ width: '290px', maxWidth: '90vw' }">
            <VCalendar
        is-expanded
        :attributes="attributes"
        first-day-of-week="1"
        theme-styling="rounded border shadow-lg bg-white"
    />
    </Dialog>

    <!-- Log Out Confirmation Modal -->
    <Dialog v-model:visible="isLogoutSuccess" header="Success" :modal="true" :style="{ width: '250px' }">
        <p>You have successfully logged out.</p>
        <template #footer>
            <button class="p-button p-button-primary" @click="isLogoutSuccess = false">OK</button>
        </template>
    </Dialog>

</template>

<style scoped>
img {
    width: 50px;
    height: 50px;
}
.profile-dropdown {
    position: absolute;
    right: 0;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 120px;
    z-index: 100;
}

.logout-button {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: #f56565; /* Red button */
    color: white;
    font-size: 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
}

.logout-button:hover {
    background: #c53030; /* Darker red on hover */
}

.logout-button i {
    margin-right: 6px;
}
.back-button {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  top: 10px; /* Adjust to align properly within the top bar */
  background-color: #1976D2;
  color: white;
  font-weight: bold;
  padding: 8px 16px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
  transition: 0.3s ease-in-out;
  box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
}

.back-button:hover {
  background-color: #1565C0;
  transform: translateX(-50%) scale(1.05);
}

.back-button:active {
  transform: translateX(-50%) scale(0.95);
}
</style>
