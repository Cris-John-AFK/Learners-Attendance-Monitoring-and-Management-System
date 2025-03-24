<template>
    <div class="qr-container">
      <qrcode-vue :value="qrData" :size="200" level="H" />
      <p>Scan this QR Code for Attendance</p>
    </div>
  </template>

  <script setup>
  import QrcodeVue from 'qrcode.vue';

  // Sample static QR data (e.g., student ID or default value)
  const qrData = 'DEFAULT-ATTENDANCE-QR';
  </script>

  <style scoped>
  .qr-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  </style>
