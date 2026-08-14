<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { isAxiosError } from 'axios';
import { useAuthStore } from '@/stores/auth';
import AppLogo from '@/components/AppLogo.vue';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);

async function submit(): Promise<void> {
    error.value = null;
    submitting.value = true;
    try {
        await auth.login(email.value, password.value);
        const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
        await router.push(redirect);
    } catch (e) {
        error.value = extractError(e);
    } finally {
        submitting.value = false;
    }
}

function extractError(e: unknown): string {
    if (isAxiosError(e)) {
        const data = e.response?.data as { message?: string } | undefined;
        return data?.message ?? 'Login gagal. Periksa email & password.';
    }
    return 'Terjadi kesalahan. Coba lagi.';
}
</script>

<template>
    <div class="min-h-screen grid lg:grid-cols-2 bg-sand-50">
        <!-- Panel kiri: identitas — nuansa pelabuhan (dermaga malam, crane, kontainer). -->
        <div class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-harbor-900 text-white p-12">
            <div
                class="pointer-events-none absolute inset-0 opacity-40"
                style="background-image: radial-gradient(circle at 15% 20%, rgba(249,115,22,0.25), transparent 45%), radial-gradient(circle at 85% 75%, rgba(44,111,168,0.35), transparent 50%)"
                aria-hidden="true"
            ></div>

            <!-- Deretan kontainer dekoratif -->
            <svg class="pointer-events-none absolute inset-x-0 bottom-0 w-full opacity-90" viewBox="0 0 800 220" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
                <g>
                    <rect x="0" y="150" width="110" height="60" fill="#c2410c" />
                    <rect x="115" y="150" width="110" height="60" fill="#f2f6fa" opacity=".12" />
                    <rect x="230" y="150" width="110" height="60" fill="#2c6fa8" opacity=".55" />
                    <rect x="345" y="150" width="110" height="60" fill="#ea580c" opacity=".85" />
                    <rect x="460" y="150" width="110" height="60" fill="#f2f6fa" opacity=".12" />
                    <rect x="575" y="150" width="110" height="60" fill="#2c6fa8" opacity=".55" />
                    <rect x="690" y="150" width="110" height="60" fill="#c2410c" />
                    <rect x="40" y="95" width="110" height="55" fill="#2c6fa8" opacity=".45" />
                    <rect x="270" y="95" width="110" height="55" fill="#f2f6fa" opacity=".1" />
                    <rect x="500" y="95" width="110" height="55" fill="#ea580c" opacity=".7" />
                    <rect x="620" y="95" width="110" height="55" fill="#2c6fa8" opacity=".45" />
                </g>
                <!-- Crane sederhana -->
                <g stroke="#f2f6fa" stroke-width="3" opacity=".5" fill="none">
                    <line x1="640" y1="30" x2="640" y2="150" />
                    <line x1="560" y1="45" x2="760" y2="45" />
                    <line x1="640" y1="45" x2="600" y2="150" />
                    <line x1="595" y1="10" x2="670" y2="30" />
                </g>
                <!-- Ombak -->
                <path d="M0 210 Q 40 195 80 210 T 160 210 T 240 210 T 320 210 T 400 210 T 480 210 T 560 210 T 640 210 T 720 210 T 800 210 V220 H0 Z" fill="#051625" opacity=".6" />
            </svg>

            <div class="relative flex items-center gap-3">
                <AppLogo variant="hero" />
                <div class="leading-tight">
                    <p class="text-lg font-semibold tracking-wide">TAS</p>
                    <p class="text-xs text-harbor-100/80">Truck Appointment System</p>
                </div>
            </div>

            <div class="relative space-y-3 max-w-sm">
                <h1 class="text-3xl font-semibold leading-tight">
                    Atur kedatangan truk<br />ke terminal, tanpa antrean menumpuk.
                </h1>
                <p class="text-sm text-harbor-100/80">
                    Booking slot gate, gate-in/gate-out, dan pantau kuota terminal secara real-time —
                    satu sistem untuk transporter, sopir, planner, dan petugas gate.
                </p>
            </div>

            <p class="relative text-xs text-harbor-100/60">© {{ new Date().getFullYear() }} Terminal Appointment System</p>
        </div>

        <!-- Panel kanan: form login -->
        <div class="flex items-center justify-center px-4 py-12">
            <form class="w-full max-w-sm space-y-5" @submit.prevent="submit">
                <div class="mb-2 flex items-center gap-3 lg:hidden">
                    <AppLogo variant="compact" />
                    <span class="font-semibold text-harbor-900">TAS</span>
                </div>

                <div class="space-y-1">
                    <h2 class="text-xl font-semibold text-harbor-900">Masuk ke akun Anda</h2>
                    <p class="text-sm text-slate-500">Gunakan email &amp; kata sandi yang terdaftar di terminal Anda.</p>
                </div>

                <p v-if="error" role="alert" data-testid="login-error" class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-md p-3">
                    {{ error }}
                </p>

                <label class="block space-y-1">
                    <span class="text-sm font-medium text-slate-700">Email</span>
                    <input
                        v-model="email"
                        type="email"
                        required
                        autocomplete="username"
                        data-testid="login-email"
                        placeholder="nama@perusahaan.com"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-harbor-500 focus:border-harbor-500"
                    />
                </label>

                <label class="block space-y-1">
                    <span class="text-sm font-medium text-slate-700">Password</span>
                    <input
                        v-model="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        data-testid="login-password"
                        placeholder="••••••••"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-harbor-500 focus:border-harbor-500"
                    />
                </label>

                <button
                    type="submit"
                    :disabled="submitting"
                    data-testid="login-submit"
                    class="w-full rounded-lg bg-signal-600 text-white py-2.5 font-medium shadow-sm hover:bg-signal-700 disabled:opacity-50 transition-colors"
                >
                    {{ submitting ? 'Memproses…' : 'Masuk' }}
                </button>
            </form>
        </div>
    </div>
</template>
