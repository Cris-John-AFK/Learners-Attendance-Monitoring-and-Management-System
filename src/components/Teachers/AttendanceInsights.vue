<template>
    <div class="attendance-insights">
        <div class="insights-header">
            <h3 class="insights-title">
                <i class="pi pi-lightbulb"></i>
                Attendance Insights
            </h3>
            <small class="insights-subtitle">{{ selectedSubject?.name || 'All Students' }}</small>
        </div>

        <!-- Risk Assessment Cards -->
        <div class="risk-cards-grid">
            <div class="risk-card critical" v-if="criticalStudents.length > 0">
                <div class="risk-header">
                    <i class="pi pi-exclamation-circle"></i>
                    <span>Critical Risk</span>
                </div>
                <div class="risk-count">{{ criticalStudents.length }}</div>
                <div class="risk-description">Students with 5+ recent absences</div>
            </div>

            <div class="risk-card warning" v-if="warningStudents.length > 0">
                <div class="risk-header">
                    <i class="pi pi-exclamation-triangle"></i>
                    <span>At Risk</span>
                </div>
                <div class="risk-count">{{ warningStudents.length }}</div>
                <div class="risk-description">Students with 3-4 recent absences</div>
            </div>

            <div class="risk-card consecutive" v-if="consecutiveAbsenceStudents.length > 0">
                <div class="risk-header">
                    <i class="pi pi-calendar-times"></i>
                    <span>Consecutive Absences</span>
                </div>
                <div class="risk-count">{{ consecutiveAbsenceStudents.length }}</div>
                <div class="risk-description">Students with 3+ consecutive days absent</div>
            </div>
        </div>

        <!-- Individual Student Plans -->
        <div class="student-plans-section" v-if="studentsNeedingAttention.length > 0">
            <h4 class="section-title">
                <i class="pi pi-users"></i>
                Students Requiring Attention
            </h4>

            <div class="student-plan-cards">
                <div 
                    v-for="student in studentsNeedingAttention" 
                    :key="student.student_id"
                    class="student-plan-card"
                    :class="student.riskLevel"
                >
                    <div class="student-header">
                        <div class="student-info">
                            <h5 class="student-name">{{ student.first_name }} {{ student.last_name }}</h5>
                            <div class="student-meta">
                                <span class="grade-badge">Grade {{ student.grade_level || 3 }}</span>
                                <span class="section-badge">{{ student.section || 'Malikhain' }}</span>
                            </div>
                        </div>
                        <div class="risk-indicator" :class="student.riskLevel">
                            <i :class="getRiskIcon(student.riskLevel)"></i>
                        </div>
                    </div>

                    <div class="attendance-summary">
                        <div class="summary-item">
                            <span class="label">Total Absences:</span>
                            <span class="value">{{ student.total_absences }}</span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Recent Absences:</span>
                            <span class="value">{{ student.recent_absences }}</span>
                        </div>
                        <div class="summary-item" v-if="student.consecutive_absences > 0">
                            <span class="label">Consecutive:</span>
                            <span class="value">{{ student.consecutive_absences }} days</span>
                        </div>
                    </div>

                    <!-- Risk Factors -->
                    <div class="risk-factors" v-if="student.riskFactors && student.riskFactors.length > 0">
                        <h6>Risk Factors:</h6>
                        <div class="factors-list">
                            <span 
                                v-for="factor in student.riskFactors" 
                                :key="factor"
                                class="factor-tag"
                                :class="getFactorClass(factor)"
                            >
                                {{ factor }}
                            </span>
                        </div>
                    </div>

                    <!-- Recommended Actions -->
                    <div class="recommended-actions">
                        <h6>Recommended Actions:</h6>
                        <div class="actions-list">
                            <div 
                                v-for="action in getRecommendedActions(student)" 
                                :key="action.id"
                                class="action-item"
                            >
                                <Button 
                                    :label="action.label"
                                    :icon="action.icon"
                                    :class="action.class"
                                    size="small"
                                    @click="executeAction(action, student)"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Individual Attendance Plan -->
                    <div class="attendance-plan" v-if="student.attendancePlan">
                        <h6>Individual Attendance Plan:</h6>
                        <div class="plan-content">
                            <p>{{ student.attendancePlan.description }}</p>
                            <div class="plan-goals">
                                <div class="goal-item">
                                    <span class="goal-label">Target:</span>
                                    <span class="goal-value">{{ student.attendancePlan.targetAttendance }}% attendance</span>
                                </div>
                                <div class="goal-item">
                                    <span class="goal-label">Review Date:</span>
                                    <span class="goal-value">{{ formatDate(student.attendancePlan.reviewDate) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Stories -->
        <div class="success-section" v-if="improvingStudents.length > 0">
            <h4 class="section-title">
                <i class="pi pi-chart-line"></i>
                Improving Students
            </h4>
            <div class="success-cards">
                <div 
                    v-for="student in improvingStudents" 
                    :key="student.student_id"
                    class="success-card"
                >
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
                    <InputNumber 
                        id="targetAttendance" 
                        v-model="newPlan.targetAttendance" 
                        :min="70" 
                        :max="100" 
                        suffix="%" 
                    />
                </div>
                <div class="field">
                    <label for="planDescription">Plan Description</label>
                    <Textarea 
                        id="planDescription" 
                        v-model="newPlan.description" 
                        rows="4" 
                        placeholder="Describe the specific interventions and support strategies..."
                    />
                </div>
                <div class="field">
                    <label for="reviewDate">Review Date</label>
                    <Calendar 
                        id="reviewDate" 
                        v-model="newPlan.reviewDate" 
                        :minDate="new Date()" 
                        dateFormat="mm/dd/yy"
                    />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="showPlanDialog = false" />
                <Button label="Create Plan" icon="pi pi-check" @click="savePlan" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Textarea from 'primevue/textarea';
import Calendar from 'primevue/calendar';

const props = defineProps({
    students: {
        type: Array,
        default: () => []
    },
    selectedSubject: {
        type: Object,
        default: null
    }
});

// Dialog state
const showPlanDialog = ref(false);
const selectedStudentForPlan = ref(null);
const newPlan = ref({
    targetAttendance: 85,
    description: '',
    reviewDate: null
});

// Computed properties for student categorization
const criticalStudents = computed(() => {
    return props.students.filter(student => student.recent_absences >= 5);
});

const warningStudents = computed(() => {
    return props.students.filter(student => student.recent_absences >= 3 && student.recent_absences < 5);
});

const consecutiveAbsenceStudents = computed(() => {
    return props.students.filter(student => (student.consecutive_absences || 0) >= 3);
});

const studentsNeedingAttention = computed(() => {
    return props.students
        .filter(student => student.recent_absences >= 3 || (student.consecutive_absences || 0) >= 3)
        .map(student => ({
            ...student,
            riskLevel: getRiskLevel(student),
            riskFactors: getRiskFactors(student),
            attendancePlan: getExistingPlan(student),
            consecutive_absences: student.consecutive_absences || calculateConsecutiveAbsences(student)
        }))
        .sort((a, b) => b.recent_absences - a.recent_absences);
});

const improvingStudents = computed(() => {
    // Students who had high absences but are now improving
    return props.students
        .filter(student => {
            const hadIssues = student.total_absences >= 5;
            const improving = student.recent_absences < 2;
            return hadIssues && improving;
        })
        .map(student => ({
            ...student,
            improvementNote: `Reduced from ${student.total_absences} total absences to only ${student.recent_absences} recent absences`
        }));
});

// Helper functions
function getRiskLevel(student) {
    if (student.recent_absences >= 5 || (student.consecutive_absences || 0) >= 5) return 'critical';
    if (student.recent_absences >= 3 || (student.consecutive_absences || 0) >= 3) return 'warning';
    return 'normal';
}

function getRiskIcon(riskLevel) {
    const icons = {
        critical: 'pi pi-exclamation-circle',
        warning: 'pi pi-exclamation-triangle',
        normal: 'pi pi-check-circle'
    };
    return icons[riskLevel] || 'pi pi-info-circle';
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
            id: 'create-plan',
            label: 'Create Attendance Plan',
            icon: 'pi pi-file-plus',
            class: 'p-button-danger',
            priority: 'high'
        });
        actions.push({
            id: 'schedule-meeting',
            label: 'Schedule Student Meeting',
            icon: 'pi pi-calendar-plus',
            class: 'p-button-warning',
            priority: 'high'
        });
    }
    
    if (student.riskLevel === 'warning' || student.riskLevel === 'critical') {
        actions.push({
            id: 'send-warning',
            label: 'Issue Attendance Warning',
            icon: 'pi pi-exclamation-triangle',
            class: 'p-button-warning',
            priority: 'medium'
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
        case 'create-plan':
            openPlanDialog(student);
            break;
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
    // Simulate progress tracking setup
    console.log(`Setting up progress tracking for ${student.first_name} ${student.last_name}`);
    // In real implementation, this would create monitoring alerts
}

function savePlan() {
    // Simulate saving attendance plan
    console.log('Saving attendance plan:', {
        student: selectedStudentForPlan.value,
        plan: newPlan.value
    });
    showPlanDialog.value = false;
}

function getExistingPlan(student) {
    // Simulate checking for existing plans
    // In real implementation, this would query the database
    return null;
}

function calculateConsecutiveAbsences(student) {
    // Simulate calculating consecutive absences
    // In real implementation, this would analyze recent attendance records
    return Math.floor(Math.random() * 4); // 0-3 consecutive days
}

function formatDate(date) {
    if (!date) return '';
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    }).format(new Date(date));
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

.risk-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.risk-card {
    padding: 1rem;
    border-radius: 8px;
    border-left: 4px solid;
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.risk-card.critical {
    border-left-color: #dc3545;
    background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
}

.risk-card.warning {
    border-left-color: #ffc107;
    background: linear-gradient(135deg, #fffbf0 0%, #ffffff 100%);
}

.risk-card.consecutive {
    border-left-color: #fd7e14;
    background: linear-gradient(135deg, #fff8f0 0%, #ffffff 100%);
}

.risk-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
}

.risk-count {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.risk-description {
    font-size: 0.75rem;
    color: #6c757d;
}

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

.grade-badge, .section-badge {
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
</style>
