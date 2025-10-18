<template>
    <div class="attendance-insights">
        <div class="insights-header">
            <h3 class="insights-title">
                <i class="pi pi-lightbulb"></i>
                Attendance Insights
            </h3>
            <small class="insights-subtitle">All Students</small>
        </div>

        <!-- Risk cards removed - now shown on main dashboard -->

        <!-- Risk Level Legend -->
        <div class="risk-legend" v-if="studentsNeedingAttention && studentsNeedingAttention.length > 0">
            <div class="legend-header">
                <i class="pi pi-info-circle"></i>
                <span>Risk Levels:</span>
            </div>
            <div class="legend-badges">
                <div class="legend-badge-item critical">
                    <i class="pi pi-exclamation-circle"></i>
                    <span class="badge-label">Critical</span>
                    <span class="badge-desc">5+ absences</span>
                </div>
                <div class="legend-badge-item high">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span class="badge-label">High Risk</span>
                    <span class="badge-desc">3-4 absences</span>
                </div>
                <div class="legend-badge-item low">
                    <i class="pi pi-info-circle"></i>
                    <span class="badge-label">Low Risk</span>
                    <span class="badge-desc">1-2 absences</span>
                </div>
            </div>
        </div>

        <!-- Students Requiring Attention - Clickable Cards -->
        <div class="student-plans-section" v-if="studentsNeedingAttention && studentsNeedingAttention.length > 0">
            <h4 class="section-title">
                <i class="pi pi-users"></i>
                Students Requiring Attention
            </h4>

            <div class="risk-cards-grid">
                <!-- Critical Card -->
                <div class="risk-card critical" v-if="groupedStudents.critical && groupedStudents.critical.length > 0" @click="openRiskDialog('critical')">
                    <div class="risk-card-icon">
                        <i class="pi pi-exclamation-circle"></i>
                    </div>
                    <div class="risk-card-content">
                        <div class="risk-card-label">Critical</div>
                        <div class="risk-card-count">{{ groupedStudents.critical.length }}</div>
                        <div class="risk-card-desc">students</div>
                    </div>
                </div>

                <!-- High Risk Card -->
                <div class="risk-card high" v-if="groupedStudents.high && groupedStudents.high.length > 0" @click="openRiskDialog('high')">
                    <div class="risk-card-icon">
                        <i class="pi pi-exclamation-triangle"></i>
                    </div>
                    <div class="risk-card-content">
                        <div class="risk-card-label">High Risk</div>
                        <div class="risk-card-count">{{ groupedStudents.high.length }}</div>
                        <div class="risk-card-desc">students</div>
                    </div>
                </div>

                <!-- Low Risk Card -->
                <div class="risk-card low" v-if="groupedStudents.low && groupedStudents.low.length > 0" @click="openRiskDialog('low')">
                    <div class="risk-card-icon">
                        <i class="pi pi-info-circle"></i>
                    </div>
                    <div class="risk-card-content">
                        <div class="risk-card-label">Low Risk</div>
                        <div class="risk-card-count">{{ groupedStudents.low.length }}</div>
                        <div class="risk-card-desc">students</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Students Dialog -->
        <Dialog v-model:visible="showRiskDialog" modal :header="getRiskDialogTitle()" style="width: 50rem" :dismissableMask="true">
            <!-- Tab Navigation -->
            <div class="risk-tabs">
                <div class="risk-tab" :class="{ active: activeRiskTab === 'critical', disabled: !groupedStudents.critical || groupedStudents.critical.length === 0 }" @click="switchRiskTab('critical')" v-if="groupedStudents.critical && groupedStudents.critical.length > 0">
                    <i class="pi pi-exclamation-circle"></i>
                    <span>Critical ({{ groupedStudents.critical.length }})</span>
                </div>
                <div class="risk-tab" :class="{ active: activeRiskTab === 'high', disabled: !groupedStudents.high || groupedStudents.high.length === 0 }" @click="switchRiskTab('high')" v-if="groupedStudents.high && groupedStudents.high.length > 0">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>High Risk ({{ groupedStudents.high.length }})</span>
                </div>
                <div class="risk-tab" :class="{ active: activeRiskTab === 'low', disabled: !groupedStudents.low || groupedStudents.low.length === 0 }" @click="switchRiskTab('low')" v-if="groupedStudents.low && groupedStudents.low.length > 0">
                    <i class="pi pi-info-circle"></i>
                    <span>Low Risk ({{ groupedStudents.low.length }})</span>
                </div>
            </div>

            <!-- Students List -->
            <div class="risk-dialog-content">
                <div class="students-list">
                    <div v-for="student in getCurrentRiskStudents()" :key="student.student_id || student.id" class="student-item" :class="activeRiskTab" @click="viewStudentProfile(student)">
                        <div class="student-item-icon">
                            <i class="pi pi-user"></i>
                        </div>
                        <div class="student-item-info">
                            <div class="student-item-name">{{ student.first_name }} {{ student.last_name }}</div>
                            <div class="student-item-stats">
                                <span class="stat-badge"><i class="pi pi-calendar"></i> {{ student.total_absences || 0 }} total</span>
                                <span class="stat-badge"><i class="pi pi-clock"></i> {{ student.recent_absences || 0 }} recent</span>
                                <span class="stat-badge" v-if="(student.consecutive_absences || 0) > 0"><i class="pi pi-exclamation-triangle"></i> {{ student.consecutive_absences }} consecutive</span>
                            </div>
                        </div>
                        <div class="student-item-action">
                            <i class="pi pi-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <div v-if="getCurrentRiskStudents().length === 0" class="empty-state">
                    <i class="pi pi-check-circle"></i>
                    <p>No students in this risk category</p>
                </div>
            </div>
        </Dialog>

        <!-- Success Stories -->
        <div class="success-section" v-if="improvingStudents.length > 0">
            <h4 class="section-title">
                <i class="pi pi-chart-line"></i>
                Improving Students
            </h4>
            <div class="success-cards">
                <div v-for="student in improvingStudents" :key="student.student_id" class="success-card">
                    <div class="student-name">{{ student.first_name }} {{ student.last_name }}</div>
                    <div class="improvement-text">{{ student.improvementNote }}</div>
                </div>
            </div>
        </div>

        <!-- Create Individual Plan Dialog -->
        <Dialog v-model:visible="showPlanDialog" modal header="Create Individual Attendance Plan" style="width: 50rem">
            <div class="plan-form">
                <div class="field">
                    <label for="targetAttendance">Target Attendance Percentage</label>
                    <InputNumber id="targetAttendance" v-model="newPlan.targetAttendance" :min="70" :max="100" suffix="%" />
                </div>
                <div class="field">
                    <label for="planDescription">Plan Description</label>
                    <Textarea id="planDescription" v-model="newPlan.description" rows="4" placeholder="Describe the specific interventions and support strategies..." />
                </div>
                <div class="field">
                    <label for="reviewDate">Review Date</label>
                    <Calendar id="reviewDate" v-model="newPlan.reviewDate" :minDate="new Date()" dateFormat="mm/dd/yy" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="showPlanDialog = false" />
                <Button label="Create Plan" icon="pi pi-check" @click="savePlan" />
            </template>
        </Dialog>

        <!-- Progress Tracking Dialog -->
        <Dialog v-model:visible="showProgressDialog" modal :header="`Progress Tracking - ${selectedStudentForProgress?.first_name} ${selectedStudentForProgress?.last_name}`" style="width: 60rem">
            <div class="progress-content" ref="progressContentRef" @scroll="handleProgressScroll">
                <!-- Weekly Attendance Chart -->
                <div class="progress-section">
                    <div class="section-header-with-picker">
                        <h5><i class="pi pi-chart-bar"></i> Weekly Attendance Overview</h5>
                        <div class="filters-container">
                            <div class="filter-group">
                                <label for="subject-filter" style="margin-right: 8px; font-weight: 500">Subject:</label>
                                <Dropdown
                                    id="subject-filter"
                                    v-model="selectedSubjectFilter"
                                    :options="availableSubjects"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="All Subjects"
                                    @change="onSubjectFilterChange"
                                    showClear
                                    style="min-width: 180px"
                                />
                            </div>
                            <div class="filter-group">
                                <label for="month-picker" style="margin-right: 8px; font-weight: 500">Month:</label>
                                <Calendar id="month-picker" v-model="selectedMonth" view="month" dateFormat="MM yy" @date-select="onMonthChange" :maxDate="new Date()" showIcon placeholder="Select month" />
                            </div>
                        </div>
                    </div>
                    <div class="weekly-grid">
                        <div v-for="week in progressData.weeklyAttendance" :key="week.week" class="week-card">
                            <div class="week-header">
                                {{ week.week }}
                                <div class="week-meta" v-if="week.total_days || week.total_subjects">
                                    <span class="meta-badge">{{ week.total_days }} days</span>
                                    <span class="meta-badge" v-if="week.total_subjects > 1">{{ week.total_subjects }} subjects</span>
                                </div>
                            </div>
                            <div class="attendance-stats">
                                <div class="stat-item present">
                                    <span class="stat-label">Present:</span>
                                    <span class="stat-value">{{ week.present }}</span>
                                </div>
                                <div class="stat-item absent">
                                    <span class="stat-label">Absent:</span>
                                    <span class="stat-value">{{ week.absent }}</span>
                                </div>
                                <div class="stat-item late">
                                    <span class="stat-label">Late:</span>
                                    <span class="stat-value">{{ week.late }}</span>
                                </div>
                            </div>

                            <!-- Subject Breakdown Tooltip -->
                            <div v-if="week.subject_breakdown && Object.keys(week.subject_breakdown).length > 0" class="subject-breakdown-info">
                                <small class="breakdown-label">📚 By Subject:</small>
                                <div class="subject-list">
                                    <div v-for="(stats, subjectKey) in week.subject_breakdown" :key="subjectKey" class="subject-item">
                                        <strong>{{ stats.subject_name || subjectKey }}:</strong>
                                        <span class="text-green-600">{{ stats.present }}P</span>
                                        <span class="text-red-600">{{ stats.absent }}A</span>
                                        <span class="text-yellow-600" v-if="stats.late > 0">{{ stats.late }}L</span>
                                    </div>
                                </div>
                            </div>

                            <div class="percentage-bar">
                                <div class="percentage-fill" :style="{ width: week.percentage + '%' }"></div>
                                <span class="percentage-text">{{ week.percentage }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Scroll Indicator (Circular Floating Button) -->
                <div class="scroll-indicator-circle" v-show="showScrollIndicator" @click="scrollDown">
                    <i class="pi pi-chevron-down"></i>
                </div>

                <!-- Improvements Section -->
                <div class="progress-section">
                    <h5><i class="pi pi-check-circle"></i> Positive Improvements</h5>
                    <ul class="improvement-list">
                        <li v-for="improvement in progressData.improvements" :key="improvement" class="improvement-item">
                            <i class="pi pi-check text-green-600"></i>
                            {{ improvement }}
                        </li>
                    </ul>
                </div>

                <!-- Concerns Section -->
                <div class="progress-section">
                    <h5><i class="pi pi-exclamation-triangle"></i> Areas of Concern</h5>
                    <ul class="concern-list">
                        <li v-for="concern in progressData.concerns" :key="concern" class="concern-item">
                            <i class="pi pi-exclamation-triangle text-orange-500"></i>
                            {{ concern }}
                        </li>
                    </ul>
                </div>

                <!-- Next Steps Section -->
                <div class="progress-section">
                    <h5><i class="pi pi-arrow-right"></i> Recommended Next Steps</h5>
                    <ul class="next-steps-list">
                        <li v-for="step in progressData.nextSteps" :key="step" class="next-step-item">
                            <i class="pi pi-arrow-circle-right text-blue-600"></i>
                            {{ step }}
                        </li>
                    </ul>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import SmartAnalyticsService from '@/services/SmartAnalyticsService';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    students: {
        type: Array,
        default: () => []
    },
    selectedSubject: {
        type: Object,
        default: null
    },
    currentTeacher: {
        type: Object,
        default: null
    }
});

