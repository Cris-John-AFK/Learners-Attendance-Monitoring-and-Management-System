<template>
    <div class="archive-wrapper">
        <!-- Animated Background -->
        <div class="background-container">
            <div class="floating-shape shape-1"></div>
            <div class="floating-shape shape-2"></div>
            <div class="floating-shape shape-3"></div>
            <div class="floating-shape shape-4"></div>
            <div class="floating-shape shape-5"></div>
        </div>

        <!-- Main Content -->
        <div class="content-container">
            <!-- Modern Header -->
            <div class="archive-header">
                <div class="header-content">
                    <div class="title-section">
                        <div class="icon-wrapper">
                            <i class="pi pi-archive animated-icon"></i>
                        </div>
                        <div class="title-text">
                            <h1 class="page-title">
                                <span class="text-gradient">Archive Center</span>
                            </h1>
                            <p class="page-subtitle">Manage archived sections and students</p>
                        </div>
                    </div>
                    <div class="header-stats">
                        <div class="stat-card">
                            <span class="stat-number">{{ archivedSections.length }}</span>
                            <span class="stat-label">Sections</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-number">{{ archivedStudents.length }}</span>
                            <span class="stat-label">Students</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enhanced Search Bar -->
            <div class="search-section">
                <div class="search-container">
                    <div class="search-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <InputText 
                            v-model="searchQuery" 
                            placeholder="Search archives by name, date, or status..." 
                            class="modern-search" 
                        />
                        <div class="search-glow"></div>
                    </div>
                    <Button 
                        icon="pi pi-filter" 
                        class="filter-btn" 
                        v-tooltip.top="'Advanced Filters'"
                        @click="showFilters = !showFilters"
                    />
                </div>
            </div>

            <!-- Archive Categories -->
            <div class="archive-categories">
                <!-- Archived Sections -->
                <div class="category-section">
                    <div class="category-header">
                        <div class="category-title">
                            <i class="pi pi-sitemap category-icon"></i>
                            <h2>Archived Sections</h2>
                            <span class="count-badge">{{ filteredSections.length }}</span>
                        </div>
                    </div>
                    
                    <div class="cards-grid" v-if="filteredSections.length > 0">
                        <div 
                            v-for="section in filteredSections" 
                            :key="section.id" 
                            class="archive-card section-card"
                        >
                            <div class="card-background"></div>
                            <div class="card-content">
                                <div class="card-icon">
                                    <i class="pi pi-sitemap"></i>
                                </div>
                                <div class="card-info">
                                    <h3 class="card-title">{{ section.name }}</h3>
                                    <p class="card-date">
                                        <i class="pi pi-calendar"></i>
                                        Archived: {{ formatDate(section.archivedDate) }}
                                    </p>
                                </div>
                                <div class="card-actions">
                                    <Button 
                                        icon="pi pi-eye" 
                                        class="action-btn view-btn" 
                                        v-tooltip.top="'View Details'"
                                    />
                                    <Button 
                                        icon="pi pi-refresh" 
                                        class="action-btn recover-btn" 
                                        v-tooltip.top="'Recover Section'"
                                        @click="confirmRecover(section, 'section')"
                                    />
                                </div>
                            </div>
                            <div class="card-glow"></div>
                        </div>
                    </div>
                    
                    <div v-else class="empty-state">
                        <i class="pi pi-inbox empty-icon"></i>
                        <h3>No Archived Sections</h3>
                        <p>No sections match your search criteria</p>
                    </div>
                </div>

                <!-- Archived Students Categories -->
                <div class="category-section">
                    <div class="category-header">
                        <div class="category-title">
                            <i class="pi pi-users category-icon"></i>
                            <h2>Archived Students</h2>
                            <span class="count-badge">{{ archivedStudents.length }}</span>
                        </div>
                    </div>
                    
                    <div class="student-categories">
                        <div class="archive-card student-category-card graduated" @click="openModal('Graduated')">
                            <div class="card-background"></div>
                            <div class="card-content">
                                <div class="card-icon graduated-icon">
                                    <i class="pi pi-graduation-cap"></i>
                                </div>
                                <div class="card-info">
                                    <h3 class="card-title">Graduated Students</h3>
                                    <p class="card-description">Students who completed their education</p>
                                    <div class="student-count">
                                        <span class="count-number">{{ graduatedCount }}</span>
                                        <span class="count-label">Students</span>
                                    </div>
                                </div>
                                <div class="card-arrow">
                                    <i class="pi pi-arrow-right"></i>
                                </div>
                            </div>
                            <div class="card-glow graduated-glow"></div>
                        </div>

                        <div class="archive-card student-category-card dropped" @click="openModal('Dropped')">
                            <div class="card-background"></div>
                            <div class="card-content">
                                <div class="card-icon dropped-icon">
                                    <i class="pi pi-user-minus"></i>
                                </div>
                                <div class="card-info">
                                    <h3 class="card-title">Dropped Students</h3>
                                    <p class="card-description">Students who left the institution</p>
                                    <div class="student-count">
                                        <span class="count-number">{{ droppedCount }}</span>
                                        <span class="count-label">Students</span>
                                    </div>
                                </div>
                                <div class="card-arrow">
                                    <i class="pi pi-arrow-right"></i>
                                </div>
                            </div>
                            <div class="card-glow dropped-glow"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Student Modal -->
        <Dialog 
            v-model:visible="showModal" 
            modal 
            :header="modalTitle" 
            class="student-modal"
            :style="{ width: '90vw', maxWidth: '800px' }"
        >
            <template #header>
                <div class="modal-header">
                    <div class="modal-title-section">
                        <i :class="modalIcon" class="modal-icon"></i>
                        <h3>{{ modalTitle }}</h3>
                    </div>
                    <div class="modal-stats">
                        <span class="student-count-badge">{{ filteredStudents.length }} Students</span>
                    </div>
                </div>
            </template>
            
            <div class="modal-content">
                <div class="students-grid">
                    <div 
                        v-for="student in filteredStudents" 
                        :key="student.id" 
                        class="student-card"
                    >
                        <div class="student-avatar">
                            <i class="pi pi-user"></i>
                        </div>
                        <div class="student-info">
                            <h4 class="student-name">{{ student.name }}</h4>
                            <div class="student-details">
                                <span class="status-badge" :class="getStatusClass(student.status)">
                                    <i :class="getStatusIcon(student.status)"></i>
                                    {{ student.status }}
                                </span>
                                <span class="archive-date">
                                    <i class="pi pi-calendar"></i>
                                    {{ formatDate(student.archivedDate) }}
                                </span>
                            </div>
                        </div>
                        <div class="student-actions">
                            <Button 
                                icon="pi pi-eye" 
                                class="action-btn view-btn" 
                                v-tooltip.top="'View Profile'"
                            />
                            <Button 
                                icon="pi pi-refresh" 
                                class="action-btn recover-btn" 
                                v-tooltip.top="'Recover Student'"
                                @click="confirmRecover(student, 'student')"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Enhanced Confirm Dialog -->
        <Dialog 
            v-model:visible="confirmDialog" 
            modal 
            class="confirm-modal"
            :style="{ width: '450px' }"
        >
            <template #header>
                <div class="confirm-header">
                    <i class="pi pi-exclamation-triangle warning-icon"></i>
                    <h3>Confirm Recovery</h3>
                </div>
            </template>
            
            <div class="confirm-content">
                <div class="confirm-message">
                    <p>Are you sure you want to recover this {{ recoverType }}?</p>
                    <div class="item-preview" v-if="selectedItem">
                        <strong>{{ selectedItem.name }}</strong>
                    </div>
                </div>
            </div>
            
            <template #footer>
                <div class="confirm-actions">
                    <Button 
                        label="Cancel" 
                        icon="pi pi-times" 
                        class="cancel-btn" 
                        @click="confirmDialog = false" 
                    />
                    <Button 
                        label="Recover" 
                        icon="pi pi-refresh" 
                        class="confirm-btn" 
                        @click="recoverItem" 
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();

