<script setup>
import { onMounted, ref, watch } from 'vue';
import { listCategoriesApi } from '../api/category';
import TransactionForm from '../components/transactions/TransactionForm.vue';
import TransactionItem from '../components/transactions/TransactionItem.vue';
import { useTransactionStore } from '../stores/transaction';
import { useUiStore } from '../stores/ui';

const transactionStore = useTransactionStore();
const uiStore = useUiStore();
const categories = ref({ income: [], expense: [] });
const period = ref('summary_month');
const editingTransaction = ref(null);

const fetchPageData = async (page = 1) => {
    await transactionStore.fetchTransactions({ period: period.value, page });
};

const submitTransaction = async (payload) => {
    try {
        if (editingTransaction.value) {
            await transactionStore.updateTransaction(editingTransaction.value.id, payload);
            uiStore.toastSuccess('แก้ไขรายการสำเร็จ');
            editingTransaction.value = null;
        } else {
            await transactionStore.createTransaction(payload);
            uiStore.toastSuccess('เพิ่มรายการสำเร็จ');
        }

        await fetchPageData(transactionStore.pagination.current_page);
    } catch (error) {
        uiStore.toastError(error.response?.data?.message || 'ไม่สามารถบันทึกรายการได้');
    }
};

const deleteTransaction = async (transaction) => {
    try {
        await transactionStore.deleteTransaction(transaction.id);
        await fetchPageData(transactionStore.pagination.current_page);
        uiStore.toastSuccess('ลบรายการสำเร็จ');
    } catch (error) {
        uiStore.toastError(error.response?.data?.message || 'ไม่สามารถลบรายการได้');
    }
};

watch(period, () => fetchPageData(1));

onMounted(async () => {
    const { data } = await listCategoriesApi();
    categories.value = data;
    await fetchPageData(1);
});
</script>

<template>
    <section>
        <div class="jt-card mb-4 p-3">
            <label class="mb-1 block text-sm">ช่วงเวลา</label>
            <select v-model="period" class="jt-select">
                <option value="summary_today">วันนี้</option>
                <option value="summary_week">สัปดาห์นี้</option>
                <option value="summary_month">เดือนนี้</option>
                <option value="summary_all">ทั้งหมด</option>
            </select>
        </div>

        <TransactionForm
            :categories="categories"
            :initial-value="editingTransaction"
            @submit="submitTransaction"
            @cancel="editingTransaction = null"
        />

        <section class="space-y-3">
            <TransactionItem
                v-for="transaction in transactionStore.transactions"
                :key="transaction.id"
                :transaction="transaction"
                @edit="editingTransaction = $event"
                @delete="deleteTransaction"
            />

            <p v-if="!transactionStore.transactions.length && !transactionStore.loading" class="text-sm text-slate-500">
                ยังไม่มีรายการในช่วงเวลานี้
            </p>
        </section>

        <div class="mt-4 flex items-center justify-between">
            <button
                class="jt-button jt-button-outline"
                type="button"
                :disabled="transactionStore.pagination.current_page <= 1"
                @click="fetchPageData(transactionStore.pagination.current_page - 1)"
            >
                ก่อนหน้า
            </button>

            <p class="text-sm text-slate-500">
                หน้า {{ transactionStore.pagination.current_page }} / {{ transactionStore.pagination.last_page }}
            </p>

            <button
                class="jt-button jt-button-outline"
                type="button"
                :disabled="transactionStore.pagination.current_page >= transactionStore.pagination.last_page"
                @click="fetchPageData(transactionStore.pagination.current_page + 1)"
            >
                ถัดไป
            </button>
        </div>
    </section>
</template>