// Dialog state
const showPlanDialog = ref(false);
const selectedStudentForPlan = ref(null);
const newPlan = ref({
    goals: '',
    strategies: '',
    timeline: '',
    checkpoints: []
});

const showProgressDialog = ref(false);

// Watch for progress dialog closing to reopen risk dialog if it was open
watch(showProgressDialog, (newVal, oldVal) => {
    if (oldVal === true && newVal === false && wasRiskDialogOpen.value) {
        // Progress dialog just closed and risk dialog was open before
        showRiskDialog.value = true;
        wasRiskDialogOpen.value = false;
    }
});
const selectedStudentForProgress = ref(null);
const selectedMonth = ref(new Date()); // Default to current month
const selectedSubjectFilter = ref(null); // Default to all subjects
const availableSubjects = ref([]);
const progressData = ref({
    weeklyAttendance: [],
    monthlyTrends: [],
    improvements: [],
    concerns: [],
    nextSteps: []
});

// Helper functions (defined first to be used in computed properties)
function getRiskLevel(student) {
    // Use ONLY recent_absences for consistency with dashboard cards
    const recentAbsences = student.recent_absences || 0;

    if (recentAbsences >= 5) return 'critical';
    if (recentAbsences >= 3) return 'high';
    if (recentAbsences >= 1) return 'low';
    return 'normal';
}

