import { mount } from '@vue/test-utils';

import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import { buildImpersonationState } from '../setup/impersonation';

describe('Impersonation status components', () => {
    it('renders organization and role details in the banner', () => {
        const state = buildImpersonationState({
            organization: { name: 'Globex' },
            role: 'admin',
        });

        const wrapper = mount(ImpersonationBanner, {
            props: {
                impersonation: state,
            },
        });

        const banner = wrapper.get('[data-test="impersonation-banner"]');
        expect(banner.text()).toContain('Globex');
        expect(banner.text()).toContain('Role: Admin');
        wrapper.unmount();
    });

    it('announces impersonation errors in the banner', () => {
        const wrapper = mount(ImpersonationBanner, {
            props: {
                error: 'Unable to impersonate this organization.',
            },
        });

        const alert = wrapper.get('[data-test="impersonation-error"]');
        expect(alert.text()).toContain('Unable to impersonate this organization.');
        wrapper.unmount();
    });

    it('emits a stop event when the banner action is clicked', async () => {
        const wrapper = mount(ImpersonationBanner, {
            props: {
                impersonation: buildImpersonationState({
                    organization: { name: 'Initech' },
                    role: 'viewer',
                }),
                showStopAction: true,
            },
        });

        await wrapper.get('[data-test="impersonation-stop"]').trigger('click');
        expect(wrapper.emitted('stop')).toBeTruthy();
        wrapper.unmount();
    });
});
