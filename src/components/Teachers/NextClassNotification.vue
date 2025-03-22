<template>
    <div class="card h-full">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-xl">Next Class Session</h3>
            <span class="text-sm font-medium bg-blue-100 text-blue-800 px-2 py-1 rounded">Live</span>
        </div>

        <div class="bg-white rounded-lg p-4 border-l-4 border-primary">
            <div class="flex items-center mb-3">
                <i class="pi pi-clock text-primary mr-2"></i>
                <span class="text-lg font-semibold">{{ currentTime }}</span>
            </div>

            <div class="mb-4">
                <h4 class="font-medium text-lg text-gray-800">{{ nextClass.subject }}</h4>
                <p class="text-gray-600">Grade {{ nextClass.grade }} - Section {{ nextClass.section }}</p>
                <div class="flex items-center mt-2">
                    <i class="pi pi-calendar text-gray-500 mr-2"></i>
                    <span class="text-gray-600">{{ nextClass.time }}</span>
                </div>
                <div class="flex items-center mt-1">
                    <i class="pi pi-map-marker text-gray-500 mr-2"></i>
                    <span class="text-gray-600">{{ nextClass.room }}</span>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t">
                <p class="text-sm text-gray-500">
                    <i class="pi pi-info-circle mr-1"></i>
                    Next class starts in {{ nextClass.startsIn }}
                </p>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            currentTime: '',
            nextClass: {
                subject: 'Mathematics',
                grade: '3',
                section: 'Sampaguita',
                time: '10:30 AM - 11:30 AM',
                room: 'Room 304',
                startsIn: '25 minutes'
            }
        };
    },
    mounted() {
        this.updateTime();
        setInterval(this.updateTime, 1000);
    },
    methods: {
        updateTime() {
            const now = new Date();
            this.currentTime = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
    }
};
</script>

<style scoped>
.border-primary {
    border-color: var(--primary-color);
}
.text-primary {
    color: var(--primary-color);
}
</style>
