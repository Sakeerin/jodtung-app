<script setup>
import { useCurrency } from '../../composables/useCurrency';

defineProps({
    summary: {
        type: Object,
        required: true,
    },
});

const { formatCurrency } = useCurrency();
</script>

<template>
    <section class="jt-grid mb-4 md:grid-cols-3">
        <article class="jt-card p-4">
            <p class="text-sm text-slate-500">รายรับ</p>
            <p class="mt-2 text-2xl font-semibold text-green-700">{{ formatCurrency(summary.totalIncome) }}</p>
        </article>
        <article class="jt-card p-4">
            <p class="text-sm text-slate-500">รายจ่าย</p>
            <p class="mt-2 text-2xl font-semibold text-red-600">{{ formatCurrency(summary.totalExpense) }}</p>
        </article>
        <article class="jt-card p-4">
            <p class="text-sm text-slate-500">คงเหลือ</p>
            <p class="mt-2 text-2xl font-semibold" :class="summary.balance >= 0 ? 'text-emerald-700' : 'text-red-700'">
                {{ formatCurrency(summary.balance) }}
            </p>
        </article>
    </section>
</template>
