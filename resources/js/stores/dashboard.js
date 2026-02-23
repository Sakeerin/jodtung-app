import { ref } from 'vue';
import { defineStore } from 'pinia';
import { getChartApi, getRecentApi, getSummaryApi } from '../api/dashboard';

export const useDashboardStore = defineStore('dashboard', () => {
    const summary = ref(null);
    const chart = ref(null);
    const recent = ref([]);
    const loading = ref(false);

    const fetchDashboard = async (period) => {
        loading.value = true;

        try {
            const [{ data: summaryData }, { data: chartData }, { data: recentData }] = await Promise.all([
                getSummaryApi(period),
                getChartApi(period),
                getRecentApi(10),
            ]);

            summary.value = summaryData;
            chart.value = chartData;
            recent.value = recentData;
        } finally {
            loading.value = false;
        }
    };

    return {
        summary,
        chart,
        recent,
        loading,
        fetchDashboard,
    };
});
