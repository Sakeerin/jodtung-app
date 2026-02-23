import apiClient from './client';

export const listTransactionsApi = (params) => apiClient.get('/transactions', { params });
export const createTransactionApi = (payload) => apiClient.post('/transactions', payload);
export const updateTransactionApi = (id, payload) => apiClient.put(`/transactions/${id}`, payload);
export const deleteTransactionApi = (id) => apiClient.delete(`/transactions/${id}`);
