<template>
    <div class="guardhouse-reports-container">
        <Toast />
        
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="pi pi-shield"></i>
                GuardHouse Reports
            </h1>
            <div class="header-actions">
                <Button 
                    :label="scannerEnabled ? 'Disable Scanner' : 'Enable Scanner'" 
                    :icon="scannerEnabled ? 'pi pi-lock' : 'pi pi-lock-open'"
                    :class="scannerEnabled ? 'p-button-danger' : 'p-button-success'"
                    @click="toggleScanner"
                />
                <Button 
                    label="Archive Current Session" 
                    icon="pi pi-save"
                    class="p-button-warning"
                    @click="archiveCurrentSession"
                    :disabled="!hasActiveRecords"
                />
            </div>
        </div>

        <!-- Live Feed Section -->
        <div class="live-feed-section">
            <div class="feed-grid">
                <!-- Check-In Feed -->
                <div class="feed-column">
                    <div class="feed-header check-in">
                        <h2>
                            <i class="pi pi-sign-in"></i>
                            Live Check-Ins
                        </h2>
                        <Badge :value="checkInCount" severity="success" />
                    </div>
                    <div class="feed-content">
                        <div v-if="checkInRecords.length === 0" class="empty-state">
                            <i class="pi pi-inbox"></i>
                            <p>No check-ins yet today</p>
                        </div>
                        <TransitionGroup name="list" tag="div" v-else>
                            <div 
                                v-for="record in checkInRecords" 
                                :key="record.id"
                                class="feed-item check-in-item"
                            >
                                <div class="student-info">
                                    <div class="student-avatar">
                                        {{ getInitials(record.student_name) }}
                                    </div>
                                    <div class="student-details">
                                        <h4>{{ record.student_name }}</h4>
                                        <p>{{ record.student_id }}</p>
                                        <span class="meta-info">
                                            Grade {{ record.grade_level }} - {{ record.section }}
                                        </span>
                                    </div>
                                </div>
                                <div class="time-info">
                                    <i class="pi pi-clock"></i>
                                    {{ formatTime(record.timestamp) }}
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>

                <!-- Check-Out Feed -->
                <div class="feed-column">
                    <div class="feed-header check-out">
                        <h2>
                            <i class="pi pi-sign-out"></i>
                            Live Check-Outs
                        </h2>
                        <Badge :value="checkOutCount" severity="warning" />
                    </div>
                    <div class="feed-content">
                        <div v-if="checkOutRecords.length === 0" class="empty-state">
                            <i class="pi pi-inbox"></i>
                            <p>No check-outs yet today</p>
                        </div>
                        <TransitionGroup name="list" tag="div" v-else>
                            <div 
                                v-for="record in checkOutRecords" 
                                :key="record.id"
                                class="feed-item check-out-item"
                            >
                                <div class="student-info">
                                    <div class="student-avatar">
                                        {{ getInitials(record.student_name) }}
                                    </div>
                                    <div class="student-details">
                                        <h4>{{ record.student_name }}</h4>
                                        <p>{{ record.student_id }}</p>
                                        <span class="meta-info">
                                            Grade {{ record.grade_level }} - {{ record.section }}
                                        </span>
                                    </div>
                                </div>
                                <div class="time-info">
                                    <i class="pi pi-clock"></i>
                                    {{ formatTime(record.timestamp) }}
                                </div>
                            </div>
                        </TransitionGroup>
                    </div>
                </div>
            </div>

            <!-- Statistics Bar -->
            <div class="stats-bar">
                <div class="stat-card">
                    <i class="pi pi-users"></i>
                    <div class="stat-content">
                        <h3>{{ totalStudentsToday }}</h3>
                        <p>Total Students</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="pi pi-sign-in"></i>
                    <div class="stat-content">
                        <h3>{{ checkInCount }}</h3>
                        <p>Check-Ins</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="pi pi-sign-out"></i>
                    <div class="stat-content">
                        <h3>{{ checkOutCount }}</h3>
                        <p>Check-Outs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <i class="pi pi-clock"></i>
                    <div class="stat-content">
                        <h3>{{ currentTime }}</h3>
                        <p>Current Time</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archived Sessions Section -->
        <div class="archived-section">
            <div class="section-header">
                <h2>
                    <i class="pi pi-history"></i>
                    Archived Sessions
                </h2>
            </div>

            <!-- Session Cards -->
            <div v-if="archivedSessions.length === 0" class="empty-sessions">
                <i class="pi pi-calendar-times"></i>
                <p>No archived sessions found</p>
                <span>Sessions will appear here after archiving</span>
            </div>

            <div v-else class="session-cards">
                <div 
                    v-for="session in archivedSessions" 
                    :key="session.session_id"
                    class="session-card"
                    @click="toggleSessionDetails(session.session_id)"
                >
                    <div class="card-header">
                        <div class="session-date">
                            <i class="pi pi-calendar"></i>
                            {{ formatDate(session.session_date) }}
                        </div>
                        <div class="session-stats">
                            <Badge :value="session.total_records" severity="info" />
                            <i :class="expandedSessions.includes(session.session_id) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"></i>
                        </div>
                    </div>
                    
                    <div class="card-meta">
                        <span>Archived: {{ formatDateTime(session.archived_at) }}</span>
                    </div>

                    <!-- Expanded Session Details -->
                    <div v-if="expandedSessions.includes(session.session_id)" class="session-details">
                        <div v-if="loadingSessionRecords[session.session_id]" class="loading-records">
                            <i class="pi pi-spin pi-spinner"></i>
                            Loading records...
                        </div>
                        
                        <DataTable 
                            v-else-if="sessionRecords[session.session_id]"
                            :value="sessionRecords[session.session_id]" 
                            :paginator="true" 
                            :rows="5"
                            responsiveLayout="scroll"
                            class="session-records-table"
                        >
                            <Column field="student_id" header="Student ID" />
                            <Column field="student_name" header="Name" />
                            <Column field="grade_level" header="Grade" />
                            <Column field="section" header="Section" />
                            <Column field="record_type" header="Type">
                                <template #body="slotProps">
                                    <Tag 
                                        :value="slotProps.data.record_type" 
                                        :severity="slotProps.data.record_type === 'check-in' ? 'success' : 'warning'"
                                    />
                                </template>
                            </Column>
                            <Column field="timestamp" header="Time">
                                <template #body="slotProps">
                                    {{ formatTime(slotProps.data.timestamp) }}
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import Calendar from 'primevue/calendar';
import InputText from 'primevue/inputtext';
import Dropdown from 'primevue/dropdown';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import axios from 'axios';

