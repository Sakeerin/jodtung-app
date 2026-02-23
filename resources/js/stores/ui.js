import { ref } from 'vue';
import { defineStore } from 'pinia';

let toastId = 1;

export const useUiStore = defineStore('ui', () => {
    const toasts = ref([]);

    const removeToast = (id) => {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    };

    const pushToast = ({ type = 'info', message, timeout = 3600 }) => {
        const id = toastId++;
        toasts.value.push({ id, type, message });

        if (timeout > 0) {
            window.setTimeout(() => removeToast(id), timeout);
        }

        return id;
    };

    const toastSuccess = (message, timeout) => pushToast({ type: 'success', message, timeout });
    const toastError = (message, timeout) => pushToast({ type: 'error', message, timeout });
    const toastInfo = (message, timeout) => pushToast({ type: 'info', message, timeout });

    return {
        toasts,
        removeToast,
        pushToast,
        toastSuccess,
        toastError,
        toastInfo,
    };
});
