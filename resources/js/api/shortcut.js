import apiClient from './client';

export const listShortcutsApi = () => apiClient.get('/shortcuts');
export const createShortcutApi = (payload) => apiClient.post('/shortcuts', payload);
export const updateShortcutApi = (id, payload) => apiClient.put(`/shortcuts/${id}`, payload);
export const deleteShortcutApi = (id) => apiClient.delete(`/shortcuts/${id}`);
