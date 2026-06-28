import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import App from '@/App.vue';
import { router } from '@/router';
import { setUnauthorizedHandler } from '@/api/client';
import { useAuthStore } from '@/stores/auth';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.use(VueQueryPlugin);

// Token kedaluwarsa/dicabut (401) → bersihkan sesi & lempar ke login.
setUnauthorizedHandler(() => {
    const auth = useAuthStore(pinia);
    auth.clearSession();
    if (router.currentRoute.value.name !== 'login') {
        void router.push({ name: 'login' });
    }
});

app.mount('#app');
