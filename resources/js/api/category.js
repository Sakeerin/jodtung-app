import apiClient from './client';

export const listCategoriesApi = () => apiClient.get('/categories');
