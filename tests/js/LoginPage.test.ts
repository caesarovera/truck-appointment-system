import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import LoginPage from '@/pages/LoginPage.vue';

const login = vi.fn();
const push = vi.fn();

// Komponen diuji terisolasi: store & router di-mock (real axios.isAxiosError dipakai).
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ login }) }));
vi.mock('vue-router', () => ({
    useRouter: () => ({ push }),
    useRoute: () => ({ query: {} }),
}));

beforeEach(() => vi.clearAllMocks());

describe('LoginPage', () => {
    it('submits credentials and redirects on success', async () => {
        login.mockResolvedValue(undefined);

        const wrapper = mount(LoginPage);
        await wrapper.find('input[type="email"]').setValue('planner@tas.test');
        await wrapper.find('input[type="password"]').setValue('password');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(login).toHaveBeenCalledWith('planner@tas.test', 'password');
        expect(push).toHaveBeenCalledWith('/');
    });

    it('shows a server error message and does not redirect on failure', async () => {
        login.mockRejectedValue({
            isAxiosError: true,
            response: { data: { message: 'These credentials do not match our records.' } },
        });

        const wrapper = mount(LoginPage);
        await wrapper.find('input[type="email"]').setValue('x@y.test');
        await wrapper.find('input[type="password"]').setValue('wrong');
        await wrapper.find('form').trigger('submit.prevent');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('These credentials do not match our records.');
        expect(push).not.toHaveBeenCalled();
    });
});
