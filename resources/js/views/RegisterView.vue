<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useUiStore } from '../stores/ui';

const authStore = useAuthStore();
const uiStore = useUiStore();
const router = useRouter();

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const successConnectionCode = ref('');

const submit = async () => {
    try {
        const data = await authStore.register(form);
        successConnectionCode.value = data.data.connection_code;
        uiStore.toastSuccess('สมัครสมาชิกสำเร็จ');
        router.push('/dashboard');
    } catch (error) {
        uiStore.toastError(error.response?.data?.message || 'สมัครสมาชิกไม่สำเร็จ');
    }
};
</script>

<template>
    <main class="mx-auto max-w-md pt-6">
        <section class="jt-card p-5">
            <h1 class="text-2xl font-semibold">สมัครสมาชิก</h1>
            <p class="mb-4 mt-1 text-sm text-slate-600">เริ่มต้นใช้งานบอทจดตังค์และเชื่อมต่อ LINE ได้ทันที</p>

            <p v-if="successConnectionCode" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                รหัสเชื่อมต่อของคุณ: {{ successConnectionCode }}
            </p>

            <form class="space-y-3" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm">ชื่อ</label>
                    <input v-model="form.name" class="jt-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm">อีเมล</label>
                    <input v-model="form.email" type="email" class="jt-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm">รหัสผ่าน</label>
                    <input v-model="form.password" type="password" class="jt-input" required>
                </div>
                <div>
                    <label class="mb-1 block text-sm">ยืนยันรหัสผ่าน</label>
                    <input v-model="form.password_confirmation" type="password" class="jt-input" required>
                </div>

                <button class="jt-button jt-button-primary w-full" type="submit" :disabled="authStore.loading">สมัครสมาชิก</button>
            </form>

            <p class="mt-4 text-sm text-slate-600">
                มีบัญชีอยู่แล้ว?
                <router-link to="/login" class="font-semibold text-emerald-700">เข้าสู่ระบบ</router-link>
            </p>
        </section>
    </main>
</template>
