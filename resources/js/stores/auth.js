import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import {
    getCurrentUserApi,
    lineAutoLoginApi,
    loginApi,
    logoutApi,
    registerApi,
} from '../api/auth';

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('jodtung_token'));
    const user = ref(null);
    const loading = ref(false);
    const userFetchAttempted = ref(false);

    const isAuthenticated = computed(() => Boolean(token.value));

    const persistToken = (nextToken) => {
        token.value = nextToken;
        localStorage.setItem('jodtung_token', nextToken);
    };

    const clearSession = () => {
        token.value = null;
        user.value = null;
        userFetchAttempted.value = false;
        localStorage.removeItem('jodtung_token');
    };

    const fetchCurrentUser = async () => {
        if (!token.value) {
            return null;
        }

        userFetchAttempted.value = true;
        const { data } = await getCurrentUserApi();
        user.value = data.data.user;

        return user.value;
    };

    const login = async (credentials) => {
        loading.value = true;

        try {
            const { data } = await loginApi(credentials);
            persistToken(data.data.token);
            user.value = data.data.user;
            userFetchAttempted.value = true;
            return data;
        } finally {
            loading.value = false;
        }
    };

    const register = async (payload) => {
        loading.value = true;

        try {
            const { data } = await registerApi(payload);
            persistToken(data.data.token);
            user.value = data.data.user;
            userFetchAttempted.value = true;
            return data;
        } finally {
            loading.value = false;
        }
    };

    const autoLoginFromLine = async (lineToken) => {
        loading.value = true;

        try {
            const { data } = await lineAutoLoginApi(lineToken);
            persistToken(data.data.token);
            user.value = data.data.user;
            userFetchAttempted.value = true;
            return data;
        } finally {
            loading.value = false;
        }
    };

    const logout = async () => {
        try {
            if (token.value) {
                await logoutApi();
            }
        } finally {
            clearSession();
        }
    };

    return {
        token,
        user,
        loading,
        userFetchAttempted,
        isAuthenticated,
        clearSession,
        fetchCurrentUser,
        login,
        register,
        autoLoginFromLine,
        logout,
    };
});
