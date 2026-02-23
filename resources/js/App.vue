<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppHeader from './components/layout/AppHeader.vue';
import BottomNav from './components/layout/BottomNav.vue';
import ToastStack from './components/ui/ToastStack.vue';

const route = useRoute();
const showNavigation = computed(() => route.meta.requiresAuth === true);
</script>

<template>
    <div class="jt-shell">
        <ToastStack />
        <AppHeader v-if="showNavigation" />
        <router-view />
        <BottomNav v-if="showNavigation" />
    </div>
</template>
