// src/main.js
import '@/assets/css/App.css'; // Import our custom CSS fixes
import '@/assets/styles.scss'; // Ensure this path is correct
import Aura from '@primevue/themes/aura'; // Import the Aura theme
import 'primeicons/primeicons.css';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import DialogService from 'primevue/dialogservice';

import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
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
    },
    ripple: true,
    inputStyle: 'filled',
    zIndex: {
        modal: 1200,        // Match App.css
        overlay: 1100,      // Match App.css
        menu: 1000,
        tooltip: 1100,
        toast: 1300         // Keep toast above modal
    }
});
app.use(ToastService);
app.use(DialogService);
app.use(ConfirmationService);

// Register PrimeVue components
app.component('Dialog', Dialog);
app.component('Button', Button);
app.component('InputText', InputText);
app.component('Textarea', Textarea);
app.component('InputNumber', InputNumber);
app.component('Dropdown', Dropdown);

app.mount('#app');