const searchQuery = ref('');
const showModal = ref(false);
const confirmDialog = ref(false);
const modalTitle = ref('');
const recoverType = ref('');
const selectedItem = ref(null);

const archivedSections = ref([
    { id: 1, name: 'Grade 3 - Section A', archivedDate: '2025-03-10' },
    { id: 2, name: 'Grade 5 - Section B', archivedDate: '2025-02-28' }
]);

const archivedStudents = ref([
    { id: 1, name: 'John Doe', status: 'Dropped', archivedDate: '2025-02-15' },
    { id: 2, name: 'Jane Smith', status: 'Graduated', archivedDate: '2024-06-20' }
]);

const filteredSections = computed(() => {
    return archivedSections.value.filter(section =>
        section.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const filteredStudents = computed(() => {
    return archivedStudents.value.filter(student =>
        modalTitle.value.includes(student.status)
    );
});

const graduatedCount = computed(() => {
    return archivedStudents.value.filter(student => student.status === 'Graduated').length;
});

const droppedCount = computed(() => {
    return archivedStudents.value.filter(student => student.status === 'Dropped').length;
});

const modalIcon = computed(() => {
    return modalTitle.value.includes('Graduated') ? 'pi pi-graduation-cap' : 'pi pi-user-minus';
});

const showFilters = ref(false);

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
};

const getStatusClass = (status) => {
    return status === 'Graduated' ? 'status-graduated' : 'status-dropped';
};

const getStatusIcon = (status) => {
    return status === 'Graduated' ? 'pi pi-graduation-cap' : 'pi pi-user-minus';
};

const openModal = (status) => {
    modalTitle.value = `${status} Students`;
    showModal.value = true;
};

const confirmRecover = (item, type) => {
    selectedItem.value = item;
    recoverType.value = type;
    confirmDialog.value = true;
};

const recoverItem = () => {
    if (recoverType.value === 'section') {
        archivedSections.value = archivedSections.value.filter(s => s.id !== selectedItem.value.id);
    } else if (recoverType.value === 'student') {
        archivedStudents.value = archivedStudents.value.filter(s => s.id !== selectedItem.value.id);
    }

    toast.add({ severity: 'success', summary: 'Recovered', detail: `${selectedItem.value.name} has been recovered`, life: 3000 });
    confirmDialog.value = false;
    showModal.value = false;
};

// ✅ **Fix: Define the `statusClass` function**
const statusClass = (status) => {
    return status === 'Graduated' ? 'bg-green-500 text-white' : 'bg-red-500 text-white';
};
</script>

<style scoped>
/* Archive Wrapper */
.archive-wrapper {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    position: relative;
    overflow: hidden;
}

/* Animated Background */
.background-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 0;
}

.floating-shape {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    animation: float 6s ease-in-out infinite;
}

.shape-1 {
    width: 80px;
    height: 80px;
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}

.shape-2 {
    width: 120px;
    height: 120px;
    top: 20%;
    right: 15%;
    animation-delay: 1s;
}

.shape-3 {
    width: 60px;
    height: 60px;
    bottom: 30%;
    left: 20%;
    animation-delay: 2s;
}

.shape-4 {
    width: 100px;
    height: 100px;
    bottom: 20%;
    right: 25%;
    animation-delay: 3s;
}

.shape-5 {
    width: 90px;
    height: 90px;
    top: 50%;
    left: 50%;
    animation-delay: 4s;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0px) rotate(0deg);
        opacity: 0.7;
    }
    50% {
        transform: translateY(-20px) rotate(180deg);
        opacity: 1;
    }
}

