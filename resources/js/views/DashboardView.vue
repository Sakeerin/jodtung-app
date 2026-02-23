<script setup>
import { onMounted, ref, watch } from 'vue';
import { useDashboardStore } from '../stores/dashboard';
import PeriodFilter from '../components/dashboard/PeriodFilter.vue';
import SummaryCards from '../components/dashboard/SummaryCards.vue';
import DonutChart from '../components/dashboard/DonutChart.vue';
import RecentTransactions from '../components/dashboard/RecentTransactions.vue';

const dashboardStore = useDashboardStore();
const period = ref('summary_month');

const refresh = () => dashboardStore.fetchDashboard(period.value);

watch(period, refresh);
onMounted(refresh);
</script>

<template>
    <section>
        <PeriodFilter v-model="period" />

        <p v-if="dashboardStore.loading" class="jt-card mb-4 p-4 text-sm text-slate-500">กำลังโหลดข้อมูล...</p>

        <template v-else-if="dashboardStore.summary && dashboardStore.chart">
            <SummaryCards :summary="dashboardStore.summary" />
            <DonutChart :chart-data="dashboardStore.chart" />
            <RecentTransactions :transactions="dashboardStore.recent" />
        </template>
    </section>
</template>
