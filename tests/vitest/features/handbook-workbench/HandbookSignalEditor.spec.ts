import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import HandbookSignalEditor from '@/features/handbook-workbench/HandbookSignalEditor.vue';
import type { SourceContext } from '@/features/handbook-workbench/MetricSourceContext.vue';
import type { Signals } from '@/features/handbook-workbench/handbook-signals';

describe('HandbookSignalEditor Source Chips', () => {
    const mockSignals: Signals = {
        org_final_definition_id: 'def-123',
        version: 1,
        signed_off_at: '2026-05-30T10:00:00Z',
        signed_off_by: { id: 'usr-1', name: 'John Admin' },
        executive_takeaway: 'Initial takeaway text',
        alignment_summary: 'Initial alignment summary',
        operational_status: 'operationally_ready',
        operational_status_notes: 'Initial status notes',
        validation_steps: 'Initial validation steps',
        common_mismatch_causes: 'Initial mismatch causes',
        implementation_gaps: {
            current_state: 'Current state',
            gap: 'None',
            owner: 'Engineering',
            suggested_next_step: 'None',
        },
    };

    const mockSourceContext: SourceContext = {
        signed_definition: {
            version: 1,
            signed_off_at: '2026-05-30T10:00:00Z',
            signed_off_by: { id: 'usr-1', name: 'John Admin' },
            notes: 'Official signed definition notes',
            owner: { label: 'Owner', value: 'Finance' },
            sections: [
                {
                    key: 'official_definition',
                    title: 'Official Definition',
                    items: [
                        {
                            key: 'mrr_formula',
                            label: 'MRR Formula',
                            value: 'ARR / 12',
                            companions: [],
                        },
                    ],
                },
                {
                    key: 'controls_caveats',
                    title: 'Controls & Caveats',
                    items: [
                        {
                            key: 'exceptions',
                            label: 'Exceptions',
                            value: 'Exclude internal test accounts',
                            companions: [],
                        },
                    ],
                },
                {
                    key: 'measurement_logic_rules',
                    title: 'Measurement Logic & Rules',
                    items: [
                        {
                            key: 'sql_logic',
                            label: 'SQL Logic',
                            value: 'SELECT sum(amount) FROM billing',
                            companions: [],
                        },
                    ],
                },
            ],
        },
        workshop_alignment: {
            has_evidence: true,
            workshop: {
                id: 'ws-456',
                scheduled_at: '2026-05-30T11:00:00Z',
                notes: 'Consensus achieved on all counts',
                workshop_url: '/workshops/456',
                decisions: {
                    consensus: 'Exclude trial instances',
                    follow_up: ['Verify logic with RevOps team'],
                    field_decisions: {},
                },
            },
            discrepancies: [
                {
                    field_key: 'formula',
                    severity: 'medium',
                    status: 'resolved',
                    description: 'Billing discrepancy fixed',
                },
            ],
        },
        matrix_leftovers: [
            {
                field_key: 'lead_definition',
                label: 'Lead Definition',
                stakeholder_positions: [
                    { stakeholder: 'Finance', raw_value: 'mql', normalized_value: 'mql' },
                    { stakeholder: 'Marketing', raw_value: 'contact', normalized_value: 'contact' },
                ],
                final_value: 'sales_qualified',
                status: 'mismatch',
                reason: 'Finance and Marketing differed',
                used_for: ['validation', 'common_mismatch_causes'],
            },
            {
                field_key: 'costs_included',
                label: 'Costs Included',
                stakeholder_positions: [],
                final_value: 'fully_loaded',
                status: 'operating_view',
                reason: 'Operating view',
                used_for: ['implementation_gaps'],
            },
        ],
    };

    const emptySourceContext: SourceContext = {
        signed_definition: {
            version: 0,
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
    };

    it('renders green available chips when source content is fully available', () => {
        const wrapper = mount(HandbookSignalEditor, {
            props: {
                signals: mockSignals,
                sourceContext: mockSourceContext,
                isEditing: false,
                isSaving: false,
            },
        });

        // 1. Executive Takeaway shows Signed Definition (available)
        const takeawayChips = wrapper.find('[data-test="source-chips-takeaway"]');
        expect(takeawayChips.exists()).toBe(true);
        const signedDefChip = takeawayChips.find('[data-test="source-chip-signed_definition-available"]');
        expect(signedDefChip.exists()).toBe(true);
        expect(signedDefChip.text()).toContain('Signed Definition');

        // 2. Alignment Summary shows Workshop Decision and Resolved Discrepancy (available)
        const alignmentChips = wrapper.find('[data-test="source-chips-alignment"]');
        expect(alignmentChips.exists()).toBe(true);
        const wsDecisionChip = alignmentChips.find('[data-test="source-chip-workshop_decision-available"]');
        expect(wsDecisionChip.exists()).toBe(true);
        expect(wsDecisionChip.text()).toContain('Workshop Decision');
        const discrepancyChip = alignmentChips.find('[data-test="source-chip-resolved_discrepancy-available"]');
        expect(discrepancyChip.exists()).toBe(true);
        expect(discrepancyChip.text()).toContain('Resolved Discrepancy');

        // 3. Status shows Signed Definition Header (available)
        const statusChips = wrapper.find('[data-test="source-chips-status"]');
        expect(statusChips.exists()).toBe(true);
        const statusHeaderChip = statusChips.find('[data-test="source-chip-signed_definition-available"]');
        expect(statusHeaderChip.exists()).toBe(true);
        expect(statusHeaderChip.text()).toContain('Signed Definition Header');

        // 4. Implementation Gaps / Follow-up shows Controls & Caveats and Workshop Follow-up (available)
        const gapsChips = wrapper.find('[data-test="source-chips-gaps"]');
        expect(gapsChips.exists()).toBe(true);
        const controlsChip = gapsChips.find('[data-test="source-chip-implementation_evidence-available"]');
        expect(controlsChip.exists()).toBe(true);
        expect(controlsChip.text()).toContain('Controls & Caveats');
        const followUpChip = gapsChips.find('[data-test="source-chip-workshop_decision-available"]');
        expect(followUpChip.exists()).toBe(true);
        expect(followUpChip.text()).toContain('Workshop Follow-up');
        const gapsLeftoverChip = gapsChips.find('[data-test="source-chip-matrix_leftover-available"]');
        expect(gapsLeftoverChip.exists()).toBe(true);
        expect(gapsLeftoverChip.text()).toContain('Unselected Stakeholder Positions');

        // 5. Validation shows Measurement Logic & Rules and Resolved Discrepancy (available)
        const validationChips = wrapper.find('[data-test="source-chips-validation"]');
        expect(validationChips.exists()).toBe(true);
        const logicChip = validationChips.find('[data-test="source-chip-validation_rule-available"]');
        expect(logicChip.exists()).toBe(true);
        expect(logicChip.text()).toContain('Measurement Logic & Rules');
        const valDiscrepancyChip = validationChips.find('[data-test="source-chip-resolved_discrepancy-available"]');
        expect(valDiscrepancyChip.exists()).toBe(true);
        expect(valDiscrepancyChip.text()).toContain('Resolved Discrepancy');
        const valLeftoverChip = validationChips.find('[data-test="source-chip-matrix_leftover-available"]');
        expect(valLeftoverChip.exists()).toBe(true);
        expect(valLeftoverChip.text()).toContain('Unselected Stakeholder Positions');
    });

    it('renders grey missing chips quietly when sources are empty or missing, without blocking editing', async () => {
        const wrapper = mount(HandbookSignalEditor, {
            props: {
                signals: mockSignals,
                sourceContext: emptySourceContext,
                isEditing: false,
                isSaving: false,
            },
        });

        // Verify chips render as missing
        const takeawayChips = wrapper.find('[data-test="source-chips-takeaway"]');
        expect(takeawayChips.exists()).toBe(true);
        const missingSignedDefChip = takeawayChips.find('[data-test="source-chip-signed_definition-missing"]');
        expect(missingSignedDefChip.exists()).toBe(true);

        const alignmentChips = wrapper.find('[data-test="source-chips-alignment"]');
        expect(alignmentChips.find('[data-test="source-chip-workshop_decision-missing"]').exists()).toBe(true);
        expect(alignmentChips.find('[data-test="source-chip-resolved_discrepancy-missing"]').exists()).toBe(true);

        const gapsChips = wrapper.find('[data-test="source-chips-gaps"]');
        expect(gapsChips.find('[data-test="source-chip-matrix_leftover-missing"]').exists()).toBe(true);

        const validationChips = wrapper.find('[data-test="source-chips-validation"]');
        expect(validationChips.find('[data-test="source-chip-matrix_leftover-missing"]').exists()).toBe(true);

        // Edit button must be present and clickable (editing not blocked)
        const editBtn = wrapper.find('[data-test="edit-section-takeaway-btn"]');
        expect(editBtn.exists()).toBe(true);
        expect(editBtn.attributes('disabled')).toBeUndefined();

        await editBtn.trigger('click');

        // Textarea should show up and allow editing
        const textarea = wrapper.find('textarea#executive_takeaway');
        expect(textarea.exists()).toBe(true);
        expect(textarea.attributes('disabled')).toBeUndefined();
        await textarea.setValue('Modified takeaway text');
        expect(wrapper.vm.draft.executive_takeaway).toBe('Modified takeaway text');
    });

    it('verifies chips do not mutate draft signal fields when clicked', async () => {
        const wrapper = mount(HandbookSignalEditor, {
            props: {
                signals: mockSignals,
                sourceContext: mockSourceContext,
                isEditing: true, // starts in edit mode
                isSaving: false,
            },
        });

        const initialTakeaway = wrapper.vm.draft.executive_takeaway;
        expect(initialTakeaway).toBe('Initial takeaway text');

        // Find a chip and click it
        const chip = wrapper.find('[data-test="source-chip-signed_definition-available"]');
        expect(chip.exists()).toBe(true);
        await chip.trigger('click');

        // Verify the draft takeaway remains unchanged
        expect(wrapper.vm.draft.executive_takeaway).toBe('Initial takeaway text');
    });
});
