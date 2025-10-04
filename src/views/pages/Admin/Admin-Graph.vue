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

            <!-- Quick Actions Section -->
            <div class="quick-actions">
                <h2 class="section-title">Quick Actions</h2>
                <div class="actions-grid">
                    <router-link to="/admin-teacher" class="action-card">
                        <div class="action-icon">
                            <i class="pi pi-users"></i>
                        </div>
                        <div class="action-content">
                            <h3 class="action-title">Manage Teachers</h3>
                            <p class="action-description">Add, edit, and assign teachers</p>
                        </div>
                        <i class="pi pi-arrow-right action-arrow"></i>
                    </router-link>

                    <router-link to="/admin-student" class="action-card">
                        <div class="action-icon">
                            <i class="pi pi-user"></i>
                        </div>
                        <div class="action-content">
                            <h3 class="action-title">Manage Students</h3>
                            <p class="action-description">Student enrollment and records</p>
                        </div>
                        <i class="pi pi-arrow-right action-arrow"></i>
                    </router-link>

                    <router-link to="/curriculum" class="action-card">
                        <div class="action-icon">
                            <i class="pi pi-book"></i>
                        </div>
                        <div class="action-content">
                            <h3 class="action-title">Curriculum</h3>
                            <p class="action-description">Manage curriculum and subjects</p>
                        </div>
                        <i class="pi pi-arrow-right action-arrow"></i>
                    </router-link>

                    <router-link to="/admin-collected-reports" class="action-card">
                        <div class="action-icon">
                            <i class="pi pi-chart-bar"></i>
                        </div>
                        <div class="action-content">
                            <h3 class="action-title">Reports</h3>
                            <p class="action-description">View system reports</p>
                        </div>
                        <i class="pi pi-arrow-right action-arrow"></i>
                    </router-link>
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

        // Load all data in parallel
        const [studentsResponse, teachersResponse, sectionsResponse] = await Promise.all([api.get(`${API_BASE_URL}/students`), api.get(`${API_BASE_URL}/teachers`), api.get(`${API_BASE_URL}/sections`)]);

        // Set the counts
        totalStudents.value = studentsResponse.data?.length || 0;
        totalTeachers.value = teachersResponse.data?.length || 0;
        totalSections.value = sectionsResponse.data?.length || 0;

        console.log('Dashboard data loaded:', {
            students: totalStudents.value,
            teachers: totalTeachers.value,
            sections: totalSections.value
        });
    } catch (err) {
        console.error('Error loading dashboard data:', err);
        error.value = 'Failed to load dashboard data. Please try again.';
    } finally {
        loading.value = false;
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

/* Quick Actions Section */
.quick-actions {
    background: white;
    border-radius: 1rem;
    padding: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    margin-top: 0.75rem;
    flex-shrink: 0;
}

.section-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.75rem 0;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.5rem;
}

.action-card {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    text-decoration: none;
    color: inherit;
    transition: all 0.2s;
    background: #f9fafb;
}

.action-card:hover {
    border-color: #667eea;
    background: #f0f4ff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.action-icon {
    width: 2rem;
    height: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 0.375rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.action-content {
    flex: 1;
}

.action-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.125rem 0;
}

.action-description {
    color: #6b7280;
    font-size: 0.75rem;
    margin: 0;
}

.action-arrow {
    color: #9ca3af;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.action-card:hover .action-arrow {
    color: #667eea;
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
