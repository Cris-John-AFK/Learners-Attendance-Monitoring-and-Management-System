<template>
    <div class="admin-dashboard" style="margin: 0 1rem">
        <!-- Loading State -->
        <div v-if="loading" class="loading-overlay">
            <div class="loading-content">
                <ProgressSpinner strokeWidth="4" style="width: 60px; height: 60px" class="text-purple-500" />
                <p class="mt-4 text-gray-600 font-medium">Loading dashboard data...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-card">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="pi pi-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-red-800 font-semibold">Error Loading Data</h3>
                        <p class="text-red-600 text-sm mt-1">{{ error }}</p>
                    </div>
                </div>
                <button @click="loadDashboardData" class="retry-btn"><i class="pi pi-refresh mr-2"></i>Retry</button>
            </div>
        </div>

        <div v-else>
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="pi pi-chart-line"></i>
                        </div>
                        <div class="header-text">
                            <h1 class="header-title">Admin Dashboard</h1>
                            <p class="header-subtitle">Naawan Central School - Overview</p>
                            <div class="school-year">
                                <i class="pi pi-calendar mr-2"></i>
                                School Year: <span class="year-badge">{{ currentSchoolYear }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="last-updated">
                            <i class="pi pi-clock mr-2"></i>
                            Last Updated: {{ lastUpdated }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Statistics Cards -->
            <div class="dashboard-stats">
                <div class="stats-grid">
                    <!-- Total Students Card -->
                    <div class="stat-card students-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="pi pi-users"></i>
                            </div>
                            <div class="stat-info">
                                <h3 class="stat-title">Total Students</h3>
                                <p class="stat-subtitle">Active Enrollments</p>
                            </div>
                        </div>
                        <div class="stat-value">
                            <span class="stat-number">{{ totalStudents }}</span>
                            <span class="stat-label">Students</span>
                        </div>
                    </div>

                    <!-- Total Teachers Card -->
                    <div class="stat-card teachers-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="pi pi-user-edit"></i>
                            </div>
                            <div class="stat-info">
                                <h3 class="stat-title">Total Teachers</h3>
                                <p class="stat-subtitle">Faculty Members</p>
                            </div>
                        </div>
                        <div class="stat-value">
                            <span class="stat-number">{{ totalTeachers }}</span>
                            <span class="stat-label">Teachers</span>
                        </div>
                    </div>

                    <!-- Total Sections Card -->
                    <div class="stat-card sections-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="pi pi-building"></i>
                            </div>
                            <div class="stat-info">
                                <h3 class="stat-title">Total Sections</h3>
                                <p class="stat-subtitle">Class Sections</p>
                            </div>
                        </div>
                        <div class="stat-value">
                            <span class="stat-number">{{ totalSections }}</span>
                            <span class="stat-label">Sections</span>
                        </div>
                    </div>

                    <!-- School Year Card -->
                    <div class="stat-card school-year-card">
                        <div class="stat-header">
                            <div class="stat-icon">
                                <i class="pi pi-calendar-plus"></i>
                            </div>
                            <div class="stat-info">
                                <h3 class="stat-title">Academic Year</h3>
                                <p class="stat-subtitle">Current Session</p>
                            </div>
                        </div>
                        <div class="stat-value">
                            <span class="stat-number">{{ currentSchoolYear }}</span>
                            <span class="stat-label">School Year</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import api, { API_BASE_URL } from '@/config/axios';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, onMounted, ref } from 'vue';

// Reactive data
const loading = ref(true);
const error = ref(null);
const totalStudents = ref(0);
const totalTeachers = ref(0);
const totalSections = ref(0);
const currentSchoolYear = ref('2024-2025');

// Computed properties
const lastUpdated = computed(() => {
    return new Date().toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
});

// Methods
const loadDashboardData = async () => {
    try {
        loading.value = true;
        error.value = null;

        console.log('🔄 Loading admin dashboard data...');

        // Create timeout wrapper for API calls
        const timeoutPromise = new Promise((_, reject) => 
            setTimeout(() => reject(new Error('Request timeout')), 10000)
        );

        // Load data with timeout protection
        const dataPromises = [
            Promise.race([api.get(`${API_BASE_URL}/students`), timeoutPromise]),
            Promise.race([api.get(`${API_BASE_URL}/teachers`), timeoutPromise]),
            Promise.race([api.get(`${API_BASE_URL}/sections`), timeoutPromise])
        ];

        const [studentsResponse, teachersResponse, sectionsResponse] = await Promise.all(dataPromises);

        // Set the counts
        totalStudents.value = studentsResponse.data?.length || 0;
        totalTeachers.value = teachersResponse.data?.length || 0;
        totalSections.value = sectionsResponse.data?.length || 0;

        console.log('✅ Dashboard data loaded:', {
            students: totalStudents.value,
            teachers: totalTeachers.value,
            sections: totalSections.value
        });
    } catch (err) {
        console.error('❌ Error loading dashboard data:', err);
        error.value = `Failed to load dashboard data: ${err.message}. Please try again.`;
        
        // Set default values on error
        totalStudents.value = 0;
        totalTeachers.value = 0;
        totalSections.value = 0;
    } finally {
        loading.value = false;
        console.log('📊 Admin dashboard loading completed');
    }
};

// Lifecycle
onMounted(() => {
    loadDashboardData();
});
</script>

<style scoped>
.admin-dashboard {
    height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1rem;
    overflow: hidden;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.loading-overlay {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60vh;
}

.loading-content {
    text-align: center;
    background: white;
    padding: 2rem;
    border-radius: 1rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.error-card {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 0.75rem;
    padding: 1rem;
    margin-bottom: 1rem;
}

.retry-btn {
    background: #dc2626;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    font-size: 0.875rem;
    font-weight: 500;
}

.retry-btn:hover {
    background: #b91c1c;
}

.dashboard-header {
    background: white;
    border-radius: 1rem;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    flex-shrink: 0;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    width: 3rem;
    height: 3rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.header-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
}

.header-subtitle {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0.25rem 0;
}

.school-year {
    display: flex;
    align-items: center;
    color: #374151;
    font-weight: 500;
    margin-top: 0.5rem;
    font-size: 0.875rem;
}

.year-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
    margin-left: 0.5rem;
    font-size: 0.75rem;
}

.last-updated {
    color: #6b7280;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
}

.dashboard-stats {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 0.75rem;
    flex: 1;
}

.stat-card {
    background: white;
    border-radius: 1rem;
    padding: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition:
        transform 0.2s,
        box-shadow 0.2s;
    height: 140px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stat-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.stat-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    color: white;
    flex-shrink: 0;
}

.students-card .stat-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.teachers-card .stat-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
}

.sections-card .stat-icon {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.school-year-card .stat-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.stat-info {
    flex: 1;
}

.stat-title {
    font-size: 1rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
    line-height: 1.2;
}

.stat-subtitle {
    color: #6b7280;
    font-size: 0.75rem;
    margin: 0;
    line-height: 1.2;
}

.stat-value {
    text-align: center;
    margin: 1rem 0;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
}

.stat-label {
    color: #6b7280;
    font-size: 0.75rem;
    font-weight: 500;
    margin-top: 0.25rem;
}

.stat-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    padding-top: 0.75rem;
    border-top: 1px solid #f3f4f6;
    font-size: 0.75rem;
    font-weight: 500;
}



@media (max-width: 768px) {
    .admin-dashboard {
        padding: 0.5rem;
        height: 100vh;
    }

    .header-content {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }

    .header-left {
        flex-direction: column;
        gap: 0.5rem;
    }

    .stats-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .actions-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .stat-card {
        height: 120px;
        padding: 0.75rem;
    }

    .dashboard-header {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .quick-actions {
        padding: 0.75rem;
        margin-top: 0.5rem;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .actions-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }

    .header-title {
        font-size: 1.25rem;
    }

    .stat-number {
        font-size: 1.5rem;
    }

    .stat-card {
        height: 100px;
        padding: 0.5rem;
    }
}
</style>
