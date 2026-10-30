import { describe, expect, it } from 'vitest';
import { mount, RouterLinkStub } from '@vue/test-utils';
import StatusPage from '@/pages/StatusPage.vue';

const mountPage = (props: { code: 403 | 404; from?: string }) =>
    mount(StatusPage, { props, global: { stubs: { RouterLink: RouterLinkStub } } });

describe('StatusPage', () => {
    it('explains a 403 as missing access and names the page that was refused', () => {
        const wrapper = mountPage({ code: 403, from: '/admin' });

        expect(wrapper.find('h1').text()).toBe('Tidak punya akses');
        expect(wrapper.text()).toContain('/admin');
        expect(wrapper.text()).not.toContain('tidak ditemukan');
    });

    it('explains a 404 as a page that does not exist', () => {
        const wrapper = mountPage({ code: 404 });

        expect(wrapper.find('h1').text()).toBe('Halaman tidak ditemukan');
        expect(wrapper.text()).not.toContain('akses');
    });

    it('offers a way back to the dashboard', () => {
        const link = mountPage({ code: 404 }).findComponent(RouterLinkStub);

        expect(link.props('to')).toBe('/');
        expect(link.text()).toBe('Ke Beranda');
    });
});