function getRiskFactors(student) {
    const factors = [];

    // Academic performance factor (simulated)
    if (student.total_absences >= 8) {
        factors.push('High Total Absences');
    }

    // Consecutive absences
    if ((student.consecutive_absences || 0) >= 3) {
        factors.push('Consecutive Absences');
    }

    // Household income factor (from enrollment data)
    if (student.household_income === 'Below 10k') {
        factors.push('Economic Risk');
    }

    // Health/medical pattern (simulated based on absence pattern)
    if (student.recent_absences > student.total_absences * 0.6) {
        factors.push('Recent Pattern Change');
    }

    return factors;
}

function getExistingPlan(student) {
    // Simulate checking for existing plans
    // In real implementation, this would query the database
    return null;
}

function calculateConsecutiveAbsences(student) {
    // Use real consecutive absence data if available, otherwise return 0
    // This should come from actual attendance records, not fake data
    return student.consecutive_absences || 0;
}

// Computed properties for student categorization
const criticalStudents = computed(() => {
    return props.students.filter((student) => student.recent_absences >= 5);
});

const warningStudents = computed(() => {
    return props.students.filter((student) => student.recent_absences >= 3 && student.recent_absences < 5);
});

const consecutiveAbsenceStudents = computed(() => {
    return props.students.filter((student) => (student.consecutive_absences || 0) >= 3);
});

const studentsNeedingAttention = computed(() => {
    if (!props.students || !Array.isArray(props.students)) {
        return [];
    }

    const filtered = props.students
        .filter((student) => {
            const totalAbsences = student.total_absences || 0;
            const recentAbsences = student.recent_absences || 0;
            const consecutiveAbsences = student.consecutive_absences || 0;

            // More inclusive filtering - anyone with any absences needs attention
            return totalAbsences >= 1 || recentAbsences >= 1 || consecutiveAbsences >= 1;
        })
        .map((student) => ({
            ...student,
            riskLevel: getRiskLevel(student),
            riskFactors: getRiskFactors(student),
            attendancePlan: getExistingPlan(student),
            // Only show consecutive absences if there are actual recent sessions (not old seeded data)
            consecutive_absences: student.recent_absences > 0 ? student.consecutive_absences || 0 : 0
        }))
        .sort((a, b) => (b.recent_absences || 0) - (a.recent_absences || 0));

    return filtered;
});

const improvingStudents = computed(() => {
    // Students who had high absences but are now improving
    return props.students
        .filter((student) => {
            const hadIssues = student.total_absences >= 5;
            const improving = student.recent_absences < 2;
            return hadIssues && improving;
        })
        .map((student) => ({
            ...student,
            improvementNote: `Reduced from ${student.total_absences} total absences to only ${student.recent_absences} recent absences`
        }));
});

function getRiskIcon(riskLevel) {
    const icons = {
        critical: 'pi pi-exclamation-circle',
        high: 'pi pi-exclamation-triangle',
        low: 'pi pi-info-circle',
        warning: 'pi pi-exclamation-triangle',
        normal: 'pi pi-check-circle'
    };
    return icons[riskLevel] || 'pi pi-info-circle';
}

function getFactorClass(factor) {
    const classMap = {
        'High Total Absences': 'factor-academic',
        'Consecutive Absences': 'factor-consecutive',
        'Economic Risk': 'factor-economic',
        'Recent Pattern Change': 'factor-health'
    };
    return classMap[factor] || 'factor-default';
}

function getRecommendedActions(student) {
    const actions = [];

    if (student.riskLevel === 'critical') {
        actions.push({
            id: 'schedule-meeting',
            label: 'Schedule Student Meeting',
            icon: 'pi pi-calendar-plus',
            class: 'p-button-warning',
            priority: 'high'
        });
    }

    if (student.riskFactors?.includes('Economic Risk')) {
        actions.push({
            id: 'refer-counselor',
            label: 'Refer to Counselor',
            icon: 'pi pi-heart',
            class: 'p-button-help',
            priority: 'medium'
        });
    }

    actions.push({
        id: 'track-progress',
        label: 'Monitor Progress',
        icon: 'pi pi-chart-line',
        class: 'p-button-secondary',
        priority: 'low'
    });

    return actions.sort((a, b) => {
        const priorityOrder = { high: 3, medium: 2, low: 1 };
        return priorityOrder[b.priority] - priorityOrder[a.priority];
    });
}

function executeAction(action, student) {
    switch (action.id) {
        case 'send-warning':
            sendAttendanceWarning(student);
            break;
        case 'schedule-meeting':
            scheduleMeeting(student);
            break;
        case 'refer-counselor':
            referToCounselor(student);
            break;
        case 'track-progress':
            trackProgress(student);
            break;
    }
}

function openPlanDialog(student) {
    selectedStudentForPlan.value = student;
    newPlan.value = {
        targetAttendance: 85,
        description: generatePlanDescription(student),
        reviewDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000) // 30 days from now
    };
    showPlanDialog.value = true;
}

function generatePlanDescription(student) {
    let description = `Individual attendance improvement plan for ${student.first_name} ${student.last_name}.\n\n`;

    if (student.riskFactors?.includes('Economic Risk')) {
        description += '• Provide flexible scheduling options\n';
        description += '• Connect family with school support resources\n';
    }

    if (student.riskFactors?.includes('Consecutive Absences')) {
        description += '• Daily check-ins with student\n';
        description += '• Identify and address barriers to attendance\n';
    }

    description += '• Weekly attendance review meetings\n';
    description += '• Positive reinforcement for improved attendance\n';
    description += '• Academic support to catch up on missed work';

    return description;
}

function sendAttendanceWarning(student) {
    // Simulate sending warning
    console.log(`Sending attendance warning to ${student.first_name} ${student.last_name}`);
    // In real implementation, this would create a warning record
}

function scheduleMeeting(student) {
    // Simulate scheduling meeting
    console.log(`Scheduling meeting with ${student.first_name} ${student.last_name}`);
    // In real implementation, this would open calendar integration
}

function referToCounselor(student) {
    // Simulate counselor referral
    console.log(`Referring ${student.first_name} ${student.last_name} to school counselor`);
    // In real implementation, this would create a referral record
}

function trackProgress(student) {
    // Remember if risk dialog was open
    if (showRiskDialog.value) {
        wasRiskDialogOpen.value = true;
    }
    
    selectedStudentForProgress.value = student;
    selectedMonth.value = new Date(); // Reset to current month when opening dialog
    selectedSubjectFilter.value = null; // Reset subject filter to show all subjects
    
    // Load all teacher subjects from localStorage
    loadTeacherSubjects();
    
    showProgressDialog.value = true;
    showScrollIndicator.value = true; // Reset scroll indicator
    loadProgressData(student);
}

