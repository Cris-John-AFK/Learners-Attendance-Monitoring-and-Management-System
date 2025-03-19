<template>
    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card mb-0">
            <div class="flex justify-between mb-4">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Total Absentees</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">3</div>
                </div>
                <div class="flex items-center justify-center bg-orange-100 dark:bg-blue-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                    <i class="pi pi-user-minus text-red-500 !text-xl"></i>
                </div>
            </div>
            <span class="text-primary font-medium">as of {{ formattedDate }}</span>
            <span class="text-muted-color"></span>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card mb-0">
            <div class="flex justify-between mb-4">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Total Presentees</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">9</div>
                </div>
                <div class="flex items-center justify-center bg-green-100 dark:bg-orange-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                    <i class="pi pi-user-plus text-green-500 !text-xl"></i>
                </div>
            </div>
            <span class="text-primary font-medium">as of {{ formattedDate }}</span>
            <span class="text-muted-color"> </span>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card mb-0">
            <div class="flex justify-between mb-4">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Total Students</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">12</div>
                </div>
                <div class="flex items-center justify-center bg-cyan-100 dark:bg-cyan-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                    <i class="pi pi-users text-cyan-500 !text-xl"></i>
                </div>
            </div>
            <span class="text-primary font-medium">&nbsp;</span>
            <span class="text-muted-color">&nbsp;</span>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card mb-0">
            <div class="flex justify-between mb-4">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Current Sections</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">3</div>
                </div>
                <div class="flex items-center justify-center bg-purple-100 dark:bg-purple-400/10 rounded-border" style="width: 2.5rem; height: 2.5rem">
                    <i class="pi pi-user text-purple-500 !text-xl"></i>
                </div>
            </div>
            <span class="text-primary font-medium">&nbsp;</span>
            <span class="text-muted-color">&nbsp;</span>
        </div>
    </div>
</template>

<script>
export default {
    computed: {
        formattedDate() {
            const today = new Date();
            const month = today.getMonth() + 1; // Months are 0-based
            const day = today.getDate();
            const year = today.getFullYear();
            return `${month}/${day}/${year}`;
        }
    }
};
</script>
