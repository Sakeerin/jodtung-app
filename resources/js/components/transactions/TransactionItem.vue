<script setup>
import { useCurrency } from '../../composables/useCurrency';

defineProps({
    transaction: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['edit', 'delete']);
const { formatCurrency } = useCurrency();
</script>

<template>
    <article class="jt-card p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="font-medium">{{ transaction.category?.emoji }} {{ transaction.category?.name }}</p>
                <p class="text-sm text-slate-500">{{ transaction.note || 'ไม่มีหมายเหตุ' }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ transaction.transaction_date }}</p>
            </div>

            <div class="text-right">
                <p class="text-lg font-semibold" :class="transaction.type === 'income' ? 'text-green-700' : 'text-red-600'">
                    {{ transaction.type === 'income' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                </p>
                <div class="mt-2 flex justify-end gap-2">
                    <button class="jt-button jt-button-outline" type="button" @click="emit('edit', transaction)">แก้ไข</button>
                    <button class="jt-button border border-red-200 bg-red-50 text-red-600" type="button" @click="emit('delete', transaction)">ลบ</button>
                </div>
            </div>
        </div>
    </article>
</template>