const toast = useToast();
const API_BASE_URL = 'http://localhost:8000/api';

// State
const scannerEnabled = ref(true);
const checkInRecords = ref([]);
const checkOutRecords = ref([]);
const archivedSessions = ref([]);
const sessionRecords = ref({});
const expandedSessions = ref([]);
const loadingSessionRecords = ref({});
const currentTime = ref('');
const loadingArchived = ref(false);

// Polling interval
let pollingInterval = null;
let timeInterval = null;

// Computed
const checkInCount = computed(() => checkInRecords.value.length);
const checkOutCount = computed(() => checkOutRecords.value.length);
const totalStudentsToday = computed(() => {
    const uniqueStudents = new Set();
    [...checkInRecords.value, ...checkOutRecords.value].forEach(record => {
        uniqueStudents.add(record.student_id);
    });
    return uniqueStudents.size;
});

const hasActiveRecords = computed(() => 
    checkInRecords.value.length > 0 || checkOutRecords.value.length > 0
);

// Methods
const getInitials = (name) => {
    if (!name) return '??';
    const parts = name.split(' ');
    if (parts.length >= 2) {
        return parts[0][0] + parts[parts.length - 1][0];
    }
    return name.substring(0, 2).toUpperCase();
};

const formatTime = (timestamp) => {
    if (!timestamp) return '';
    const date = new Date(timestamp);
    return date.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
    });
};

const formatDateForFilter = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatDateTime = (dateTimeStr) => {
    if (!dateTimeStr) return '';
    const date = new Date(dateTimeStr);
    return date.toLocaleString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};

// Toggle session details
const toggleSessionDetails = async (sessionId) => {
    const index = expandedSessions.value.indexOf(sessionId);
    
    if (index > -1) {
        // Collapse
        expandedSessions.value.splice(index, 1);
    } else {
        // Expand and load records
        expandedSessions.value.push(sessionId);
        
        if (!sessionRecords.value[sessionId]) {
            await loadSessionRecords(sessionId);
        }
    }
};

// Load records for a specific session
const loadSessionRecords = async (sessionId) => {
    loadingSessionRecords.value[sessionId] = true;
    
    try {
        const response = await axios.get(`${API_BASE_URL}/guardhouse/session-records/${sessionId}`);
        if (response.data.success) {
            sessionRecords.value[sessionId] = response.data.records;
        }
    } catch (error) {
        console.error('Error loading session records:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load session records',
            life: 3000
        });
    } finally {
        loadingSessionRecords.value[sessionId] = false;
    }
};

const updateCurrentTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit',
        hour12: true 
    });
};

// Fetch live data
const fetchLiveData = async () => {
    try {
        const response = await axios.get(`${API_BASE_URL}/guardhouse/live-feed`);
        if (response.data.success) {
            // Update check-in records
            const newCheckIns = response.data.check_ins || [];
            checkInRecords.value = newCheckIns.slice(0, 20); // Keep last 20
            
            // Update check-out records
            const newCheckOuts = response.data.check_outs || [];
            checkOutRecords.value = newCheckOuts.slice(0, 20); // Keep last 20
        }
    } catch (error) {
        console.error('Error fetching live data:', error);
        toast.add({
            severity: 'error',
            summary: 'Connection Error',
            detail: 'Unable to fetch live data. Please check backend connection.',
            life: 5000
        });
    }
};

