<script setup>
import { onMounted, ref } from 'vue';
import {
    disconnectLineApi,
    generateLineConnectionCodeApi,
    getLineConnectionStatusApi,
} from '../api/lineConnection';
import ConnectionCode from '../components/line/ConnectionCode.vue';
import ConnectionStatus from '../components/line/ConnectionStatus.vue';
import { useUiStore } from '../stores/ui';

const uiStore = useUiStore();
const status = ref({
    is_connected: false,
    connected_at: null,
    connection_code: null,
    code_expires_at: null,
});

const loadStatus = async () => {
    const { data } = await getLineConnectionStatusApi();
    status.value = data;
};

const generateCode = async () => {
    try {
        const { data } = await generateLineConnectionCodeApi();
        status.value.connection_code = data.connection_code;
        status.value.code_expires_at = data.expires_at;
        uiStore.toastSuccess('สร้างรหัสเชื่อมต่อใหม่แล้ว');
    } catch (error) {
        uiStore.toastError(error.response?.data?.error || 'ไม่สามารถสร้างรหัสเชื่อมต่อได้');
    }
};

const disconnect = async () => {
    try {
        await disconnectLineApi();
        await loadStatus();
        uiStore.toastSuccess('ยกเลิกการเชื่อมต่อ LINE สำเร็จ');
    } catch (error) {
        uiStore.toastError(error.response?.data?.error || 'ไม่สามารถยกเลิกการเชื่อมต่อได้');
    }
};

onMounted(loadStatus);
</script>

<template>
    <section>
        <ConnectionStatus :status="status" @disconnect="disconnect" />
        <ConnectionCode
            :code="status.connection_code"
            :expires-at="status.code_expires_at"
            @generate="generateCode"
        />
    </section>
</template>
