<script setup>
const props = defineProps({
    status: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['disconnect']);
</script>

<template>
    <section class="jt-card mb-4 p-4">
        <h2 class="mb-3 text-lg font-semibold">สถานะการเชื่อมต่อ LINE</h2>

        <div class="rounded-lg border p-3" :class="status.is_connected ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
            <p class="font-medium">{{ status.is_connected ? 'เชื่อมต่อแล้ว' : 'ยังไม่เชื่อมต่อ' }}</p>
            <p v-if="status.connected_at" class="text-sm text-slate-600">เชื่อมต่อเมื่อ {{ status.connected_at }}</p>
        </div>

        <button
            v-if="status.is_connected"
            class="jt-button mt-3 border border-red-200 bg-red-50 text-red-600"
            type="button"
            @click="emit('disconnect')"
        >
            ยกเลิกการเชื่อมต่อ
        </button>
    </section>
</template>
