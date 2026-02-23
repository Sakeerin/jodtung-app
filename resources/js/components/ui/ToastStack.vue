<script setup>
import { useUiStore } from '../../stores/ui';

const uiStore = useUiStore();

const toneClass = (type) => {
    if (type === 'success') {
        return 'jt-toast-success';
    }

    if (type === 'error') {
        return 'jt-toast-error';
    }

    return 'jt-toast-info';
};
</script>

<template>
    <div class="jt-toast-wrap pointer-events-none fixed inset-x-0 top-3 z-50 mx-auto w-full max-w-xl px-3">
        <TransitionGroup name="toast" tag="div" class="space-y-2">
            <div
                v-for="toast in uiStore.toasts"
                :key="toast.id"
                class="jt-toast pointer-events-auto"
                :class="toneClass(toast.type)"
            >
                <p class="text-sm font-medium">{{ toast.message }}</p>
                <button type="button" class="jt-toast-close" @click="uiStore.removeToast(toast.id)">x</button>
            </div>
        </TransitionGroup>
    </div>
</template>
