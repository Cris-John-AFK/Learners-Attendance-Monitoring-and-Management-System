// src/main.js
import '@/assets/styles.scss'; // Ensure this path is correct
import Aura from '@primevue/themes/aura'; // Import the Aura theme
import 'primeicons/primeicons.css';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';

import Dialog from 'primevue/dialog';
import ToastService from 'primevue/toastservice';
import VCalendar from 'v-calendar';
import 'v-calendar/style.css';
import { createApp } from 'vue';
import App from './App.vue';
import router from './router';

const app = createApp(App);

app.use(VCalendar, {});
app.use(router);
app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: '.app-dark'
        }
    }
});
app.use(ToastService);
app.use(ConfirmationService);
app.component('Dialog', Dialog);

app.mount('#app');
