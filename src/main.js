// src/main.js
import '@/assets/css/App.css'; // Import our custom CSS fixes
import '@/assets/styles.scss'; // Ensure this path is correct
import Aura from '@primevue/themes/aura'; // Import the Aura theme
import axios from 'axios';
import 'primeicons/primeicons.css';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import DialogService from 'primevue/dialogservice';

// Configure axios globally
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import '@/assets/css/global-overrides.css'; // Import global overrides last to ensure they take precedence
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
import CustomDialog from './components/CustomDialog.vue';
import router from './router';
import TeacherAuthService from './services/TeacherAuthService';

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
        modal: 1200, // Match App.css
        overlay: 1100, // Match App.css
        menu: 1000,
        tooltip: 1100,
        toast: 1300 // Keep toast above modal
    }
});
app.use(ToastService);
app.use(DialogService);
app.use(ConfirmationService);

// Register PrimeVue components
app.component('Dialog', Dialog);
app.component('CustomDialog', CustomDialog);
app.component('Button', Button);
app.component('InputText', InputText);
app.component('Textarea', Textarea);
app.component('InputNumber', InputNumber);
app.component('Dropdown', Dropdown);

// Initialize teacher authentication on app start
TeacherAuthService.initializeAuth();

app.mount('#app');
