<template>
    <header class="topbar">
        <div class="logo-container">
            <img src="../../assets/images/logo.png" alt="NCS Logo" class="logo" />
            <span class="school-name">NAAWAN CENTRAL SCHOOL</span>
        </div>
        <div class="nav-links">
            <router-link to="/about">ABOUT</router-link>
            <router-link to="/download">DOWNLOAD</router-link>
            <router-link to="/pricing">PRICING</router-link>
            <router-link to="/features">FEATURES</router-link>
            <router-link to="/contact">CONTACT</router-link>
            <router-link to="/homepage" class="sign-in-btn">MY ACCOUNT</router-link>
        </div>
    </header>
</template>

<script setup>
// Component logic here
</script>

<style scoped>
.topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background-color: #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.logo {
    height: 40px;
    width: auto;
}

.school-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.nav-links a {
    text-decoration: none;
    color: #333;
    font-weight: 500;
    transition: color 0.3s ease;
}

.nav-links a:hover {
    color: #81bfda;
}

.sign-in-btn {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: background-color 0.3s ease;
}

.sign-in-btn:hover {
    background-color: #0056b3;
}
</style>