function loadTeacherSubjects() {
    try {
        const teacherData = JSON.parse(localStorage.getItem('teacher_data') || '{}');
        const assignments = teacherData.assignments || [];
        
        // Extract all unique subjects from teacher assignments
        const subjectsMap = new Map();
        
        assignments.forEach(assignment => {
            // Handle homeroom (no subject_id)
            if (!assignment.subject_id && assignment.subject_name === 'Homeroom') {
                subjectsMap.set('homeroom', {
                    id: null,
                    name: 'Homeroom'
                });
            }
            // Handle regular subjects
            else if (assignment.subject_id && assignment.subject_name) {
                subjectsMap.set(assignment.subject_id, {
                    id: assignment.subject_id,
                    name: assignment.subject_name
                });
            }
        });
        
        availableSubjects.value = Array.from(subjectsMap.values());
        console.log('📚 Loaded teacher subjects:', availableSubjects.value);
    } catch (error) {
        console.error('Error loading teacher subjects:', error);
        availableSubjects.value = [];
    }
}

function onMonthChange() {
    console.log('📅 Month changed to:', selectedMonth.value);
    if (selectedStudentForProgress.value) {
        loadProgressData(selectedStudentForProgress.value);
    }
}

function onSubjectFilterChange() {
    console.log('📚 Subject filter changed to:', selectedSubjectFilter.value);
    if (selectedStudentForProgress.value) {
        loadProgressData(selectedStudentForProgress.value);
    }
}

async function loadProgressData(student) {
    try {
        console.log('Loading progress data for student:', student);
        console.log('Student ID:', student.id);
        console.log('Available student keys:', Object.keys(student));

        // Try different possible ID fields based on common patterns
        const studentId = student.id || student.student_id || student.studentId || student.user_id || student.pk || student.ID;

        if (!studentId) {
            console.error('No valid student ID found in student object:', student);
            progressData.value = {
                weeklyAttendance: [],
                monthlyTrends: [],
                improvements: ['Unable to load data - no student ID found'],
                concerns: ['Missing student identification'],
                nextSteps: ['Please contact support']
            };
            return;
        }

        console.log('🔥 Fetching REAL weekly attendance from database for student:', studentId);
        console.log('📅 Selected month:', selectedMonth.value);
        console.log('📚 Selected subject filter:', selectedSubjectFilter.value);
        console.log('👤 Teacher ID:', props.currentTeacher?.id);

        // Fetch REAL weekly attendance records from database for selected month, subject, AND teacher!
        const weeklyResponse = await SmartAnalyticsService.getStudentWeeklyAttendance(
            studentId,
            selectedMonth.value,
            selectedSubjectFilter.value,
            props.currentTeacher?.id // CRITICAL: Only show this teacher's sessions!
        );

        console.log('📊 Real Weekly Attendance Response:', weeklyResponse);

        if (weeklyResponse.success && weeklyResponse.data) {
            const weeklyAttendance = weeklyResponse.data.weekly_attendance;
            console.log('✅ Loaded real weekly attendance:', weeklyAttendance);

            // Load available subjects from the breakdown (with real database IDs)
            // BUT only show subjects that belong to the current teacher (from props)
            const subjectsMap = new Map();

            // If we have a selected subject from props, use only that
            if (props.selectedSubject && props.selectedSubject.id) {
                subjectsMap.set(props.selectedSubject.id, {
                    id: props.selectedSubject.id,
                    name: props.selectedSubject.name
                });
            }

            // Also collect subjects from the breakdown, but they should match teacher's subjects
            weeklyAttendance.forEach((week) => {
                if (week.subject_breakdown) {
                    Object.values(week.subject_breakdown).forEach((subjectData) => {
                        if (subjectData.subject_id && subjectData.subject_name) {
                            subjectsMap.set(subjectData.subject_id, {
                                id: subjectData.subject_id,
                                name: subjectData.subject_name
                            });
                        }
                    });
                }
            });

            availableSubjects.value = Array.from(subjectsMap.values());
            console.log('📚 Available subjects for current teacher:', availableSubjects.value);

            // Format weekly data for display
            const weeklyData = weeklyAttendance.map((week) => ({
                week: week.week,
                present: week.present,
                absent: week.absent,
                late: week.late,
                percentage: week.percentage,
                total_days: week.total_days,
                total_subjects: week.total_subjects,
                subject_breakdown: week.subject_breakdown
            }));

            // Calculate metrics from real data (prioritize absences!)
            const totalAbsences = weeklyData.reduce((sum, week) => sum + week.absent, 0);
            const recentAbsences = weeklyData.slice(-2).reduce((sum, week) => sum + week.absent, 0); // Last 2 weeks
            const consecutiveAbsences = student.consecutive_absences || 0;

            console.log('📈 Real attendance metrics:', {
                totalAbsences,
                recentAbsences,
                consecutiveAbsences,
                weeklyData
            });

            // 🤖 CLIENT-SIDE SMART ANALYTICS ENGINE
            console.log('🧠 Generating Smart Analytics insights...');
            
            // Calculate comprehensive metrics
            const totalSessions = weeklyData.reduce((sum, week) => sum + (week.total_days || 0), 0);
            const totalPresent = weeklyData.reduce((sum, week) => sum + week.present, 0);
            const totalLate = weeklyData.reduce((sum, week) => sum + week.late, 0);
            const attendanceRate = totalSessions > 0 ? Math.round((totalPresent / totalSessions) * 100) : 0;
            
            const improvements = [];
            const concerns = [];
            const nextSteps = [];
            
            console.log('📊 Metrics:', { totalAbsences, recentAbsences, totalSessions, attendanceRate, totalLate });
            
            // === POSITIVE IMPROVEMENTS ===
            if (totalAbsences === 0 && totalSessions > 0) {
                improvements.push('🏆 Perfect attendance - no absences recorded!');
            } else if (attendanceRate >= 95 && totalSessions >= 5) {
                improvements.push(`📈 Excellent attendance: ${attendanceRate}%`);
            } else if (attendanceRate >= 85 && totalSessions >= 5) {
                improvements.push(`✅ Good attendance consistency: ${attendanceRate}%`);
            }
            
            if (totalLate === 0 && totalSessions > 0) {
                improvements.push('⏰ Always punctual - no tardiness');
            }
            
            // === AREAS OF CONCERN ===
            // Determine risk level based on recent_absences (matches dashboard grouping)
            const studentRiskLevel = selectedStudentForProgress.value?.recent_absences >= 5 ? 'critical' : 
                                    selectedStudentForProgress.value?.recent_absences >= 3 ? 'high' : 'low';
            
            if (totalAbsences >= 18) {
                concerns.push(`🚨 CRITICAL RISK: ${totalAbsences} total absences exceeds 18-day limit`);
                concerns.push('⚠️ Immediate intervention required - risk of academic failure');
            } else if (totalAbsences >= 10) {
                concerns.push(`🚨 CRITICAL RISK: ${totalAbsences} total absences (approaching 18-day limit)`);
                concerns.push('⚠️ Urgent attention needed');
            } else if (totalAbsences >= 5) {
                if (studentRiskLevel === 'critical') {
                    concerns.push(`🚨 CRITICAL RISK: ${totalAbsences} total absences with ${selectedStudentForProgress.value?.recent_absences} recent absences`);
                } else {
                    concerns.push(`⚠️ AT RISK: ${totalAbsences} total absences - monitor closely`);
                }
            } else if (totalAbsences >= 3) {
                concerns.push(`📊 LOW RISK: ${totalAbsences} absences - early monitoring recommended`);
            }
            
            if (attendanceRate < 70 && totalSessions >= 5) {
                concerns.push(`📉 Very low attendance: ${attendanceRate}% (below 70%)`);
            } else if (attendanceRate < 80 && totalSessions >= 5) {
                concerns.push(`📊 Low attendance: ${attendanceRate}% (below 80%)`);
            }
            
            if (consecutiveAbsences >= 5) {
                concerns.push(`🚨 Extended absence: ${consecutiveAbsences} consecutive days`);
            } else if (consecutiveAbsences >= 3) {
                concerns.push(`⚠️ Multiple consecutive absences: ${consecutiveAbsences} days`);
            }
            
            if (totalLate >= 8) {
                concerns.push(`⏰ Chronic tardiness: ${totalLate} late arrivals`);
            } else if (totalLate >= 5) {
                concerns.push(`⏰ Frequent tardiness: ${totalLate} late arrivals`);
            }
            
            // === RECOMMENDED NEXT STEPS ===
            if (totalAbsences >= 18) {
                nextSteps.push('🚨 Schedule IMMEDIATE parent conference (within 24 hours)');
                nextSteps.push('📋 Implement daily check-in system');
                nextSteps.push('📄 Create formal attendance contract');
                nextSteps.push('👥 Refer to school counselor');
            } else if (totalAbsences >= 10) {
                nextSteps.push('⚠️ Contact parents within 3 days');
                nextSteps.push('🔍 Investigate barriers (health, transportation, family)');
                nextSteps.push('📅 Set up weekly attendance monitoring');
            } else if (totalAbsences >= 5) {
                nextSteps.push('📞 Contact parents within 1 week');
                nextSteps.push('📊 Monitor patterns for next 2 weeks');
            } else if (attendanceRate < 80 && totalSessions >= 5) {
                nextSteps.push('👨‍👩‍👧 Schedule parent meeting');
            }
            
            if (consecutiveAbsences >= 3) {
                nextSteps.push('🏥 Request medical documentation if health-related');
                nextSteps.push('📚 Provide makeup work and catch-up support');
            }
            
            if (totalLate >= 5) {
                nextSteps.push('🌅 Discuss morning routine with family');
            }
            
            if (attendanceRate >= 90 && totalSessions >= 5) {
                nextSteps.push('🎉 Acknowledge positive attendance');
            }
            
            console.log('✅ Analytics generated:', { improvements: improvements.length, concerns: concerns.length, nextSteps: nextSteps.length });

            // Ensure we always have content - but make it smart!
            if (improvements.length === 0) {
                if (totalAbsences === 0 && weeklyData.length > 0) {
                    improvements.push('✅ Excellent attendance record maintained');
                } else if (weeklyData.length === 0) {
                    improvements.push('📋 No attendance data available for analysis');
                } else {
                    improvements.push('🔄 Working on improving attendance consistency');
                }
            }

            if (concerns.length === 0) {
                if (weeklyData.length === 0) {
                    concerns.push('📊 Insufficient data - waiting for attendance records');
                } else {
                    concerns.push('✅ No significant concerns at this time');
                }
            }

            if (nextSteps.length === 0) {
                if (weeklyData.length === 0) {
                    nextSteps.push('📝 Begin recording attendance once classes start');
                } else {
                    nextSteps.push('👀 Continue regular monitoring');
                }
            }

            // Map real analytics data to progress dialog format
            progressData.value = {
                weeklyAttendance: weeklyData, // REAL DATA from database!
                monthlyTrends: [],
                improvements: improvements,
                concerns: concerns,
                nextSteps: nextSteps
            };

            console.log('✅ Progress data loaded with REAL weekly attendance!', progressData.value);
        } else {
            // Fallback to empty state if no analytics available
            progressData.value = {
                weeklyAttendance: [],
                monthlyTrends: [],
                improvements: ['No analytics data available yet'],
                concerns: ['Insufficient data for analysis'],
                nextSteps: ['Collect more attendance data for analysis']
            };
        }
    } catch (error) {
        console.error('Error loading student analytics:', error);

        progressData.value = {
            weeklyAttendance: [],
            monthlyTrends: [],
            improvements: ['Error loading data'],
            concerns: ['Unable to retrieve attendance information'],
            nextSteps: ['Please try again or contact support']
        };
    }
}

