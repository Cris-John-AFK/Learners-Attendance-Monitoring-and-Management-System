<script setup>
import AnalyticalChart from '@/components/Teachers/AnalyticalChart.vue';
import NextClassNotification from '@/components/Teachers/NextClassNotification.vue';
import StatsWidget from '@/components/Teachers/StatsWidget.vue';
import TopAbsentees from '@/components/Teachers/TopAbsentees.vue';
</script>

<template>
    <div class="grid grid-cols-12 gap-6">

        <!-- Stats Widgets -->
        <StatsWidget />

        <!-- Chart and Next Class -->
        <div class="col-span-12 xl:col-span-8">
            <AnalyticalChart />
        </div>
        <div class="col-span-12 xl:col-span-4">
            <NextClassNotification />
        </div>

        <!-- Top Absentees -->
        <div class="col-span-12">
            <TopAbsentees />
        </div>
    </div>
</template>