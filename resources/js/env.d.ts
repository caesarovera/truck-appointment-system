/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<Record<string, never>, Record<string, never>, unknown>;
    export default component;
}

// Variabel Vite untuk klien Reverb (WebSocket). Semua opsional — bila
// VITE_REVERB_APP_KEY kosong, Echo tak di-init (degradasi mulus, lihat echo.ts).
interface ImportMetaEnv {
    readonly VITE_REVERB_APP_KEY?: string;
    readonly VITE_REVERB_HOST?: string;
    readonly VITE_REVERB_PORT?: string;
    readonly VITE_REVERB_SCHEME?: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
