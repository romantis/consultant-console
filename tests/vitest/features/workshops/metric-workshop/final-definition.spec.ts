import {
    buildDefinitionFieldRows,
    buildImplementationGapsPayload,
    buildInitialEditableRows,
    parseEditableRowsSnapshot,
    resolveMissingFieldLabels,
    resolveOwnerSummaryLabel,
    serializeEditableRows,
} from '@/features/workshops/metric-workshop/final-definition';
import type {
    FinalDefinition,
    OwnerOption,
} from '@/features/workshops/metric-workshop/types';

describe('metric workshop final-definition helpers', () => {
    it('builds definition rows from field roles, required fields, and saved definition data', () => {
        const rows = buildDefinitionFieldRows({
            fieldRoles: [
                {
                    key: 'metric_value',
                    label: 'Metric value',
                    role: 'definition',
                    companion_of: null,
                    value: null,
                    unclassified: false,
                },
                {
                    key: 'hidden_field',
                    label: 'Hidden field',
                    role: 'definition',
                    exclude_from_consensus: true,
                    companion_of: null,
                    value: null,
                    unclassified: false,
                },
            ],
            requiredFields: [
                {
                    key: 'metric_value',
                    label: 'Metric Value',
                },
                {
                    key: 'owner_notes',
                    label: 'Owner Notes',
                },
            ],
            definitionJson: {
                custom_note: {
                    label: 'Custom Note',
                    value: 'Keep this',
                },
            },
        });

        expect(rows).toEqual([
            {
                key: 'metric_value',
                label: 'Metric Value',
                required: true,
                companion_of: null,
            },
            {
                key: 'owner_notes',
                label: 'Owner Notes',
                required: true,
                companion_of: null,
            },
            {
                key: 'custom_note',
                label: 'Custom Note',
                required: false,
                companion_of: null,
            },
        ]);
    });

    it('falls back to matrix rows when there are no explicit definition rows yet', () => {
        const rows = buildInitialEditableRows({
            definitionFieldRows: [],
            definitionJson: {
                metric_value: {
                    label: 'Metric Value',
                    value: '42',
                },
            },
            matrix: [
                {
                    field: 'metric_value',
                    label: 'Metric Value',
                    type: 'text',
                    values: [],
                    discrepancy: null,
                },
            ],
        });

        expect(rows).toEqual([
            {
                field: 'metric_value',
                label: 'Metric Value',
                value: '42',
                companion_of: null,
            },
        ]);
    });

    it('resolves missing-field labels from required keys and owner eligibility', () => {
        const missingFields = resolveMissingFieldLabels({
            requiredFields: [
                {
                    key: 'metric_value',
                    label: 'Metric Value',
                },
                {
                    key: 'owner_notes',
                    label: 'Owner Notes',
                },
            ],
            missingKeys: ['owner_notes'],
            ownerEligible: false,
        });

        expect(missingFields).toEqual(['Owner Notes', 'Metric owner']);
    });

    it('serializes editable rows and restores normalized row snapshots', () => {
        const snapshot = serializeEditableRows([
            {
                field: 'metric_value',
                label: 'Metric Value',
                value: 'Aligned',
                companion_of: undefined,
            },
        ]);

        expect(parseEditableRowsSnapshot(snapshot)).toEqual([
            {
                field: 'metric_value',
                label: 'Metric Value',
                value: 'Aligned',
                companion_of: null,
            },
        ]);
    });

    it('builds implementation gap payloads only when at least one value is present', () => {
        expect(
            buildImplementationGapsPayload({
                current_state: '  ',
                gap: '',
                owner: '',
                suggested_next_step: '',
            }),
        ).toBeNull();

        expect(
            buildImplementationGapsPayload({
                current_state: 'Current flow',
                gap: '   ',
                owner: '',
                suggested_next_step: 'Clarify metric owner',
            }),
        ).toEqual({
            current_state: 'Current flow',
            gap: null,
            owner: null,
            suggested_next_step: 'Clarify metric owner',
        });
    });

    it('resolves the owner summary from participant name, option label, or pending invite fallback', () => {
        const ownerOptions: OwnerOption[] = [
            {
                id: 'participant-1',
                label: 'Jane Owner',
                responsibility: 'Team lead',
                affiliation: 'Product',
                is_default: false,
                disabled: false,
                user: null,
            },
        ];
        const baseFinalDefinition: FinalDefinition = {
            id: 'fd-1',
            status: 'draft',
            definition_json: {},
            notes: null,
            owner_participant_id: 'participant-1',
            owner_participant: null,
            signed_off_at: null,
            signed_off_by: null,
        };

        expect(
            resolveOwnerSummaryLabel({
                finalDefinition: {
                    ...baseFinalDefinition,
                    owner_participant: {
                        id: 'participant-1',
                        responsibility: 'Team lead',
                        user: {
                            id: 'user-1',
                            name: 'Resolved User',
                        },
                    },
                },
                ownerOptions,
            }),
        ).toBe('Resolved User');

        expect(
            resolveOwnerSummaryLabel({
                finalDefinition: baseFinalDefinition,
                ownerOptions,
            }),
        ).toBe('Jane Owner');

        expect(
            resolveOwnerSummaryLabel({
                finalDefinition: {
                    ...baseFinalDefinition,
                    owner_participant_id: 'participant-missing',
                },
                ownerOptions,
            }),
        ).toBe('Pending invite');
    });
});
