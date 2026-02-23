<script setup>
import { onMounted, ref } from 'vue';
import { listCategoriesApi } from '../api/category';
import ShortcutForm from '../components/shortcuts/ShortcutForm.vue';
import ShortcutList from '../components/shortcuts/ShortcutList.vue';
import { useShortcutStore } from '../stores/shortcut';
import { useUiStore } from '../stores/ui';

const shortcutStore = useShortcutStore();
const uiStore = useUiStore();
const categories = ref({ income: [], expense: [] });
const editingShortcut = ref(null);

const fetchPageData = async () => {
    const [{ data: categoryData }] = await Promise.all([listCategoriesApi(), shortcutStore.fetchShortcuts()]);
    categories.value = categoryData;
};

const onCreateOrUpdate = async (payload) => {
    try {
        if (editingShortcut.value) {
            await shortcutStore.updateShortcut(editingShortcut.value.id, payload);
            uiStore.toastSuccess('แก้ไขคำสั่งลัดสำเร็จ');
            editingShortcut.value = null;
            return;
        }

        await shortcutStore.createShortcut(payload);
        uiStore.toastSuccess('เพิ่มคำสั่งลัดสำเร็จ');
    } catch (error) {
        uiStore.toastError(error.response?.data?.error || 'ไม่สามารถบันทึกคำสั่งลัดได้');
    }
};

const onDelete = async (shortcut) => {
    try {
        await shortcutStore.deleteShortcut(shortcut.id);
        uiStore.toastSuccess('ลบคำสั่งลัดสำเร็จ');
    } catch (error) {
        uiStore.toastError(error.response?.data?.error || 'ไม่สามารถลบคำสั่งลัดได้');
    }
};

onMounted(fetchPageData);
</script>

<template>
    <section>
        <ShortcutForm
            :categories="categories"
            :initial-value="editingShortcut"
            @submit="onCreateOrUpdate"
            @cancel="editingShortcut = null"
        />
        <ShortcutList :shortcuts="shortcutStore.shortcuts" @edit="editingShortcut = $event" @delete="onDelete" />
    </section>
</template>
