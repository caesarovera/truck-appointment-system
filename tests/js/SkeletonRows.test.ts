import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';

import SkeletonRows from '@/components/SkeletonRows.vue';

describe('SkeletonRows', () => {
    it('renders the requested number of placeholder rows', () => {
        const wrapper = mount(SkeletonRows, { props: { rows: 4 } });

        expect(wrapper.findAll('[aria-hidden="true"]')).toHaveLength(4);
    });

    it('defaults to 3 rows', () => {
        expect(mount(SkeletonRows).findAll('[aria-hidden="true"]')).toHaveLength(3);
    });

    it('keeps the label readable by screen readers', () => {
        // Animasi tidak mengabarkan apa pun ke pembaca layar — teksnya harus tetap
        // ada (sr-only) di dalam role="status", bukan hilang bersama teks visual.
        const wrapper = mount(SkeletonRows, { props: { label: 'Memuat armada…' } });
        const status = wrapper.get('[role="status"]');

        expect(status.attributes('aria-busy')).toBe('true');
        expect(status.get('.sr-only').text()).toBe('Memuat armada…');
    });
});
