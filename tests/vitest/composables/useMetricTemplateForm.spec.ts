import { describe, expect, it } from 'vitest';
import {
    buildPayload,
    formStateFromTemplate,
    defaultField,
    type FormState,
    type TemplateInput,
} from '@/composables/useMetricTemplateForm';

describe('useMetricTemplateForm composable helpers', () => {
    it('buildPayload() includes trimmed workshop_prompt and workshop_guidance', () => {
        const state: FormState = {
            canonical_metric_id: 'canonical-1',
            key: 'test_metric',
            name: 'Test Metric',
            description: 'A test description',
            fields: [
                {
                    ...defaultField(),
                    name: 'field_1',
                    label: 'Field 1',
                    workshopPrompt: '  Agree on this field.  ',
                    workshopGuidance: '  Guidance for this field.  ',
                },
            ],
        };

        const payload = buildPayload(state);

        expect(payload.form_schema_json.fields[0].workshop_prompt).toBe('Agree on this field.');
        expect(payload.form_schema_json.fields[0].workshop_guidance).toBe('Guidance for this field.');
    });

    it('empty prompt and guidance are omitted from payload fields', () => {
        const state: FormState = {
            canonical_metric_id: 'canonical-1',
            key: 'test_metric',
            name: 'Test Metric',
            description: 'A test description',
            fields: [
                {
                    ...defaultField(),
                    name: 'field_1',
                    label: 'Field 1',
                    workshopPrompt: '   ',
                    workshopGuidance: '',
                },
            ],
        };

        const payload = buildPayload(state);

        expect(payload.form_schema_json.fields[0].workshop_prompt).toBeUndefined();
        expect(payload.form_schema_json.fields[0].workshop_guidance).toBeUndefined();
    });

    it('formStateFromTemplate() maps existing workshop_prompt and workshop_guidance to camelCase properties', () => {
        const template: TemplateInput = {
            canonical_metric_id: 'canonical-1',
            key: 'test_metric',
            name: 'Test Metric',
            description: 'A test description',
            form_schema_json: {
                fields: [
                    {
                        name: 'field_1',
                        label: 'Field 1',
                        type: 'number',
                        required: true,
                        workshop_prompt: 'Agree on this field.',
                        workshop_guidance: 'Guidance for this field.',
                    },
                    {
                        name: 'field_2',
                        label: 'Field 2',
                        type: 'number',
                        required: false,
                    },
                ],
            },
        };

        const state = formStateFromTemplate(template);

        expect(state.fields[0].workshopPrompt).toBe('Agree on this field.');
        expect(state.fields[0].workshopGuidance).toBe('Guidance for this field.');

        expect(state.fields[1].workshopPrompt).toBe('');
        expect(state.fields[1].workshopGuidance).toBe('');
    });
});
