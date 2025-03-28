<template>
  <div class="custom-dialog-wrapper">
    <Dialog 
      v-bind="$attrs" 
      v-model:visible="localVisible"
      :style="{ 'z-index': 900 }"
      class="custom-dialog"
    >
      <template v-for="(_, name) in $slots" :key="name" #[name]="slotData">
        <slot :name="name" v-bind="slotData || {}" />
      </template>
    </Dialog>
  </div>
</template>

<script>
import Dialog from 'primevue/dialog';

export default {
  name: 'CustomDialog',
  components: {
    Dialog
  },
  props: {
    visible: {
      type: Boolean,
      required: true
    }
  },
  emits: ['update:visible'],
  computed: {
    localVisible: {
      get() {
        return this.visible;
      },
      set(value) {
        this.$emit('update:visible', value);
      }
    }
  }
};
</script>

<style scoped>
.custom-dialog-wrapper {
  position: relative;
  z-index: 900;
}

.custom-dialog :deep(.p-dialog) {
  z-index: 900 !important;
}

/* Ensure dropdown menus appear above dialogs */
:deep(.p-dropdown-panel) {
  z-index: 9999 !important;
  position: fixed !important;
}
</style>
