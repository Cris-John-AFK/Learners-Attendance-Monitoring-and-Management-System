<script setup>
import AnalyticalChart from '@/components/Teachers/AnalyticalChart.vue';
import StatsWidget from '@/components/Teachers/StatsWidget.vue';
</script>

<template>
    <div class="grid grid-cols-12 gap-8">
        <StatsWidget />
        <div class="col-span-12 xl:col-span-12">
            <AnalyticalChart />
        </div>
    </div>
</template>
