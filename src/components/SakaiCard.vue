<template>
    <div class="sakai-card">
        <slot></slot> <!-- Allows content to be inserted -->
    </div>
</template>

<style scoped>
.sakai-card {
    padding: 20px;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.sakai-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
}
</style>
