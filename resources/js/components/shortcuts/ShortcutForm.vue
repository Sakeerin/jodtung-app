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
    keyword: props.initialValue?.keyword ?? '',
    emoji: props.initialValue?.emoji ?? '',
    type: props.initialValue?.type ?? 'expense',
    category_id: props.initialValue?.category_id ?? '',
});

const categoryOptions = computed(() => props.categories?.[form.type] ?? []);

const submit = () => {
    emit('submit', {
        keyword: form.keyword.trim(),
        emoji: form.emoji.trim(),
        type: form.type,
        category_id: Number(form.category_id),
    });
};
</script>

<template>
    <form class="jt-card mb-4 space-y-3 p-4" @submit.prevent="submit">
        <h3 class="text-lg font-semibold">{{ initialValue ? 'แก้ไขคำสั่งลัด' : 'เพิ่มคำสั่งลัด' }}</h3>

        <div class="jt-grid md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm">คำสั่งลัด</label>
                <input v-model="form.keyword" class="jt-input" placeholder="เช่น กาแฟ" required>
            </div>
            <div>
                <label class="mb-1 block text-sm">Emoji (ไม่บังคับ)</label>
                <input v-model="form.emoji" class="jt-input" placeholder="เช่น ☕">
            </div>
        </div>

        <div class="jt-grid md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm">ประเภทรายการ</label>
                <select v-model="form.type" class="jt-select" required>
                    <option value="expense">รายจ่าย</option>
                    <option value="income">รายรับ</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm">หมวดหมู่</label>
                <select v-model="form.category_id" class="jt-select" required>
                    <option value="" disabled>เลือกหมวดหมู่</option>
                    <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                        {{ category.emoji }} {{ category.name }}
                    </option>
                </select>
            </div>
        </div>

        <div class="flex gap-2">
            <button class="jt-button jt-button-primary" type="submit">บันทึก</button>
            <button class="jt-button jt-button-outline" type="button" @click="emit('cancel')">ยกเลิก</button>
        </div>
    </form>
</template>
