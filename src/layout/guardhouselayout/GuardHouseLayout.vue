<script setup>
import { useLayout } from '@/layout/composables/layout';
import GuestTopbar from '@/layout/guestlayout/GuestTopbar.vue';
import { ref, watch } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';

const { layoutState, isSidebarActive } = useLayout();
const outsideClickListener = ref(null);
const scanning = ref(false);

watch(isSidebarActive, (newVal) => {
    if (newVal) {
        bindOutsideClickListener();
    } else {
        unbindOutsideClickListener();
    }
});

function bindOutsideClickListener() {
    if (!outsideClickListener.value) {
        outsideClickListener.value = (event) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive = false;
                layoutState.staticMenuMobileActive = false;
                layoutState.menuHoverActive = false;
            }
        };
        document.addEventListener('click', outsideClickListener.value);
    }
}

function unbindOutsideClickListener() {
    if (outsideClickListener.value) {
        document.removeEventListener('click', outsideClickListener);
        outsideClickListener.value = null;
    }
}

function isOutsideClicked(event) {
    const topbarEl = document.querySelector('.layout-menu-button');
    return !(topbarEl && (topbarEl.isSameNode(event.target) || topbarEl.contains(event.target)));
}

const onDetect = (detectedCodes) => {
    console.log('QR Code Detected:', detectedCodes);
};

const startScan = () => {
    scanning.value = true;
};
</script>

<template>
    <div class="layout-wrapper">
        <guest-topbar></guest-topbar>
        <div class="layout-main-container">
            <div class="scanner-container">
                <qrcode-stream @detect="onDetect" class="qr-scanner" :class="{ scanning: scanning }"></qrcode-stream>
                <button @click="startScan" class="scan-button">Start Scan</button>
            </div>
        </div>
        <guest-footer></guest-footer>
        <div class="layout-mask animate-fadein"></div>
    </div>
</template>

<style lang="scss" scoped>
.scanner-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 10px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    max-width: 500px;
    margin: auto;
}

.qr-scanner {
    width: 500px;
    height: 500px;
    border: 3px solid gray;
    border-radius: 12px;
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease;
    background: #fff;
}

.scanning {
    border-color: #00ff00 !important;
    box-shadow: 0px 0px 15px 4px #00ff00;
}

.scan-button {
    margin-top: 12px;
    padding: 12px 24px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition:
        background 0.3s ease,
        transform 0.2s ease;
    font-size: 18px;
    font-weight: 600;
}

.scan-button:hover {
    background-color: #0056b3;
    transform: scale(1.05);
}
</style>
