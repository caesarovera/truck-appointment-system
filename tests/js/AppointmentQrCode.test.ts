import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

// jsdom tak punya implementasi canvas 2D nyata — mock library-nya, bukan
// coba render piksel sungguhan (sama pola dgn mock composable di halaman lain).
const toCanvas = vi.fn().mockResolvedValue(undefined);
vi.mock('qrcode', () => ({ default: { toCanvas: (...args: unknown[]) => toCanvas(...args) } }));

import AppointmentQrCode from '@/components/AppointmentQrCode.vue';

beforeEach(() => {
    toCanvas.mockClear();
    toCanvas.mockResolvedValue(undefined);
});

describe('AppointmentQrCode', () => {
    it('renders a QR into the canvas from the given token', async () => {
        mount(AppointmentQrCode, { props: { qrToken: 'abc123.deadbeef' } });
        await flushPromises();

        expect(toCanvas).toHaveBeenCalledTimes(1);
        expect(toCanvas.mock.calls[0][1]).toBe('abc123.deadbeef');
    });

    it('re-renders when the token prop changes', async () => {
        const wrapper = mount(AppointmentQrCode, { props: { qrToken: 'first.token' } });
        await flushPromises();

        await wrapper.setProps({ qrToken: 'second.token' });
        await flushPromises();

        expect(toCanvas).toHaveBeenCalledTimes(2);
        expect(toCanvas.mock.calls[1][1]).toBe('second.token');
    });

    it('shows a fallback message when rendering fails', async () => {
        toCanvas.mockRejectedValueOnce(new Error('boom'));

        const wrapper = mount(AppointmentQrCode, { props: { qrToken: 'broken.token' } });
        await flushPromises();

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    });
});
