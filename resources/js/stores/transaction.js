import { ref } from 'vue';
import { defineStore } from 'pinia';
import {
    createTransactionApi,
    deleteTransactionApi,
    listTransactionsApi,
    updateTransactionApi,
} from '../api/transaction';

export const useTransactionStore = defineStore('transaction', () => {
    const transactions = ref([]);
    const pagination = ref({
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: 0,
    });
    const loading = ref(false);

    const fetchTransactions = async (params = {}) => {
        loading.value = true;

        try {
            const { data } = await listTransactionsApi(params);
            transactions.value = data.data;
            pagination.value = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page,
                total: data.total,
            };
        } finally {
            loading.value = false;
        }
    };

    const createTransaction = async (payload) => {
        const { data } = await createTransactionApi(payload);
        return data;
    };

    const updateTransaction = async (id, payload) => {
        const { data } = await updateTransactionApi(id, payload);
        const index = transactions.value.findIndex((transaction) => transaction.id === id);
        if (index >= 0) {
            transactions.value[index] = data.transaction;
        }
        return data;
    };

    const deleteTransaction = async (id) => {
        await deleteTransactionApi(id);
        transactions.value = transactions.value.filter((transaction) => transaction.id !== id);
    };

    return {
        transactions,
        pagination,
        loading,
        fetchTransactions,
        createTransaction,
        updateTransaction,
        deleteTransaction,
    };
});
