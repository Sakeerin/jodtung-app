<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useUiStore } from '../../stores/ui';

const authStore = useAuthStore();
const uiStore = useUiStore();
const router = useRouter();

const onLogout = async () => {
    await authStore.logout();
    uiStore.toastInfo('ออกจากระบบแล้ว');
    router.push('/login');
};
</script>

<template>
    <header class="jt-card mb-4 flex items-center justify-between px-4 py-3">
        <div>
            <p class="text-xs uppercase tracking-wide text-emerald-700">JodTung Dashboard</p>
            <h1 class="text-lg font-semibold">สวัสดี, {{ authStore.user?.name ?? 'ผู้ใช้งาน' }}</h1>
        </div>
        <button class="jt-button jt-button-outline" type="button" @click="onLogout">ออกจากระบบ</button>
    </header>
</template>