// Toggle scanner
const toggleScanner = async () => {
    try {
        const response = await axios.post(`${API_BASE_URL}/guardhouse/toggle-scanner`, {
            enabled: !scannerEnabled.value
        });
        
        if (response.data.success) {
            scannerEnabled.value = !scannerEnabled.value;
            toast.add({
                severity: scannerEnabled.value ? 'success' : 'warning',
                summary: 'Scanner Status',
                detail: scannerEnabled.value ? 'Scanner enabled' : 'Scanner disabled',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error toggling scanner:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to toggle scanner',
            life: 3000
        });
    }
};

// Archive current session
const archiveCurrentSession = async () => {
    try {
        const records = [
            ...checkInRecords.value.map(r => ({ ...r, record_type: 'check-in' })),
            ...checkOutRecords.value.map(r => ({ ...r, record_type: 'check-out' }))
        ];
        
        const response = await axios.post(`${API_BASE_URL}/guardhouse/archive-session`, {
            session_date: formatDateForFilter(new Date()),
            records: records
        });
        
        if (response.data.success) {
            // Clear current records
            checkInRecords.value = [];
            checkOutRecords.value = [];
            
            // Reload archived records
            await loadArchivedRecords();
            
            toast.add({
                severity: 'success',
                summary: 'Session Archived',
                detail: `Archived ${records.length} records`,
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error archiving session:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to archive session',
            life: 3000
        });
    }
};

// Load archived sessions (date-based cards)
const loadArchivedRecords = async () => {
    loadingArchived.value = true;
    try {
        const response = await axios.get(`${API_BASE_URL}/guardhouse/archived-sessions`);
        if (response.data.success) {
            archivedSessions.value = response.data.sessions || [];
        }
    } catch (error) {
        console.error('Error loading archived sessions:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load archived sessions',
            life: 3000
        });
    } finally {
        loadingArchived.value = false;
    }
};

// Lifecycle
onMounted(() => {
    // Initial load
    fetchLiveData();
    loadArchivedRecords();
    updateCurrentTime();
    
    // Set up polling for live data (every 5 seconds)
    pollingInterval = setInterval(fetchLiveData, 5000);
    
    // Update time every second
    timeInterval = setInterval(updateCurrentTime, 1000);
});

onUnmounted(() => {
    // Clean up intervals
    if (pollingInterval) {
        clearInterval(pollingInterval);
    }
    if (timeInterval) {
        clearInterval(timeInterval);
    }
});
</script>

<style scoped>
.guardhouse-reports-container {
    padding: 1.5rem;
    background: #f8f9fa;
    min-height: calc(100vh - 4rem);
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Live Feed Section */
.live-feed-section {
    margin-bottom: 2rem;
}

.feed-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.feed-column {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.feed-header {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #e9ecef;
}

.feed-header.check-in {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.feed-header.check-out {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.feed-header h2 {
    font-size: 1.25rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.feed-content {
    max-height: 400px;
    overflow-y: auto;
    padding: 1rem;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.feed-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    margin-bottom: 0.75rem;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.check-in-item {
    background: #f0fdf4;
    border-left: 4px solid #10b981;
}

.check-out-item {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
}

.feed-item:hover {
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.student-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.student-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.student-details h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
}

.student-details p {
    margin: 0.25rem 0;
    font-size: 0.875rem;
    color: #6c757d;
}

.meta-info {
    font-size: 0.75rem;
    color: #95a5a6;
}

.time-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6c757d;
    font-size: 0.875rem;
}

/* Statistics Bar */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card i {
    font-size: 2rem;
    color: #3b82f6;
    opacity: 0.8;
}

.stat-content h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
}

.stat-content p {
    margin: 0.25rem 0 0;
    font-size: 0.875rem;
    color: #6c757d;
}

/* Archived Section */
.archived-section {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e9ecef;
}

.section-header h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2c3e50;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Empty Sessions State */
.empty-sessions {
    text-align: center;
    padding: 3rem 1rem;
    color: #6c757d;
}

.empty-sessions i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Session Cards */
.session-cards {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.session-card {
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.session-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    transform: translateY(-2px);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.session-date {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #2c3e50;
}

.session-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-meta {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

.session-details {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e9ecef;
}

.loading-records {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    justify-content: center;
    color: #6c757d;
}

.session-records-table {
    margin-top: 1rem;
}

/* Transitions */
.list-enter-active,
.list-leave-active {
    transition: all 0.5s ease;
}

.list-enter-from {
    opacity: 0;
    transform: translateX(-30px);
}

.list-leave-to {
    opacity: 0;
    transform: translateX(30px);
}

/* Responsive */
@media (max-width: 1024px) {
    .feed-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-bar {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .page-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .filter-controls {
        flex-direction: column;
        width: 100%;
    }
    
    .search-input {
        width: 100%;
    }
}
</style>
