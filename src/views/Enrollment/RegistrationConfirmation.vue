<template>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="success-icon">
                <i class="pi pi-check-circle"></i>
            </div>

            <h1>Registration Successful!</h1>
            <p>Thank you for registering with our school. Your application has been submitted successfully.</p>

            <div class="confirmation-details">
                <h2>What's Next?</h2>
                <ul>
                    <li>
                        <i class="pi pi-envelope"></i>
                        <div>
                            <h3>Check Your Messages</h3>
                            <p>We've sent a confirmation text to contact details you've provided.</p>
                        </div>
                    </li>
                    <li>
                        <i class="pi pi-phone"></i>
                        <div>
                            <h3>Expect a Call</h3>
                            <p>Our admissions team will contact you within 2-3 business days.</p>
                        </div>
                    </li>
                    <li>
                        <i class="pi pi-file"></i>
                        <div>
                            <h3>Prepare Documents</h3>
                            <p>Please prepare the required documents for enrollment verification.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="confirmation-actions">
                <Button label="Back to Home" icon="pi pi-home" class="p-button-outlined" @click="goToHome" />
                <Button label="Print Confirmation" icon="pi pi-print" @click="printConfirmation" />
            </div>
        </div>
    </div>
</template>

<script setup>
import Button from 'primevue/button';
import { useRouter } from 'vue-router';

const router = useRouter();

const goToHome = () => {
    router.push('/');
};

const printConfirmation = () => {
    window.print();
};
</script>

<style scoped>
.confirmation-container {
    min-height: calc(100vh - 4rem);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 2rem;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
}

.confirmation-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 700px;
    padding: 3rem 2rem;
    text-align: center;
    animation: card-fade-in 0.6s ease-out;
}

@keyframes card-fade-in {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.success-icon {
    font-size: 5rem;
    color: #22c55e;
    margin-bottom: 1.5rem;
    animation: success-pulse 2s infinite;
}

@keyframes success-pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
    }
}

h1 {
    color: #1e293b;
    font-size: 2rem;
    margin-bottom: 1rem;
}

p {
    color: #64748b;
    font-size: 1.1rem;
    max-width: 600px;
    margin: 0 auto 2rem;
}

.confirmation-details {
    background-color: #f8fafc;
    border-radius: 12px;
    padding: 2rem;
    margin-bottom: 2rem;
    text-align: left;
}

.confirmation-details h2 {
    color: #3b82f6;
    font-size: 1.5rem;
    margin-top: 0;
    margin-bottom: 1.5rem;
    text-align: center;
}

.confirmation-details ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.confirmation-details li {
    display: flex;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}

.confirmation-details li:last-child {
    margin-bottom: 0;
}

.confirmation-details i {
    font-size: 1.5rem;
    color: #3b82f6;
    margin-right: 1rem;
    margin-top: 0.25rem;
}

.confirmation-details h3 {
    margin: 0 0 0.5rem;
    color: #1e293b;
    font-size: 1.2rem;
}

.confirmation-details p {
    margin: 0;
    font-size: 1rem;
}

.confirmation-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
}

@media (max-width: 768px) {
    .confirmation-card {
        padding: 2rem 1.5rem;
    }

    .confirmation-actions {
        flex-direction: column;
        gap: 0.75rem;
    }

    .confirmation-actions button {
        width: 100%;
    }
}

@media print {
    .confirmation-actions {
        display: none;
    }
}
</style>
