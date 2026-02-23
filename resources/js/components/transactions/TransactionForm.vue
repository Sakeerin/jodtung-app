<script setup>
import { computed, reactive } from 'vue';

const props = defineProps({
    categories: {
        type: Object,
        required: true,
    },
    initialValue: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['submit', 'cancel']);

const form = reactive({
    type: props.initialValue?.type ?? 'expense',
    category_id: props.initialValue?.category_id ?? '',
    amount: props.initialValue?.amount ?? '',
    note: props.initialValue?.note ?? '',
    transaction_date: props.initialValue?.transaction_date ?? new Date().toISOString().slice(0, 10),
});

const options = computed(() => props.categories?.[form.type] ?? []);

const submit = () => {
    emit('submit', {
        ...form,
        category_id: Number(form.category_id),
        amount: Number(form.amount),
    });
};
</script>

<template>
    <form class="jt-card mb-4 space-y-3 p-4" @submit.prevent="submit">
        <h3 class="text-lg font-semibold">{{ initialValue ? 'แก้ไขรายการ' : 'เพิ่มรายการใหม่' }}</h3>

        <div class="jt-grid md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm">ประเภท</label>
                <select v-model="form.type" class="jt-select" required>
                    <option value="expense">รายจ่าย</option>
                    <option value="income">รายรับ</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm">หมวดหมู่</label>
                <select v-model="form.category_id" class="jt-select" required>
                    <option value="" disabled>เลือกหมวดหมู่</option>
                    <option v-for="category in options" :key="category.id" :value="category.id">
                        {{ category.emoji }} {{ category.name }}
                    </option>
                </select>
            </div>
        </div>

        <div class="jt-grid md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm">จำนวนเงิน</label>
                <input v-model="form.amount" type="number" min="0.01" step="0.01" class="jt-input" required>
            </div>
            <div>
                <label class="mb-1 block text-sm">วันที่</label>
                <input v-model="form.transaction_date" type="date" class="jt-input" required>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm">หมายเหตุ</label>
            <textarea v-model="form.note" class="jt-textarea" rows="2" />
        </div>

        <div class="flex gap-2">
            <button class="jt-button jt-button-primary" type="submit">บันทึก</button>
            <button class="jt-button jt-button-outline" type="button" @click="emit('cancel')">ยกเลิก</button>
        </div>
    </form>
</template>
