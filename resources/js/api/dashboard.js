import apiClient from './client';

export const getSummaryApi = (period) => apiClient.get('/dashboard/summary', { params: { period } });
export const getChartApi = (period) => apiClient.get('/dashboard/chart', { params: { period } });
export const getRecentApi = (limit = 10) => apiClient.get('/dashboard/recent', { params: { limit } });
