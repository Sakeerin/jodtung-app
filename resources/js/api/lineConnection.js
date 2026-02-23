import apiClient from './client';

export const getLineConnectionStatusApi = () => apiClient.get('/line/connection');
export const generateLineConnectionCodeApi = () => apiClient.post('/line/generate-code');
export const disconnectLineApi = () => apiClient.delete('/line/disconnect');