function savePlan() {
    // Simulate saving attendance plan
    console.log('Saving attendance plan:', {
        student: selectedStudentForPlan.value,
        plan: newPlan.value
    });
    showPlanDialog.value = false;
}

function formatDate(date) {
    if (!date) return '';
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(new Date(date));
}

function printAttendanceReport() {
    if (!selectedStudentForProgress.value) return;

    const student = selectedStudentForProgress.value;
    const currentDate = new Date().toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Create a new window for the report
    const reportWindow = window.open('', '_blank', 'width=800,height=1000,scrollbars=yes');

    if (!reportWindow) {
        alert('Unable to open print window. Please allow popups for this site.');
        return;
    }

    // Generate the HTML content for the report
    const reportHTML = generateAttendanceReportHTML(student, currentDate);

    // Write the HTML to the new window
    reportWindow.document.write(reportHTML);
    reportWindow.document.close();

    // Wait for content to load, then print
    reportWindow.onload = () => {
        setTimeout(() => {
            reportWindow.print();
        }, 500);
    };
}

function generateAttendanceReportHTML(student, currentDate) {
    const schoolYear = getCurrentSchoolYear();
    const overallAttendanceRate = calculateOverallAttendanceRate();

    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance Report - ${student.first_name} ${student.last_name}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.6;
            color: #333;
            background: white;
            padding: 20px;
        }

        .report-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 2px solid #2c5aa0;
        }

        .header {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a8a 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .school-logo {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .school-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .report-title {
            font-size: 20px;
            font-weight: bold;
            background: rgba(255,255,255,0.1);
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
        }

        .content {
            padding: 30px;
        }

        .student-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #cbd5e0;
        }

        .info-label {
            font-weight: bold;
            color: #4a5568;
        }

        .info-value {
            color: #2d3748;
        }

        .stats-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c5aa0;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card.present .stat-number { color: #16a34a; }
        .stat-card.absent .stat-number { color: #dc2626; }
        .stat-card.late .stat-number { color: #d97706; }
        .stat-card.excused .stat-number { color: #2563eb; }

        .weekly-breakdown {
            margin-bottom: 30px;
        }

        .week-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .week-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .week-title {
            font-weight: bold;
            color: #374151;
            margin-bottom: 10px;
            text-align: center;
        }

        .week-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            font-size: 12px;
        }

        .week-stat {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .attendance-rate {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 20px;
            padding: 8px 15px;
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
            color: #0369a1;
        }

        .improvements-section, .concerns-section {
            margin-bottom: 25px;
        }

        .improvements-list, .concerns-list, .next-steps-list {
            list-style: none;
            padding: 0;
        }

        .improvements-list li, .concerns-list li, .next-steps-list li {
            padding: 8px 0;
            border-bottom: 1px dotted #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .improvements-list li::before {
            content: "✓";
            color: #16a34a;
            font-weight: bold;
        }

        .concerns-list li::before {
            content: "⚠";
            color: #dc2626;
            font-weight: bold;
        }

        .next-steps-list li::before {
            content: "→";
            color: #2563eb;
            font-weight: bold;
        }

        .footer {
            background: #f8fafc;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #6b7280;
        }

        .signature-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .signature-line {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #333;
        }

        @media print {
            body { padding: 0; }
            .report-container { border: none; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <div class="school-logo">
                <img src="/demo/images/logo.png" alt="NCS Logo" />
            </div>
            <div class="school-name">Naawan Central School</div>
            <div class="school-subtitle">Attendance Monitoring System</div>
            <div class="report-title">Student Attendance Report</div>
        </div>

        <div class="content">
            <div class="student-info">
                <h3 style="margin-bottom: 15px; color: #2c5aa0;">Student Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Full Name:</span>
                        <span class="info-value">${student.first_name} ${student.last_name}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Grade Level:</span>
                        <span class="info-value">${getStudentGradeLevel(student)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Section:</span>
                        <span class="info-value">${getStudentSection(student)}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">School Year:</span>
                        <span class="info-value">${schoolYear}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Report Date:</span>
                        <span class="info-value">${currentDate}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Subject:</span>
                        <span class="info-value">${props.selectedSubject?.name || 'All Subjects'}</span>
                    </div>
                </div>
            </div>

            <div class="stats-section">
                <h3 class="section-title">Attendance Summary</h3>
                <div class="stats-grid">
                    <div class="stat-card present">
                        <div class="stat-number">${getTotalPresent(student)}</div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="stat-card absent">
                        <div class="stat-number">${student.total_absences || 0}</div>
                        <div class="stat-label">Absent</div>
                    </div>
                    <div class="stat-card late">
                        <div class="stat-number">${getTotalLate(student)}</div>
                        <div class="stat-label">Late</div>
                    </div>
                    <div class="stat-card excused">
                        <div class="stat-number">${getTotalExcused(student)}</div>
                        <div class="stat-label">Excused</div>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <div style="font-size: 18px; font-weight: bold; color: #2c5aa0;">
                        Overall Attendance Rate: ${overallAttendanceRate}%
                    </div>
                </div>
            </div>

            <div class="weekly-breakdown">
                <h3 class="section-title">Weekly Attendance Breakdown</h3>
                <div class="week-grid">
                    ${progressData.value.weeklyAttendance
                        .map(
                            (week) => `
                        <div class="week-card">
                            <div class="week-title">${week.week}</div>
                            <div class="week-stats">
                                <div class="week-stat">
                                    <span>Present:</span>
                                    <span style="color: #16a34a; font-weight: bold;">${week.present}</span>
                                </div>
                                <div class="week-stat">
                                    <span>Absent:</span>
                                    <span style="color: #dc2626; font-weight: bold;">${week.absent}</span>
                                </div>
                                <div class="week-stat">
                                    <span>Late:</span>
                                    <span style="color: #d97706; font-weight: bold;">${week.late}</span>
                                </div>
                            </div>
                            <div class="attendance-rate">${week.percentage}%</div>
                        </div>
                    `
                        )
                        .join('')}
                </div>
            </div>

            <div class="improvements-section">
                <h3 class="section-title">Positive Improvements</h3>
                <ul class="improvements-list">
                    ${progressData.value.improvements
                        .map(
                            (improvement) => `
                        <li>${improvement}</li>
                    `
                        )
                        .join('')}
                </ul>
            </div>

            <div class="concerns-section">
                <h3 class="section-title">Areas of Concern</h3>
                <ul class="concerns-list">
                    ${progressData.value.concerns
                        .map(
                            (concern) => `
                        <li>${concern}</li>
                    `
                        )
                        .join('')}
                </ul>
            </div>

            <div class="next-steps-section">
                <h3 class="section-title">Recommended Next Steps</h3>
                <ul class="next-steps-list">
                    ${progressData.value.nextSteps
                        .map(
                            (step) => `
                        <li>${step}</li>
                    `
                        )
                        .join('')}
                </ul>
            </div>

            <div class="signature-section">
                <div>
                    <div class="signature-line">
                        <div>Teacher Signature</div>
                    </div>
                </div>
                <div>
                    <div class="signature-line">
                        <div>Date</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This report was generated by the Naawan Central School Attendance Monitoring System</p>
            <p>Generated on ${currentDate} | Confidential Student Information</p>
        </div>
    </div>
</body>
</html>
    `;
}

// Risk dialog state
const showRiskDialog = ref(false);
const activeRiskTab = ref('critical');
const wasRiskDialogOpen = ref(false);

// Scroll indicator state
const showScrollIndicator = ref(true);
const progressContentRef = ref(null);

// Grouped students computed property
const groupedStudents = computed(() => {
    const groups = { critical: [], high: [], low: [] };

    if (!studentsNeedingAttention.value || !Array.isArray(studentsNeedingAttention.value)) {
        return groups;
    }

    studentsNeedingAttention.value.forEach((student) => {
        const riskLevel = getRiskLevel(student);

        if (riskLevel === 'critical') {
            groups.critical.push(student);
        } else if (riskLevel === 'high') {
            groups.high.push(student);
        } else if (riskLevel === 'low') {
            groups.low.push(student);
        }
    });

    return groups;
});

function getCurrentSchoolYear() {
    const currentDate = new Date();
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth();

    // School year typically starts in June (month 5) and ends in March (month 2)
    if (currentMonth >= 5) {
        return `${currentYear}-${currentYear + 1}`;
    } else {
        return `${currentYear - 1}-${currentYear}`;
    }
}

function calculateOverallAttendanceRate() {
    if (!progressData.value.weeklyAttendance.length) return 0;

    const totalWeeks = progressData.value.weeklyAttendance.length;
    const totalPercentage = progressData.value.weeklyAttendance.reduce((sum, week) => sum + week.percentage, 0);

    return Math.round(totalPercentage / totalWeeks);
}

function getTotalPresent(student) {
    return progressData.value.weeklyAttendance.reduce((sum, week) => sum + week.present, 0);
}

function getTotalLate(student) {
    return progressData.value.weeklyAttendance.reduce((sum, week) => sum + week.late, 0);
}

function getTotalExcused(student) {
    // For now, return 0 as we don't have excused data in the mock data
    return 0;
}

function getStudentGradeLevel(student) {
    // Check if student has grade_level property
    if (student.grade_level) {
        // Handle numeric grade levels
        if (typeof student.grade_level === 'number') {
            if (student.grade_level === 0) return 'Kindergarten';
            if (student.grade_level === 1) return 'Kinder One';
            if (student.grade_level === 2) return 'Kinder Two';
            return `Grade ${student.grade_level}`;
        }
        // Handle string grade levels
        return student.grade_level;
    }

    // Check if section name contains grade info
    const section = student.section || '';
    if (section.toLowerCase().includes('kinder')) {
        return 'Kinder One';
    }

    // Default fallback
    return 'Kinder One';
}

function getStudentSection(student) {
    // Get section name, default to current context
    return student.section || 'Kinder One';
}

// Risk dialog functions
function openRiskDialog(riskType) {
    activeRiskTab.value = riskType;
    showRiskDialog.value = true;
}

function switchRiskTab(riskType) {
    if (groupedStudents.value[riskType] && groupedStudents.value[riskType].length > 0) {
        activeRiskTab.value = riskType;
    }
}

function getCurrentRiskStudents() {
    return groupedStudents.value[activeRiskTab.value] || [];
}

function getRiskDialogTitle() {
    const titles = {
        critical: 'Critical Students',
        high: 'High Risk Students',
        low: 'Low Risk Students'
    };
    return titles[activeRiskTab.value] || 'Students Requiring Attention';
}

function viewStudentProfile(student) {
    // Don't close risk dialog - keep it open in the background
    // Show detailed student profile - opens the progress dialog
    trackProgress(student);
}

// Handle scroll in progress dialog
function handleProgressScroll(event) {
    const element = event.target;
    const scrollTop = element.scrollTop;
    const scrollHeight = element.scrollHeight;
    const clientHeight = element.clientHeight;
    
    // Hide indicator when scrolled near bottom (within 50px)
    if (scrollTop + clientHeight >= scrollHeight - 50) {
        showScrollIndicator.value = false;
    } else {
        showScrollIndicator.value = true;
    }
}

// Scroll down smoothly when indicator is clicked
function scrollDown() {
    if (progressContentRef.value) {
        const element = progressContentRef.value;
        const scrollAmount = element.clientHeight * 0.8; // Scroll 80% of visible height
        
        element.scrollBy({
            top: scrollAmount,
            behavior: 'smooth'
        });
    }
}
</script>

<style scoped>
.attendance-insights {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.insights-header {
    margin-bottom: 1.5rem;
}

.insights-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.25rem 0;
    color: #2c3e50;
    font-size: 1.25rem;
    font-weight: 600;
}

.insights-subtitle {
    color: #6c757d;
    font-size: 0.875rem;
}

/* Risk cards CSS removed - cards now on main dashboard */

.section-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 2rem 0 1rem 0;
    color: #2c3e50;
    font-size: 1.1rem;
    font-weight: 600;
}

.student-plan-cards {
    display: grid;
    gap: 1rem;
}

.student-plan-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1.5rem;
    border-left: 4px solid #6c757d;
}

.student-plan-card.critical {
    border-left-color: #dc3545;
}

.student-plan-card.warning {
    border-left-color: #ffc107;
}

.student-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.student-name {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #2c3e50;
}

.student-meta {
    display: flex;
    gap: 0.5rem;
}

.grade-badge,
.section-badge {
    background: #e9ecef;
    color: #495057;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.risk-indicator {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}

.risk-indicator.critical {
    background: #dc3545;
}

.risk-indicator.warning {
    background: #ffc107;
}

.attendance-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.summary-item .label {
    font-size: 0.875rem;
    color: #6c757d;
}

.summary-item .value {
    font-weight: 600;
    color: #2c3e50;
}

.risk-factors {
    margin-bottom: 1rem;
}

.risk-factors h6 {
    margin: 0 0 0.5rem 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
}

.factors-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.factor-tag {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.factor-academic {
    background: #fff3cd;
    color: #856404;
}

.factor-consecutive {
    background: #f8d7da;
    color: #721c24;
}

.factor-economic {
    background: #d1ecf1;
    color: #0c5460;
}

.factor-health {
    background: #e2e3e5;
    color: #383d41;
}

.recommended-actions {
    margin-bottom: 1rem;
}

.recommended-actions h6 {
    margin: 0 0 0.5rem 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
}

.actions-list {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.attendance-plan {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.attendance-plan h6 {
    margin: 0 0 0.5rem 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
}

.plan-content p {
    margin: 0 0 1rem 0;
    font-size: 0.875rem;
    line-height: 1.4;
}

.plan-goals {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.goal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.goal-label {
    font-size: 0.75rem;
    color: #6c757d;
}

.goal-value {
    font-weight: 600;
    font-size: 0.875rem;
    color: #2c3e50;
}

.success-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.success-card {
    background: linear-gradient(135deg, #d4edda 0%, #ffffff 100%);
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 1rem;
    border-left: 4px solid #28a745;
}

.success-card .student-name {
    font-weight: 600;
    color: #155724;
    margin-bottom: 0.5rem;
}

.success-card .improvement-text {
    font-size: 0.875rem;
    color: #155724;
    line-height: 1.4;
}

.plan-form .field {
    margin-bottom: 1rem;
}

.plan-form label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
}

/* Progress Tracking Dialog Styles */
.progress-content {
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden; /* Hide horizontal scrollbar */
    position: relative;
}

/* Scroll Indicator - Circular Floating Button */
.scroll-indicator-circle {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.95), rgba(37, 99, 235, 0.95));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 1000;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.4), 0 2px 8px rgba(0, 0, 0, 0.2);
    animation: floatBounce 2s ease-in-out infinite;
    transition: all 0.3s ease;
}

.scroll-indicator-circle:hover {
    transform: translateX(-50%) scale(1.1);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5), 0 3px 10px rgba(0, 0, 0, 0.3);
}

.scroll-indicator-circle i {
    font-size: 1.5rem;
    animation: chevronPulse 2s ease-in-out infinite;
}

@keyframes floatBounce {
    0%, 100% {
        transform: translateX(-50%) translateY(0);
    }
    50% {
        transform: translateX(-50%) translateY(-10px);
    }
}

@keyframes chevronPulse {
    0%, 100% {
        transform: translateY(0);
        opacity: 1;
    }
    50% {
        transform: translateY(4px);
        opacity: 0.7;
    }
}

.progress-section {
    margin-bottom: 2rem;
    padding: 1rem;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: #f8f9fa;
}

.section-header-with-picker {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.filters-container {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-section h5 {
    margin: 0 0 1rem 0;
    color: #495057;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.1rem;
}

/* Improvements, Concerns, and Next Steps Lists */
.improvement-list,
.concern-list,
.next-steps-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.improvement-item,
.concern-item,
.next-step-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.875rem;
    background: white;
    border-radius: 8px;
    border-left: 4px solid;
    transition: all 0.2s ease;
    line-height: 1.6;
}

.improvement-item {
    border-left-color: #10b981;
    background: linear-gradient(to right, #f0fdf4 0%, #ffffff 100%);
}

.concern-item {
    border-left-color: #f59e0b;
    background: linear-gradient(to right, #fffbeb 0%, #ffffff 100%);
}

.next-step-item {
    border-left-color: #3b82f6;
    background: linear-gradient(to right, #eff6ff 0%, #ffffff 100%);
}

.improvement-item:hover,
.concern-item:hover,
.next-step-item:hover {
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.improvement-item i,
.concern-item i,
.next-step-item i {
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.weekly-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.week-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.week-meta {
    display: flex;
    gap: 4px;
    margin-top: 4px;
    flex-wrap: wrap;
}

.meta-badge {
    background: #e9ecef;
    color: #495057;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 500;
}

.subject-breakdown-info {
    margin-top: 12px;
    padding: 8px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 3px solid #6366f1;
}

.breakdown-label {
    display: block;
    font-weight: 600;
    color: #495057;
    margin-bottom: 6px;
    font-size: 0.8rem;
}

.subject-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.subject-item {
    font-size: 0.85rem;
    display: flex;
    gap: 6px;
    align-items: center;
}

.subject-item strong {
    color: #495057;
    min-width: 80px;
}

.subject-item span {
    padding: 1px 4px;
    border-radius: 3px;
    font-weight: 500;
    font-size: 0.75rem;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.25rem;
    font-size: 0.875rem;
}

.stat-item.present .stat-value {
    color: #28a745;
    font-weight: 600;
}

.stat-item.absent .stat-value {
    color: #dc3545;
    font-weight: 600;
}

.stat-item.late .stat-value {
    color: #ffc107;
    font-weight: 600;
}

.percentage-bar {
    position: relative;
    height: 20px;
    background: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.percentage-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    transition: width 0.3s ease;
}

.percentage-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 0.75rem;
    font-weight: 600;
    color: white;
    text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.3);
}

.improvement-list,
.concern-list,
.next-steps-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

/* Risk Cards Grid */
.risk-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.risk-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.risk-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: transparent;
}

.risk-card.critical::before {
    background: #dc3545;
}

.risk-card.high::before {
    background: #ffc107;
}

.risk-card.low::before {
    background: #10b981;
}

.risk-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.risk-card.critical:hover {
    border-color: #dc3545;
    background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
}

.risk-card.high:hover {
    border-color: #ffc107;
    background: linear-gradient(135deg, #fffbeb 0%, #ffffff 100%);
}

.risk-card.low:hover {
    border-color: #10b981;
    background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
}

.risk-card-icon {
    width: 48px;
    height: 48px;
    margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.risk-card.critical .risk-card-icon {
    background: #fee;
    color: #dc3545;
}

.risk-card.high .risk-card-icon {
    background: #fffbeb;
    color: #ffc107;
}

.risk-card.low .risk-card-icon {
    background: #f0fdf4;
    color: #10b981;
}

.risk-card-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.risk-card-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.risk-card-count {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.risk-card-desc {
    font-size: 0.75rem;
    color: #94a3b8;
}

/* Risk Dialog Styles */
.risk-tabs {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 0;
}

.risk-tab {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s ease;
    font-weight: 500;
    color: #64748b;
    background: transparent;
    border-radius: 4px 4px 0 0;
}

.risk-tab:hover:not(.disabled) {
    background: #f8fafc;
    color: #1e293b;
}

.risk-tab.active {
    color: #1e293b;
    font-weight: 600;
}

.risk-tab.active.critical {
    border-bottom-color: #dc3545;
    color: #dc3545;
}

.risk-tab.active.high {
    border-bottom-color: #ffc107;
    color: #d97706;
}

.risk-tab.active.low {
    border-bottom-color: #10b981;
    color: #10b981;
}

.risk-tab.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.risk-tab i {
    font-size: 1rem;
}

.risk-dialog-content {
    max-height: 500px;
    overflow-y: auto;
}

.students-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-left: 4px solid;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.student-item:hover {
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transform: translateX(4px);
}

.student-item.critical {
    border-left-color: #dc3545;
}

.student-item.high {
    border-left-color: #ffc107;
}

.student-item.low {
    border-left-color: #10b981;
}

.student-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 2px solid #e2e8f0;
    flex-shrink: 0;
}

.student-item.critical .student-item-icon {
    background: #fee;
    border-color: #dc3545;
    color: #dc3545;
}

.student-item.high .student-item-icon {
    background: #fffbeb;
    border-color: #ffc107;
    color: #d97706;
}

.student-item.low .student-item-icon {
    background: #f0fdf4;
    border-color: #10b981;
    color: #10b981;
}

.student-item-info {
    flex: 1;
    min-width: 0;
}

.student-item-name {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.5rem;
}

.student-item-stats {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.stat-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    color: #64748b;
    background: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.stat-badge i {
    font-size: 0.7rem;
}

.student-item-action {
    color: #94a3b8;
    flex-shrink: 0;
}

.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    color: #94a3b8;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #cbd5e1;
}

.empty-state p {
    font-size: 1rem;
    margin: 0;
}

.improvement-item,
.concern-item,
.next-step-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
    padding: 0.5rem;
    background: white;
    border-radius: 4px;
    border-left: 3px solid transparent;
}

.improvement-item {
    border-left-color: #28a745;
}

.concern-item {
    border-left-color: #ffc107;
}

.next-step-item {
    border-left-color: #007bff;
}

.improvement-item i,
.concern-item i,
.next-step-item i {
    margin-top: 0.125rem;
    flex-shrink: 0;
}

/* Risk Legend Styles - Horizontal Badge Design */
.risk-legend {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 0.875rem 1.25rem;
    margin-bottom: 1.5rem;
}

.legend-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.legend-header i {
    color: #3b82f6;
    font-size: 1rem;
}

.legend-badges {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.legend-badge-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.legend-badge-item:hover {
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.legend-badge-item i {
    font-size: 1rem;
    flex-shrink: 0;
}

.legend-badge-item.critical i {
    color: #dc3545;
}

.legend-badge-item.high i {
    color: #ffc107;
}

.legend-badge-item.low i {
    color: #10b981;
}

.badge-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1e293b;
}

.badge-desc {
    font-size: 0.75rem;
    color: #64748b;
    padding-left: 0.25rem;
    border-left: 1px solid #e2e8f0;
}
</style>
