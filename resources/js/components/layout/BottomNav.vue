<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const items = [
    { label: 'Dashboard', icon: '🏠', to: '/dashboard' },
    { label: 'คำสั่งลัด', icon: '⚡', to: '/shortcuts' },
    { label: 'เชื่อมต่อ LINE', icon: '🔗', to: '/line-connection' },
    { label: 'ประวัติ', icon: '🕐', to: '/history' },
];

const isActive = computed(() => (path) => route.path === path);
</script>

<template>
    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-emerald-100 bg-white/95 backdrop-blur">
        <div class="mx-auto grid max-w-5xl grid-cols-4 gap-1 px-2 py-1">
            <router-link
                v-for="item in items"
                :key="item.to"
                :to="item.to"
                class="flex flex-col items-center rounded-xl px-2 py-2 text-xs"
                :class="isActive(item.to) ? 'bg-emerald-100 text-emerald-800' : 'text-slate-500'
                "
            >
                <span class="text-base">{{ item.icon }}</span>
                <span>{{ item.label }}</span>
            </router-link>
        </div>
    </nav>
</template>
