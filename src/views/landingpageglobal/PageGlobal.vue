<template>
  <div class="landing-page">
    <div class="content">
      <div class="left-section">
        <h1 class="title">Welcome.</h1>
        <div class="search-box">
          <input type="text" placeholder="Search..." />
          <button class="search-btn">
            <i class="pi pi-search"></i>
          </button>
        </div>
        <div class="action-buttons">
          <button class="free-trial-btn">FREE TRIAL</button>
          <button class="see-more-btn">see more</button>
        </div>
      </div>
      <div class="right-section">
        <div class="logo-container">
          <img src="../../assets/images/logo.png" alt="Logo" class="logo" />
          <h2>Landing page.</h2>
          <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>
        </div>
        <div class="wave-animation"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Component logic here
</script>

<style scoped>
.landing-page {
  width: 100%;
  min-height: calc(100vh - 80px);
  background-color: #000;
  color: white;
  padding: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.content {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 4rem;
}

.left-section {
  flex: 1;
  padding-right: 4rem;
}

.title {
  font-size: 4rem;
  font-weight: 700;
  margin-bottom: 2rem;
}

.search-box {
  display: flex;
  gap: 1rem;
  margin-bottom: 2rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 0.5rem;
  border-radius: 30px;
}

.search-box input {
  flex: 1;
  background: transparent;
  border: none;
  color: white;
  padding: 0.5rem 1rem;
  font-size: 1rem;
}

.search-box input::placeholder {
  color: rgba(255, 255, 255, 0.7);
}

.search-btn {
  background: transparent;
  border: none;
  color: white;
  cursor: pointer;
  padding: 0.5rem 1rem;
}

.action-buttons {
  display: flex;
  gap: 1rem;
}

.free-trial-btn {
  background: #81BFDA;
  color: white;
  border: none;
  padding: 0.8rem 2rem;
  border-radius: 25px;
  cursor: pointer;
  font-weight: 500;
  transition: background-color 0.3s ease;
}

.see-more-btn {
  background: transparent;
  color: white;
  border: 1px solid white;
  padding: 0.8rem 2rem;
  border-radius: 25px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
}

.right-section {
  flex: 1;
  position: relative;
}

.logo-container {
  text-align: right;
  position: relative;
  z-index: 2;
}

.logo-container h2 {
  font-size: 2rem;
  margin-bottom: 1rem;
}

.logo-container p {
  color: rgba(255, 255, 255, 0.7);
  line-height: 1.6;
}

.wave-animation {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 400px;
  height: 400px;
  background: linear-gradient(45deg, #81BFDA, #6366f1);
  border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
  animation: morph 8s ease-in-out infinite;
  z-index: 1;
  opacity: 0.5;
}

@keyframes morph {
  0% {
    border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
  }
  50% {
    border-radius: 60% 40% 30% 70% / 50% 60% 40% 50%;
  }
  100% {
    border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%;
  }
}
</style>
