import { ref } from 'vue';
import { defineStore } from 'pinia';
import { createShortcutApi, deleteShortcutApi, listShortcutsApi, updateShortcutApi } from '../api/shortcut';

export const useShortcutStore = defineStore('shortcut', () => {
    const shortcuts = ref([]);
    const loading = ref(false);

    const fetchShortcuts = async () => {
        loading.value = true;

        try {
            const { data } = await listShortcutsApi();
            shortcuts.value = data;
        } finally {
            loading.value = false;
        }
    };

    const createShortcut = async (payload) => {
        const { data } = await createShortcutApi(payload);
        shortcuts.value.unshift(data.shortcut);
        return data;
    };

    const updateShortcut = async (id, payload) => {
        const { data } = await updateShortcutApi(id, payload);
        const index = shortcuts.value.findIndex((shortcut) => shortcut.id === id);
        if (index >= 0) {
            shortcuts.value[index] = data.shortcut;
        }
        return data;
    };

    const deleteShortcut = async (id) => {
        await deleteShortcutApi(id);
        shortcuts.value = shortcuts.value.filter((shortcut) => shortcut.id !== id);
    };

    return {
        shortcuts,
        loading,
        fetchShortcuts,
        createShortcut,
        updateShortcut,
        deleteShortcut,
    };
});
