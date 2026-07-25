import { createApp, watch } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin } from '@tanstack/vue-query';
import App from '@/App.vue';
import { router } from '@/router';
import { setUnauthorizedHandler } from '@/api/client';
import { useAuthStore } from '@/stores/auth';
import { connectEcho, disconnectEcho } from '@/echo';

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

// Siklus hidup Echo mengikuti status auth: login/restore → sambung (token sudah
// ada untuk /broadcasting/auth), logout/401 → putus (cegah socket menggantung
// dengan token lama). connectEcho() no-op bila Reverb tak dikonfigurasi.
const auth = useAuthStore(pinia);
watch(
    () => auth.isAuthenticated,
    (authenticated) => {
        if (authenticated) {
            connectEcho();
        } else {
            disconnectEcho();
        }
    },
    { immediate: true },
);

app.mount('#app');
