import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import MetricSourceContext from '@/features/handbook-workbench/MetricSourceContext.vue';
import type { SourceContext } from '@/features/handbook-workbench/MetricSourceContext.vue';

describe('MetricSourceContext - Matrix Leftovers', () => {
    const mockContext: SourceContext = {
        signed_definition: {
            version: 1,
            signed_off_at: '2026-05-30T10:00:00Z',
            signed_off_by: { id: 'usr-1', name: 'John Admin' },
            notes: null,
            owner: null,
            sections: [],
        },
        workshop_alignment: {
            has_evidence: false,
            workshop: null,
            discrepancies: [],
        },
        matrix_leftovers: [
            {
                field_key: 'lead_definition',
                label: 'CPL lead definition',
                stakeholder_positions: [
                    {
                        stakeholder: 'Finance',
                        raw_value: 'marketing_qualified',
                        normalized_value: 'marketing_qualified',
                    },
                    {
                        stakeholder: 'Marketing',
                        raw_value: 'any_contact',
                        normalized_value: 'any_contact',
                    },
                ],
                final_value: 'sales_qualified',
                status: 'rejected',
                reason: 'Workshop selected SQL as board CPL denominator.',
                used_for: ['common_mismatch_causes', 'validation'],
            },
        ],
    };

    it('renders the Matrix Leftovers section and its items correctly', () => {
        const wrapper = mount(MetricSourceContext, {
            props: {
                sourceContext: mockContext,
            },
        });

        // Verify section exists
        const panel = wrapper.find('[data-test="matrix-leftovers-panel"]');
        expect(panel.exists()).toBe(true);
        expect(panel.text()).toContain('Matrix Leftovers');

        // Verify item exists
        const item = panel.find('[data-test="matrix-leftover-lead_definition"]');
        expect(item.exists()).toBe(true);
        expect(item.text()).toContain('CPL lead definition');
        expect(item.text()).toContain('Final Decision:');
        expect(item.text()).toContain('Sales Qualified'); // check option/value formatting

        // Verify stakeholder positions
        const financePos = item.find('[data-test="leftover-stakeholder-lead_definition-Finance"]');
        expect(financePos.exists()).toBe(true);
        expect(financePos.text()).toContain('Finance');
        expect(financePos.text()).toContain('Marketing Qualified'); // formatted option/value

        const marketingPos = item.find('[data-test="leftover-stakeholder-lead_definition-Marketing"]');
        expect(marketingPos.exists()).toBe(true);
        expect(marketingPos.text()).toContain('Marketing');
        expect(marketingPos.text()).toContain('Any Contact');

        // Verify status and reason
        const status = item.find('[data-test="leftover-status-lead_definition"]');
        expect(status.exists()).toBe(true);
        expect(status.text()).toContain('Rejected');

        const reason = item.find('[data-test="leftover-reason-lead_definition"]');
        expect(reason.exists()).toBe(true);
        expect(reason.text()).toContain('Workshop selected SQL as board CPL denominator.');

        // Verify used for list
        expect(item.text()).toContain('Used for:');
        expect(item.text()).toContain('common mismatch causes');
        expect(item.text()).toContain('validation');
    });

    it('does not render the leftovers panel if list is empty or undefined', () => {
        const emptyContext: SourceContext = {
            signed_definition: {
                version: 1,
                signed_off_at: null,
                signed_off_by: null,
                notes: null,
                owner: null,
                sections: [],
            },
            workshop_alignment: {
                has_evidence: false,
                workshop: null,
                discrepancies: [],
            },
            matrix_leftovers: [],
        };

        const wrapper = mount(MetricSourceContext, {
            props: {
                sourceContext: emptyContext,
            },
        });

        expect(wrapper.find('[data-test="matrix-leftovers-panel"]').exists()).toBe(false);
    });
});
