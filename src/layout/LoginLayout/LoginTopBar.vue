<script setup></script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <router-link to="/enrollment" class="layout-topbar-logo">
                <img src="/demo/images/logo.png" alt="Logo" />

                <span>NAAWAN CENTRAL SCHOOL</span>
            </router-link>
        </div>
        <div class="my-account-container">
            <router-link to="/my-account" class="my-account-button">
                <i class="pi pi-user mr-2"></i>
                MY ACCOUNT
            </router-link>
        </div>
    </div>
</template>

<style scoped>
.layout-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 2rem;
    background-color: #f8f9fa;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.layout-topbar-logo-container {
    display: flex;
    align-items: center;
}

.layout-topbar-logo {
    display: flex;
    align-items: center;
    color: #495057;
    font-size: 1.5rem;
    font-weight: 500;
    text-decoration: none;
}

img {
    width: 50px;
    height: 50px;
    margin-right: 0.5rem;
}

/* Top bar styling */
.top-bar {
    display: flex;
    align-items: center;
    gap: 15px;
}

.top-link {
    color: #495057;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.search-box {
    display: flex;
    overflow: hidden;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.search-box input {
    padding: 5px 10px;
    border: none;
    outline: none;
    font-size: 14px;
    width: 150px;
    background-color: white;
    color: #495057;
}

.search-box button {
    background: #f0f0f0;
    border: none;
    padding: 5px 10px;
    cursor: pointer;
    color: #495057;
}

.account-btn {
    background-color: #ff7518;
    color: white;
    border: none;
    padding: 6px 12px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.account-btn i {
    font-size: 16px;
}

.my-account-container {
    display: flex;
    align-items: center;
}

.my-account-button {
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    background-color: #495057;
    color: white;
    border-radius: 4px;
    text-decoration: none;
    transition: background-color 0.3s ease;
    font-weight: 500;
}

.my-account-button:hover {
    background-color: #343a40;
}

.my-account-button i {
    font-size: 1.2rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .layout-topbar {
        flex-direction: column;
        padding: 0.5rem;
    }

    .layout-topbar-logo-container {
        margin-bottom: 0.5rem;
    }

    .top-bar {
        flex-wrap: wrap;
        justify-content: center;
        width: 100%;
    }

    .search-box {
        order: 3;
        width: 100%;
        margin: 10px 0;
    }

    .search-box input {
        width: 100%;
    }
}
</style>
