# Custom Modal Component

## Overview

This CustomModal component serves as a replacement for PrimeVue's Dialog component, providing a consistent appearance across your application while avoiding conflicts with global styles that may be imposed by Laravel integration.

## Usage

### Basic Usage

```vue
<template>
  <CustomModal
    v-model="showModal"
    header="My Modal Title"
    width="400px"
  >
    <p>This is the modal content.</p>
    
    <template #footer>
      <Button label="Cancel" @click="showModal = false" />
      <Button label="Save" @click="saveData" class="p-button-success" />
    </template>
  </CustomModal>
</template>

<script setup>
import { ref } from 'vue';
import CustomModal from '@/components/custom/CustomModal.vue';
import Button from 'primevue/button';

const showModal = ref(false);

function saveData() {
  // Save logic here
  showModal.value = false;
}
</script>
```

### Props

| Prop Name          | Type      | Default     | Description                                        |
|--------------------|-----------|-------------|----------------------------------------------------|
| modelValue         | Boolean   | (required)  | Controls the visibility of the modal               |
| header             | String    | ''          | Modal title text                                   |
| width              | String    | '500px'     | Width of the modal                                 |
| maxWidth           | String    | '90vw'      | Maximum width (for responsive behavior)            |
| closeOnOverlayClick| Boolean   | true        | Whether clicking the overlay closes the modal      |
| showCloseButton    | Boolean   | true        | Whether to show the close button in header         |
| customClass        | String    | ''          | Additional CSS class to apply to the modal         |
| customStyle        | Object    | {}          | Additional inline styles to apply to the modal     |

### Slots

- **default**: The main content of the modal
- **footer**: Optional footer content, typically containing action buttons

## Migrating from PrimeVue Dialog

### PrimeVue Dialog:

```vue
<Dialog 
  v-model:visible="displayModal" 
  :modal="true" 
  header="Modal Title" 
  :style="{ width: '450px' }"
>
  <p>Content here</p>
  
  <template #footer>
    <Button label="Cancel" @click="displayModal = false" />
    <Button label="Save" @click="save" />
  </template>
</Dialog>
```

### Equivalent with CustomModal:

```vue
<CustomModal 
  v-model="displayModal" 
  header="Modal Title" 
  width="450px"
>
  <p>Content here</p>
  
  <template #footer>
    <Button label="Cancel" @click="displayModal = false" />
    <Button label="Save" @click="save" />
  </template>
</CustomModal>
```

## Key Differences from PrimeVue Dialog

1. **v-model vs v-model:visible**: 
   - CustomModal uses `v-model` instead of `v-model:visible`

2. **Width setting**:
   - CustomModal uses `width="450px"` instead of `:style="{ width: '450px' }"`

3. **No modal prop**:
   - The CustomModal is always modal by design, so there's no need for a `:modal="true"` prop

4. **Additional features**:
   - `closeOnOverlayClick` - Control whether clicking outside closes the modal
   - `showCloseButton` - Option to hide the close button
   - Built-in animations that work consistently

## Benefits

- Consistent styling across the application
- No conflicts with global CSS from Laravel integration
- Better z-index management
- Proper cleanup of event listeners
- Smooth animations 
