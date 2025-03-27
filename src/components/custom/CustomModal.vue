<template>
    <Teleport to="body">
        <transition name="modal-fade">
            <div v-if="modelValue" class="custom-modal-overlay" @click="closeOnOverlayClick ? $emit('update:modelValue', false) : null">
                <div :class="['custom-modal', customClass]" :style="computedStyles" @click.stop>
                    <div class="custom-modal-header">
                        <h3 class="custom-modal-title">{{ header }}</h3>
                        <button v-if="showCloseButton" class="custom-modal-close" @click="$emit('update:modelValue', false)">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                    <div class="custom-modal-content">
                        <slot></slot>
                    </div>
                    <div v-if="$slots.footer" class="custom-modal-footer">
                        <slot name="footer"></slot>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        required: true
    },
    header: {
        type: String,
        default: ''
    },
    width: {
        type: String,
        default: '500px'
    },
    maxWidth: {
        type: String,
        default: '90vw'
    },
    closeOnOverlayClick: {
        type: Boolean,
        default: true
    },
    showCloseButton: {
        type: Boolean,
        default: true
    },
    customClass: {
        type: String,
        default: ''
    },
    customStyle: {
        type: Object,
        default: () => ({})
    }
});

defineEmits(['update:modelValue']);

const computedStyles = computed(() => {
    return {
        width: props.width,
        maxWidth: props.maxWidth,
        ...props.customStyle
    };
});
</script>

<style scoped>
.custom-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1100;
}

.custom-modal {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    min-width: 300px;
    margin: auto;
}

.custom-modal-header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.custom-modal-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.custom-modal-close {
    background: none;
    border: none;
    font-size: 1rem;
    cursor: pointer;
    padding: 0.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
    border-radius: 50%;
    width: 1.5rem;
    height: 1.5rem;
}

.custom-modal-close:hover {
    background-color: #f8f9fa;
    color: #343a40;
}

.custom-modal-content {
    padding: 1rem;
    max-height: calc(80vh - 6rem);
    overflow-y: auto;
}

.custom-modal-footer {
    padding: 0.75rem 1rem;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* Transitions */
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.2s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.modal-fade-enter-active .custom-modal {
    animation: modal-in 0.3s ease-out forwards;
}

.modal-fade-leave-active .custom-modal {
    animation: modal-out 0.2s ease-in forwards;
}

@keyframes modal-in {
    0% {
        transform: translateY(-20px);
        opacity: 0;
    }
    100% {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes modal-out {
    0% {
        transform: translateY(0);
        opacity: 1;
    }
    100% {
        transform: translateY(-20px);
        opacity: 0;
    }
}
</style>
