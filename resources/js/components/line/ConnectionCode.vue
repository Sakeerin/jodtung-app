<script setup>
defineProps({
    code: {
        type: String,
        default: '',
    },
    expiresAt: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['generate']);
</script>

<template>
    <section class="jt-card p-4">
        <h2 class="mb-3 text-lg font-semibold">รหัสเชื่อมต่อ</h2>
        <div v-if="code" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm text-slate-600">ส่งรหัสนี้ใน LINE แชทของบอท</p>
            <p class="mt-1 font-mono text-2xl font-semibold tracking-wide text-emerald-700">{{ code }}</p>
            <p v-if="expiresAt" class="mt-2 text-xs text-slate-500">หมดอายุ: {{ expiresAt }}</p>
        </div>
        <p v-else class="mb-3 text-sm text-slate-500">กดสร้างรหัสเพื่อเชื่อมต่อบัญชี LINE</p>
        <button class="jt-button jt-button-primary mt-3" type="button" @click="emit('generate')">สร้างรหัสเชื่อมต่อ</button>
    </section>
</template>
