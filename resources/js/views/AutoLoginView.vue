<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';

const authStore = useAuthStore();
const uiStore = useUiStore();
const route = useRoute();
const router = useRouter();

const state = ref('loading');

onMounted(async () => {
    const token = route.query.token;

    if (!token) {
        state.value = 'error';
        uiStore.toastError('ไม่พบ token สำหรับ auto-login');
        return;
    }

    try {
        await authStore.autoLoginFromLine(token);
        uiStore.toastSuccess('เข้าสู่ระบบจาก LINE สำเร็จ');
        router.replace('/dashboard');
    } catch (error) {
        state.value = 'error';
        uiStore.toastError(error.response?.data?.message || 'Auto-login ไม่สำเร็จ');
    }
});
</script>

<template>
    <main class="mx-auto max-w-md pt-10">
        <section class="jt-card p-6 text-center">
            <div v-if="state === 'loading'" class="space-y-3">
                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-emerald-200 border-t-emerald-600" />
                <h1 class="text-xl font-semibold">กำลังเข้าสู่ระบบจาก LINE</h1>
                <p class="text-sm text-slate-600">โปรดรอสักครู่ ระบบกำลังยืนยันตัวตนของคุณ</p>
            </div>

            <div v-else class="space-y-3">
                <h1 class="text-xl font-semibold text-red-700">เข้าสู่ระบบไม่สำเร็จ</h1>
                <p class="text-sm text-slate-600">ลิงก์อาจหมดอายุแล้ว กรุณาลองเปิดจาก LINE อีกครั้ง</p>
                <router-link to="/login" class="jt-button jt-button-outline inline-flex">ไปหน้าเข้าสู่ระบบ</router-link>
            </div>
        </section>
    </main>
</template>