/* Content Container */
.content-container {
    position: relative;
    z-index: 1;
    padding: 2rem;
    max-width: 1200px;
    margin: 0 auto;
}

/* Archive Header */
.archive-header {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.title-section {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.icon-wrapper {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(238, 90, 36, 0.3);
}

.animated-icon {
    font-size: 2.5rem;
    color: white;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}

.title-text h1 {
    font-size: 3rem;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.text-gradient {
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 1.2rem;
    margin: 0.5rem 0 0 0;
}

.header-stats {
    display: flex;
    gap: 1rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
    border: 1px solid rgba(255, 255, 255, 0.3);
    min-width: 100px;
}

.stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.8);
    margin-top: 0.5rem;
}

/* Search Section */
.search-section {
    margin-bottom: 2rem;
}

.search-container {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-wrapper {
    position: relative;
    flex: 1;
    max-width: 600px;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.2rem;
    z-index: 2;
}

.modern-search {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    color: white;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.modern-search::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.modern-search:focus {
    outline: none;
    border-color: rgba(255, 255, 255, 0.4);
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.2);
}

.search-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 15px;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.search-wrapper:hover .search-glow {
    opacity: 1;
}

.filter-btn {
    padding: 1rem;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    color: white;
    transition: all 0.3s ease;
}

.filter-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

/* Archive Categories */
.archive-categories {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.category-section {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.category-header {
    margin-bottom: 1.5rem;
}

.category-title {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.category-icon {
    font-size: 1.5rem;
    color: #ff6b6b;
}

.category-title h2 {
    font-size: 1.8rem;
    font-weight: 600;
    color: white;
    margin: 0;
}

.count-badge {
    background: linear-gradient(135deg, #ff6b6b, #ee5a24);
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(238, 90, 36, 0.3);
}

/* Cards Grid */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.student-categories {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
}

/* Archive Cards */
.archive-card {
    position: relative;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 1.5rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: all 0.3s ease;
    overflow: hidden;
}

.archive-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
}

.card-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.archive-card:hover .card-background {
    opacity: 1;
}

.card-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.card-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
    box-shadow: 0 8px 25px rgba(79, 172, 254, 0.3);
}

.graduated-icon {
    background: linear-gradient(135deg, #56ab2f, #a8e6cf);
    box-shadow: 0 8px 25px rgba(86, 171, 47, 0.3);
}

.dropped-icon {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    box-shadow: 0 8px 25px rgba(255, 65, 108, 0.3);
}

.card-info {
    flex: 1;
}

.card-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: white;
    margin: 0 0 0.5rem 0;
}

.card-description {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
    margin: 0 0 1rem 0;
}

.card-date {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
}

.student-count {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}

.count-number {
    font-size: 2rem;
    font-weight: 700;
    color: white;
}

.count-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

.card-arrow {
    color: rgba(255, 255, 255, 0.6);
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.archive-card:hover .card-arrow {
    color: white;
    transform: translateX(5px);
}

.action-btn {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.view-btn {
    background: rgba(79, 172, 254, 0.2);
    color: #4facfe;
    border: 1px solid rgba(79, 172, 254, 0.3);
}

.view-btn:hover {
    background: #4facfe;
    color: white;
    transform: scale(1.1);
}

.recover-btn {
    background: rgba(86, 171, 47, 0.2);
    color: #56ab2f;
    border: 1px solid rgba(86, 171, 47, 0.3);
}

.recover-btn:hover {
    background: #56ab2f;
    color: white;
    transform: scale(1.1);
}

.card-glow {
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, rgba(79, 172, 254, 0.3), rgba(0, 242, 254, 0.3));
    border-radius: 22px;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
}

.graduated-glow {
    background: linear-gradient(135deg, rgba(86, 171, 47, 0.3), rgba(168, 230, 207, 0.3));
}

.dropped-glow {
    background: linear-gradient(135deg, rgba(255, 65, 108, 0.3), rgba(255, 75, 43, 0.3));
}

.archive-card:hover .card-glow {
    opacity: 1;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: rgba(255, 255, 255, 0.6);
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.5rem;
    margin: 0 0 0.5rem 0;
    color: rgba(255, 255, 255, 0.8);
}

.empty-state p {
    margin: 0;
    font-size: 1rem;
}

/* Modal Styles */
:deep(.student-modal .p-dialog) {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

:deep(.student-modal .p-dialog-header) {
    background: transparent;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.modal-title-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modal-icon {
    font-size: 1.5rem;
    color: #667eea;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #333;
}

.student-count-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: 600;
}

.modal-content {
    padding: 1rem 1.5rem;
}

.students-grid {
    display: grid;
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
}

.student-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 15px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.student-avatar {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
}

.student-info {
    flex: 1;
}

.student-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: #333;
}

.student-details {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    width: fit-content;
}

.status-graduated {
    background: rgba(86, 171, 47, 0.2);
    color: #56ab2f;
    border: 1px solid rgba(86, 171, 47, 0.3);
}

.status-dropped {
    background: rgba(255, 65, 108, 0.2);
    color: #ff416c;
    border: 1px solid rgba(255, 65, 108, 0.3);
}

.archive-date {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    color: #666;
    font-size: 0.8rem;
}

.student-actions {
    display: flex;
    gap: 0.5rem;
}

/* Confirm Modal */
:deep(.confirm-modal .p-dialog) {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.confirm-header {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.warning-icon {
    font-size: 1.5rem;
    color: #ff9500;
}

.confirm-header h3 {
    margin: 0;
    font-size: 1.3rem;
    font-weight: 600;
    color: #333;
}

.confirm-content {
    padding: 1rem 0;
}

.confirm-message p {
    margin: 0 0 1rem 0;
    color: #666;
    font-size: 1rem;
}

.item-preview {
    background: rgba(102, 126, 234, 0.1);
    padding: 0.8rem;
    border-radius: 10px;
    border-left: 4px solid #667eea;
}

.item-preview strong {
    color: #667eea;
    font-weight: 600;
}

.confirm-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem 0 0 0;
}

.cancel-btn {
    background: transparent;
    color: #666;
    border: 1px solid #ddd;
    padding: 0.8rem 1.5rem;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.cancel-btn:hover {
    background: #f5f5f5;
    color: #333;
}

.confirm-btn {
    background: linear-gradient(135deg, #56ab2f, #a8e6cf);
    color: white;
    border: none;
    padding: 0.8rem 1.5rem;
    border-radius: 10px;
    transition: all 0.3s ease;
}

.confirm-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(86, 171, 47, 0.3);
}

/* Responsive Design */
@media (max-width: 768px) {
    .content-container {
        padding: 1rem;
    }
    
    .header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .title-section {
        flex-direction: column;
        text-align: center;
    }
    
    .title-text h1 {
        font-size: 2rem;
    }
    
    .cards-grid {
        grid-template-columns: 1fr;
    }
    
    .student-categories {
        grid-template-columns: 1fr;
    }
    
    .search-container {
        flex-direction: column;
    }
    
    .search-wrapper {
        max-width: 100%;
    }
}
</style>
