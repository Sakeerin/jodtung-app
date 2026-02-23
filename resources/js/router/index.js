import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const routes = [
    { path: '/', redirect: '/dashboard' },
    {
        path: '/dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/shortcuts',
        component: () => import('../views/ShortcutsView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/line-connection',
        component: () => import('../views/LineConnectionView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/history',
        component: () => import('../views/HistoryView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/login',
        component: () => import('../views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        component: () => import('../views/RegisterView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/auto-login',
        component: () => import('../views/AutoLoginView.vue'),
        meta: { guestOnly: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const authStore = useAuthStore();

    if (authStore.token && !authStore.user && !authStore.userFetchAttempted) {
        try {
            await authStore.fetchCurrentUser();
        } catch (error) {
            authStore.clearSession();
        }
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { path: '/login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return { path: '/dashboard' };
    }

    return true;
});

export default router;
