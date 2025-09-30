<script setup>
// Footer component
</script>

<template>
    <div class="layout-footer">
        <span class="font-medium ml-2">LAMMS brought you by Angelow Larot, Jessica Roque, & Cris John Cañales</span>
    </div>
</template>

<style lang="scss" scoped>
.layout-footer {
    padding: 1rem 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
    color: var(--text-color);
    border-top: 1px solid var(--surface-border);
}

/* Hide footer when printing */
@media print {
    .layout-footer {
        display: none !important;
    }
}
</style>
