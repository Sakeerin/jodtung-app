<script setup>
import { reactive } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';

const authStore = useAuthStore();
const uiStore = useUiStore();
const route = useRoute();
const router = useRouter();

const form = reactive({
    email: '',
    password: '',
});

const submit = async () => {
    try {
        await authStore.login(form);
        uiStore.toastSuccess('เข้าสู่ระบบสำเร็จ');
        router.push(route.query.redirect || '/dashboard');
    } catch (error) {
        uiStore.toastError(error.response?.data?.message || 'เข้าสู่ระบบไม่สำเร็จ');
    }
};
</script>

<template>
    <main class="mx-auto max-w-md pt-6">
        <section class="jt-card p-5">
            <h1 class="text-2xl font-semibold">เข้าสู่ระบบ</h1>
            <p class="mb-4 mt-1 text-sm text-slate-600">จัดการรายรับรายจ่ายผ่านแดชบอร์ดของจดตังค์</p>

            <form class="space-y-3" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm">อีเมล</label>
                    <input v-model="form.email" type="email" class="jt-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm">รหัสผ่าน</label>
                    <input v-model="form.password" type="password" class="jt-input" required>
                </div>
                <button class="jt-button jt-button-primary w-full" type="submit" :disabled="authStore.loading">เข้าสู่ระบบ</button>
            </form>

            <p class="mt-4 text-sm text-slate-600">
                ยังไม่มีบัญชี?
                <router-link to="/register" class="font-semibold text-emerald-700">สมัครสมาชิก</router-link>
            </p>
        </section>
    </main>
</template>
