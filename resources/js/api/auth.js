import apiClient from './client';

export const loginApi = (payload) => apiClient.post('/auth/login', payload);
export const registerApi = (payload) => apiClient.post('/auth/register', payload);
export const getCurrentUserApi = () => apiClient.get('/auth/user');
export const logoutApi = () => apiClient.post('/auth/logout');
export const lineAutoLoginApi = (token) => apiClient.get(`/auth/line-auto-login?token=${token}`);
