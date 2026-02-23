<script setup>
import { computed } from 'vue';
import ApexChart from 'vue3-apexcharts';
import { useCurrency } from '../../composables/useCurrency';

const props = defineProps({
    chartData: {
        type: Object,
        required: true,
    },
});

const { formatCurrency } = useCurrency();

const labels = computed(() => props.chartData.expenseByCategory.map((item) => `${item.emoji} ${item.name}`));
const series = computed(() => props.chartData.expenseByCategory.map((item) => Number(item.amount)));

const options = computed(() => ({
    chart: { type: 'donut' },
    labels: labels.value,
    colors: ['#2e7d63', '#4f9b74', '#7bbf93', '#a6d8b4', '#f1c85f', '#d96c51'],
    legend: { position: 'bottom' },
    dataLabels: {
        formatter: (value) => `${value.toFixed(0)}%`,
    },
}));
</script>

<template>
    <section class="jt-card mb-4 p-4">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold">สัดส่วนรายจ่าย</h2>
            <p class="text-sm text-slate-500">รวม {{ formatCurrency(chartData.totalExpense) }}</p>
        </div>

        <div v-if="series.length > 0">
            <ApexChart type="donut" height="280" :options="options" :series="series" />
        </div>
        <p v-else class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">ยังไม่มีข้อมูลรายจ่ายในช่วงเวลานี้</p>
    </section>
</template>
