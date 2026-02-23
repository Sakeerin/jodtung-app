<script setup>
const model = defineModel({ required: true });

const options = [
    { label: 'วันนี้', value: 'summary_today' },
    { label: 'สัปดาห์นี้', value: 'summary_week' },
    { label: 'เดือนนี้', value: 'summary_month' },
    { label: 'ทั้งหมด', value: 'summary_all' },
];
</script>

<template>
    <div class="jt-card mb-4 p-2">
        <div class="grid grid-cols-4 gap-2">
            <button
                v-for="option in options"
                :key="option.value"
                class="jt-button text-sm"
                :class="model === option.value ? 'jt-button-primary' : 'jt-button-outline'"
                type="button"
                @click="model = option.value"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
