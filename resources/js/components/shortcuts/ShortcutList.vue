<script setup>
defineProps({
    shortcuts: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['edit', 'delete']);
</script>

<template>
    <section class="jt-card p-4">
        <h2 class="mb-3 text-lg font-semibold">คำสั่งลัดทั้งหมด</h2>

        <div v-if="shortcuts.length" class="space-y-2">
            <article
                v-for="shortcut in shortcuts"
                :key="shortcut.id"
                class="flex items-center justify-between rounded-xl border border-emerald-100 p-3"
            >
                <div>
                    <p class="font-medium">{{ shortcut.emoji || '🔹' }} {{ shortcut.keyword }}</p>
                    <p class="text-sm text-slate-500">{{ shortcut.category?.emoji }} {{ shortcut.category?.name }} ({{ shortcut.type }})</p>
                </div>
                <div class="flex gap-2">
                    <button class="jt-button jt-button-outline" type="button" @click="emit('edit', shortcut)">แก้ไข</button>
                    <button class="jt-button border border-red-200 bg-red-50 text-red-600" type="button" @click="emit('delete', shortcut)">ลบ</button>
                </div>
            </article>
        </div>
        <p v-else class="text-sm text-slate-500">ยังไม่มีคำสั่งลัด</p>
    </section>
</template>
