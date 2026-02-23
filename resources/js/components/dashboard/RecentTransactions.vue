<script setup>
import { useCurrency } from '../../composables/useCurrency';

defineProps({
    transactions: {
        type: Array,
        required: true,
    },
});

const { formatCurrency } = useCurrency();
</script>

<template>
    <section class="jt-card p-4">
        <h2 class="mb-3 text-lg font-semibold">รายการล่าสุด</h2>

        <div v-if="transactions.length" class="space-y-2">
            <article v-for="item in transactions" :key="item.id" class="rounded-xl border border-emerald-100 p-3">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium">{{ item.category?.emoji }} {{ item.category?.name }}</p>
                        <p class="text-sm text-slate-500">{{ item.note || 'ไม่มีหมายเหตุ' }}</p>
                    </div>
                    <p class="font-semibold" :class="item.type === 'income' ? 'text-green-700' : 'text-red-600'">
                        {{ item.type === 'income' ? '+' : '-' }}{{ formatCurrency(item.amount) }}
                    </p>
                </div>
            </article>
        </div>
        <p v-else class="text-sm text-slate-500">ยังไม่มีรายการล่าสุด</p>
    </section>
</template>
