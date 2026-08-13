<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import QRCode from 'qrcode';

/**
 * Render QR gate-in di client dari `qr_token` (BUSINESS-FLOW §3.4) — TIDAK
 * pernah minta gambar ke backend. Konten QR = token ter-sign itu sendiri;
 * gate-officer scan → `GET /api/v1/appointments/qr/{token}` yang memverifikasi
 * tanda tangan + TTL-nya. Ini "opsi A" yang disepakati: nol beban storage
 * server, karena gambarnya tak pernah dikirim ke/dari backend sama sekali.
 * (Endpoint `.../qr/{token}/image` di backend itu terpisah — cuma untuk
 * kebutuhan cetak/email, bukan dipakai path render normal ini.)
 */
const props = defineProps<{ qrToken: string }>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const failed = ref(false);

async function render(): Promise<void> {
    if (!canvasRef.value || !props.qrToken) {
        return;
    }

    try {
        await QRCode.toCanvas(canvasRef.value, props.qrToken, { width: 200, margin: 1 });
        failed.value = false;
    } catch {
        failed.value = true;
    }
}

onMounted(render);
watch(() => props.qrToken, render);
</script>

<template>
    <div data-testid="appointment-qr-code">
        <canvas ref="canvasRef" role="img" aria-label="Kode QR untuk gate-in"></canvas>
        <p v-if="failed" role="alert" class="text-sm text-red-600">Gagal menampilkan QR. Gunakan kode booking.</p>
    </div>
</template>
