import { mount } from '@vue/test-utils';

import StageReadinessPanel from '@/pages/Engagements/Show/StageReadinessPanel.vue';

describe('Internal-first MVP rail smoke', () => {
    it('renders the full rail as ready for the consultant golden path', () => {
        const wrapper = mount(StageReadinessPanel, {
            props: {
                readiness: {
                    intake_ready: true,
                    forms_ready: true,
                    review_ready: true,
                    sign_off_ready: true,
                    handbook_ready: true,
                    blockers: [],
                },
            },
        });

        expect(wrapper.find('[data-test="rail-readiness-summary"]').text()).toBe(
            '5/5 ready',
        );
        expect(wrapper.text()).toContain('Launch');
        expect(wrapper.text()).toContain('Forms');
        expect(wrapper.text()).toContain('Review / Workshop');
        expect(wrapper.text()).toContain('Sign-off');
        expect(wrapper.text()).toContain('Handbook');
        expect(wrapper.find('[data-test="rail-readiness-clear"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('No blockers on the current rail.');
    });

    it('shows a blocker when a required stage is missing', () => {
        const wrapper = mount(StageReadinessPanel, {
            props: {
                readiness: {
                    intake_ready: true,
                    forms_ready: true,
                    review_ready: true,
                    sign_off_ready: false,
                    handbook_ready: false,
                    blockers: [
                        'Finalize or deprecate all metrics before closing the engagement.',
                    ],
                },
            },
        });

        expect(wrapper.find('[data-test="rail-readiness-summary"]').text()).toBe(
            '3/5 ready',
        );
        expect(
            wrapper.find('[data-test="rail-readiness-sign-off"]').text(),
        ).toContain('Blocked');
        expect(wrapper.find('[data-test="rail-readiness-blockers"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain(
            'Finalize or deprecate all metrics before closing the engagement.',
        );
    });
});
