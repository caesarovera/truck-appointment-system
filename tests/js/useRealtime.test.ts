import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, ref, type Ref } from 'vue';

// --- Mock TanStack Query: tangkap invalidateQueries ---
const invalidateQueries = vi.fn();
vi.mock('@tanstack/vue-query', () => ({
    useQueryClient: () => ({ invalidateQueries }),
}));

// --- Mock Echo: private().listen() merekam callback event ---
const listen = vi.fn();
const privateChannel = vi.fn(() => ({ listen }));
const leave = vi.fn();
let echoInstance: { private: typeof privateChannel; leave: typeof leave } | null = null;
vi.mock('@/echo', () => ({
    getEcho: () => echoInstance,
}));

import { useSlotRealtime, useGateQueueRealtime } from '@/composables/useRealtime';

/** Jalankan composable di dalam konteks komponen (butuh onUnmounted/watch). */
function withComposable(fn: () => void) {
    const Comp = defineComponent({
        setup() {
            fn();
            return () => null;
        },
    });
    return mount(Comp);
}

/** Ambil callback event terakhir yang didaftarkan via listen(). */
function lastListener(): () => void {
    return listen.mock.calls.at(-1)?.[1] as () => void;
}

beforeEach(() => {
    vi.clearAllMocks();
    echoInstance = { private: privateChannel, leave };
});

describe('useSlotRealtime', () => {
    it('subscribes to slot.{gateId} and invalidates availability on event', () => {
        withComposable(() => useSlotRealtime(ref(5)));

        expect(privateChannel).toHaveBeenCalledWith('slot.5');
        expect(listen).toHaveBeenCalledWith('.slot.availability.changed', expect.any(Function));

        lastListener()(); // simulasikan event dari server
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['slots-availability'] });
    });

    it('does not subscribe when gate is null', () => {
        withComposable(() => useSlotRealtime(ref<number | null>(null)));
        expect(privateChannel).not.toHaveBeenCalled();
    });

    it('leaves the old channel and joins the new one when gate changes', async () => {
        const gate: Ref<number | null> = ref(5);
        withComposable(() => useSlotRealtime(gate));

        gate.value = 7;
        await Promise.resolve(); // biarkan watcher jalan

        expect(leave).toHaveBeenCalledWith('slot.5');
        expect(privateChannel).toHaveBeenLastCalledWith('slot.7');
    });

    it('leaves the channel on unmount', () => {
        const wrapper = withComposable(() => useSlotRealtime(ref(5)));
        wrapper.unmount();
        expect(leave).toHaveBeenCalledWith('slot.5');
    });

    it('is a no-op when Echo is not connected (graceful degradation)', () => {
        echoInstance = null;
        expect(() => withComposable(() => useSlotRealtime(ref(5)))).not.toThrow();
        expect(privateChannel).not.toHaveBeenCalled();
    });
});

describe('useGateQueueRealtime', () => {
    it('subscribes to gate.queue.{terminalId} and invalidates the queue on event', () => {
        withComposable(() => useGateQueueRealtime(ref(3)));

        expect(privateChannel).toHaveBeenCalledWith('gate.queue.3');
        expect(listen).toHaveBeenCalledWith('.gate.queue.updated', expect.any(Function));

        lastListener()();
        expect(invalidateQueries).toHaveBeenCalledWith({ queryKey: ['gate-queue'] });
    });

    it('does not subscribe when terminal is null', () => {
        withComposable(() => useGateQueueRealtime(ref<number | null>(null)));
        expect(privateChannel).not.toHaveBeenCalled();
    });
});
