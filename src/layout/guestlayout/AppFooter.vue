<script setup>
// Footer component
</script>

<template>
    <div class="layout-footer">
        <div class="footer-content">
            <span class="footer-text">LAMMS brought you by Angelow Larot, Jessica Roque, & Cris John Cañales</span>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.layout-footer {
    padding: 1.5rem 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
    color: var(--text-color);
    border-top: 1px solid var(--surface-border);
    width: 100%;
    background-color: var(--surface-card);
    position: relative;
    right: 20px;
}

.footer-content {
    width: 100%;
    text-align: center;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
}

.footer-text {
    font-weight: 500;
    font-size: 0.95rem;
    color: var(--text-color-secondary);
    white-space: nowrap;
}

@media (max-width: 640px) {
    .layout-footer {
        padding: 1rem 0;
    }

    .footer-text {
        font-size: 0.85rem;
        white-space: normal;
        padding: 0 1rem;
    }
}
</style>
