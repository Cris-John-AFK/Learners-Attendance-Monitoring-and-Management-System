<template>
    <div :class="[
        'sticky-note p-4 rounded-lg border-l-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer',
        colorClass,
        note.is_pinned ? 'ring-2 ring-blue-200' : ''
    ]">
        <!-- Note Header -->
        <div class="flex items-start justify-between mb-2">
            <div class="flex-1">
                <h4 class="font-medium text-gray-900 text-sm line-clamp-1">
                    {{ note.title }}
                </h4>
                <p v-if="note.student" class="text-xs text-gray-600 mt-1">
                    👨‍🎓 {{ formatStudentName(note.student) }}
                </p>
            </div>
            
            <div class="flex items-center space-x-1 ml-2">
                <!-- Pin indicator -->
                <i v-if="note.is_pinned" 
                   class="pi pi-thumbtack text-blue-600 text-xs" 
                   title="Pinned"></i>
                
                <!-- Reminder indicator -->
                <i v-if="hasReminder" 
                   :class="[
                       'pi pi-clock text-xs',
                       isReminderDue ? 'text-red-600' : 'text-orange-600'
                   ]" 
                   :title="reminderText"></i>
                
                <!-- Actions dropdown -->
                <div class="relative">
                    <Button @click="showActions = !showActions" 
                            icon="pi pi-ellipsis-v" 
                            class="p-button-text p-button-sm w-6 h-6" 
                            style="padding: 0;" />
                    
                    <div v-if="showActions" 
                         class="absolute right-0 top-8 bg-white border border-gray-200 rounded-lg shadow-lg z-10 min-w-32">
                        <button @click="editNote" 
                                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center">
                            <i class="pi pi-pencil mr-2 text-xs"></i>Edit
                        </button>
                        <button @click="togglePin" 
                                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center">
                            <i :class="['mr-2 text-xs', note.is_pinned ? 'pi pi-times' : 'pi pi-thumbtack']"></i>
                            {{ note.is_pinned ? 'Unpin' : 'Pin' }}
                        </button>
                        <button @click="deleteNote" 
                                class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 text-red-600 flex items-center">
                            <i class="pi pi-trash mr-2 text-xs"></i>Archive
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Note Content -->
        <div class="mb-3">
            <p class="text-sm text-gray-700 line-clamp-3">
                {{ note.content }}
            </p>
        </div>

        <!-- Note Footer -->
        <div class="flex items-center justify-between text-xs text-gray-500">
            <span>{{ formatDate(note.created_at) }}</span>
            <span v-if="hasReminder" class="text-orange-600 font-medium">
                📅 {{ reminderText }}
            </span>
        </div>

        <!-- Click outside to close actions -->
        <div v-if="showActions" 
             @click="showActions = false" 
             class="fixed inset-0 z-0"></div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import TeacherNotesService from '@/services/TeacherNotesService';

// Props
const props = defineProps({
    note: {
        type: Object,
        required: true
    }
});

// Emits
const emit = defineEmits(['edit', 'delete', 'togglePin']);

// Reactive data
const showActions = ref(false);

// Computed properties
const colorClass = computed(() => {
    return TeacherNotesService.getColorClass(props.note.color);
});

const hasReminder = computed(() => {
    return TeacherNotesService.hasReminder(props.note);
});

const isReminderDue = computed(() => {
    return TeacherNotesService.isReminderDueSoon(props.note);
});

const reminderText = computed(() => {
    return TeacherNotesService.formatReminderDate(props.note.reminder_date);
});

// Methods
const formatStudentName = (student) => {
    if (!student) return '';
    return `${student.firstName || ''} ${student.lastName || ''}`.trim();
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays <= 7) return `${diffDays} days ago`;
    
    return date.toLocaleDateString();
};

const editNote = () => {
    showActions.value = false;
    emit('edit', props.note);
};

const deleteNote = () => {
    showActions.value = false;
    emit('delete', props.note);
};

const togglePin = () => {
    showActions.value = false;
    emit('togglePin', props.note);
};
</script>

<style scoped>
.sticky-note {
    position: relative;
}

.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Color classes for different note colors */
.bg-yellow-100 {
    background-color: #fef3c7;
    border-left-color: #f59e0b;
}

.bg-blue-100 {
    background-color: #dbeafe;
    border-left-color: #3b82f6;
}

.bg-pink-100 {
    background-color: #fce7f3;
    border-left-color: #ec4899;
}

.bg-green-100 {
    background-color: #d1fae5;
    border-left-color: #10b981;
}

.bg-orange-100 {
    background-color: #fed7aa;
    border-left-color: #f97316;
}

.bg-purple-100 {
    background-color: #e9d5ff;
    border-left-color: #8b5cf6;
}
</style>
