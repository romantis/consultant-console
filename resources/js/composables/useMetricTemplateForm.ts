import { useForm } from '@inertiajs/vue3';

export type FieldType =
    | 'number'
    | 'text'
    | 'textarea'
    | 'select'
    | 'checkbox_group';

export type FieldClassificationRole =
    | 'definition'
    | 'context'
    | 'governance'
    | 'diagnostic';

export type FieldExecutiveSection =
    | 'official_definition'
    | 'measurement_logic_rules'
    | 'controls_caveats';

export type FieldClassificationForm = {
    role: FieldClassificationRole;
    excludeFromConsensus: boolean;
    companionOf: string;
};

export type FieldForm = {
    name: string;
    label: string;
    formLabel: string;
    type: FieldType;
    required: boolean;
    meta: boolean;
    optionsText: string;
    withOther: boolean;
    otherPrompt: string;
    executiveSection: FieldExecutiveSection;
    classification: FieldClassificationForm;
    workshopPrompt: string;
    workshopGuidance: string;
};

export type FormState = {
    canonical_metric_id: string;
    key: string;
    name: string;
    description: string;
    fields: FieldForm[];
};

export type TemplatePayload = {
    canonical_metric_id: string;
    key: string;
    name: string;
    description: string | null;
    form_schema_json: {
        fields: Array<{
            name: string;
            label: string;
            form_label?: string;
            type: FieldType;
            required: boolean;
            meta?: boolean;
            options?: Array<string | { value: string; label: string }>;
            with_other?: boolean;
            other_prompt?: string | null;
            workshop_prompt?: string | null;
            workshop_guidance?: string | null;
            executive_section: FieldExecutiveSection;
            classification: {
                role: FieldClassificationRole;
                exclude_from_consensus: boolean;
                companion_of?: string;
                executive_section: FieldExecutiveSection;
            };
        }>;
    };
};

export type TemplateFieldInput = {
    name?: string | null;
    label?: string | null;
    form_label?: string | null;
    type?: string | null;
    required?: boolean | null;
    meta?: boolean | null;
    options?: Array<string | { value: string; label: string }> | null;
    with_other?: boolean | null;
    other_prompt?: string | null;
    workshop_prompt?: string | null;
    workshop_guidance?: string | null;
    executive_section?: string | null;
    classification?: {
        role?: string | null;
        exclude_from_consensus?: boolean | null;
        companion_of?: string | null;
        executive_section?: string | null;
    } | null;
};

export type TemplateInput = {
    canonical_metric_id?: string | null;
    key: string;
    name: string;
    description: string | null;
    form_schema_json: {
        fields: TemplateFieldInput[];
    } | null;
};

export const fieldTypes: { value: FieldType; label: string }[] = [
    { value: 'number', label: 'Number' },
    { value: 'text', label: 'Short text' },
    { value: 'textarea', label: 'Long text' },
    { value: 'select', label: 'Single select' },
    { value: 'checkbox_group', label: 'Checkbox group' },
];

export const fieldRoleOptions: Array<{
    value: FieldClassificationRole;
    label: string;
    description: string;
}> = [
    {
        value: 'definition',
        label: 'Definition',
        description:
            'Core formula inputs and canonical descriptions shown with the metric summary.',
    },
    {
        value: 'context',
        label: 'Context',
        description:
            'Supplemental notes, segments, or contact info shown in supporting sections.',
    },
    {
        value: 'governance',
        label: 'Governance',
        description:
            'Policies, owners, and approval rules that guide how the metric is used.',
    },
    {
        value: 'diagnostic',
        label: 'Diagnostic',
        description:
            'Breakdowns and debug info surfaced when resolving discrepancies.',
    },
];

export const executiveSectionOptions: Array<{
    value: FieldExecutiveSection;
    label: string;
    description: string;
}> = [
    {
        value: 'official_definition',
        label: 'Official Definition',
        description:
            'Qualification rules, scope, triggers, and formula inputs.',
    },
    {
        value: 'measurement_logic_rules',
        label: 'Measurement Logic & Rules',
        description:
            'Guardrails, exclusions, cadence, and technical counting policies.',
    },
    {
        value: 'controls_caveats',
        label: 'Controls & Caveats',
        description:
            'Known divergence points, uncertainty, caveats, and risk notes.',
    },
];

const classificationDefaults: Record<
    FieldClassificationRole,
    FieldClassificationForm
> = {
    definition: {
        role: 'definition',
        excludeFromConsensus: false,
        companionOf: '',
    },
    context: {
        role: 'context',
        excludeFromConsensus: false,
        companionOf: '',
    },
    governance: {
        role: 'governance',
        excludeFromConsensus: false,
        companionOf: '',
    },
    diagnostic: {
        role: 'diagnostic',
        excludeFromConsensus: false,
        companionOf: '',
    },
};

export const defaultClassification = (
    role: FieldClassificationRole = 'definition',
): FieldClassificationForm => {
    const defaults = classificationDefaults[role];
    return { ...defaults };
};

export const defaultExecutiveSection = (
    role: FieldClassificationRole = 'definition',
): FieldExecutiveSection => {
    return role === 'definition'
        ? 'official_definition'
        : role === 'diagnostic'
          ? 'controls_caveats'
          : 'measurement_logic_rules';
};

const normalizeExecutiveSection = (
    section?: string | null,
): FieldExecutiveSection | null => {
    const candidate =
        typeof section === 'string' ? section.trim().toLowerCase() : '';

    if (
        candidate === 'official_definition' ||
        candidate === 'measurement_logic_rules' ||
        candidate === 'controls_caveats'
    ) {
        return candidate;
    }

    return null;
};

const pickRole = (
    role?: string | null,
    isMeta?: boolean | null,
): FieldClassificationRole => {
    const normalized =
        typeof role === 'string' ? role.toLowerCase().trim() : '';
    if (
        normalized === 'definition' ||
        normalized === 'context' ||
        normalized === 'governance' ||
        normalized === 'diagnostic'
    ) {
        return normalized;
    }

    if (isMeta) {
        return 'context';
    }

    return 'definition';
};

const normalizeClassification = (
    field: TemplateFieldInput,
): FieldClassificationForm => {
    const inferredRole = pickRole(field.classification?.role, field.meta);
    const defaults = defaultClassification(inferredRole);

    const excludeFromConsensus = field.classification?.exclude_from_consensus;
    const companionOf =
        typeof field.classification?.companion_of === 'string'
            ? field.classification.companion_of.trim()
            : '';

    return {
        role: inferredRole,
        excludeFromConsensus:
            typeof excludeFromConsensus === 'boolean'
                ? excludeFromConsensus
                : defaults.excludeFromConsensus,
        companionOf,
    };
};

const resolveExecutiveSection = (
    field: TemplateFieldInput,
    role: FieldClassificationRole,
): FieldExecutiveSection => {
    const fieldLevel = normalizeExecutiveSection(field.executive_section);
    if (fieldLevel !== null) {
        return fieldLevel;
    }

    const classificationLevel = normalizeExecutiveSection(
        field.classification?.executive_section,
    );
    if (classificationLevel !== null) {
        return classificationLevel;
    }

    return defaultExecutiveSection(role);
};

export const defaultField = (): FieldForm => ({
    name: '',
    label: '',
    formLabel: '',
    type: 'number',
    required: true,
    meta: false,
    optionsText: '',
    withOther: false,
    otherPrompt: 'Please specify',
    executiveSection: defaultExecutiveSection(),
    classification: defaultClassification(),
    workshopPrompt: '',
    workshopGuidance: '',
});

export const defaultState = (): FormState => ({
    canonical_metric_id: '',
    key: '',
    name: '',
    description: '',
    fields: [defaultField()],
});

export function buildPayload(state: FormState): TemplatePayload {
    return {
        canonical_metric_id: state.canonical_metric_id,
        key: state.key,
        name: state.name,
        description: state.description ? state.description : null,
        form_schema_json: {
            fields: state.fields.map((field) => {
                const base: TemplatePayload['form_schema_json']['fields'][number] =
                    {
                        name: field.name,
                        label: field.label,
                        type: field.type,
                        required: field.required,
                    };

                const formLabel = field.formLabel?.trim() ?? '';
                if (formLabel.length > 0) {
                    base.form_label = formLabel;
                }

                const workshopPrompt = field.workshopPrompt?.trim() ?? '';
                if (workshopPrompt.length > 0) {
                    base.workshop_prompt = workshopPrompt;
                }

                const workshopGuidance = field.workshopGuidance?.trim() ?? '';
                if (workshopGuidance.length > 0) {
                    base.workshop_guidance = workshopGuidance;
                }

                if (field.meta) {
                    base.meta = true;
                }

                if (
                    field.type === 'select' ||
                    field.type === 'checkbox_group'
                ) {
                    const options = field.optionsText
                        .split('\n')
                        .map((entry) => entry.trim())
                        .filter((entry) => entry.length > 0)
                        .map((entry) => {
                            const [valuePart, ...labelParts] = entry.split('|');
                            const value = (valuePart ?? '').trim();
                            const label = labelParts.join('|').trim();

                            if (label.length > 0) {
                                const canonical =
                                    value.length > 0 ? value : label;
                                return canonical.length > 0
                                    ? { value: canonical, label }
                                    : null;
                            }

                            return value.length > 0 ? value : null;
                        })
                        .filter(
                            (
                                entry,
                            ): entry is
                                | string
                                | { value: string; label: string } =>
                                Boolean(entry),
                        );

                    if (options.length) {
                        base.options = options;
                    }
                }

                if (
                    (field.type === 'checkbox_group' ||
                        field.type === 'select') &&
                    field.withOther
                ) {
                    base.with_other = true;
                    base.other_prompt = field.otherPrompt?.trim()
                        ? field.otherPrompt.trim()
                        : 'Please specify';
                }

                const classification =
                    field.classification ?? defaultClassification();
                const executiveSection =
                    field.executiveSection ??
                    defaultExecutiveSection(classification.role);

                base.classification = {
                    role: classification.role,
                    exclude_from_consensus: classification.excludeFromConsensus,
                    executive_section: executiveSection,
                };
                base.executive_section = executiveSection;

                const companionOf = classification.companionOf.trim();
                if (companionOf.length > 0) {
                    base.classification.companion_of = companionOf;
                }

                return base;
            }),
        },
    };
}

export function formStateFromTemplate(template: TemplateInput): FormState {
    const fields = (template.form_schema_json?.fields ?? []).map((field) => {
        const optionLines = Array.isArray(field.options)
            ? field.options
                  .map((option) => {
                      if (typeof option === 'string') {
                          return option;
                      }

                      const value =
                          typeof option?.value === 'string' ? option.value : '';
                      const label =
                          typeof option?.label === 'string' ? option.label : '';

                      if (value && label) {
                          return `${value} | ${label}`;
                      }

                      return value || label;
                  })
                  .map((line) => (typeof line === 'string' ? line.trim() : ''))
                  .filter((line) => line.length > 0)
            : [];

        const classification = normalizeClassification(field);

        return {
            name: field.name ?? '',
            label: field.label ?? '',
            formLabel: field.form_label ?? '',
            type: (field.type as FieldType) ?? 'number',
            required: Boolean(field.required),
            meta: Boolean((field as { meta?: boolean }).meta),
            optionsText: optionLines.join('\n'),
            withOther: Boolean((field as { with_other?: boolean }).with_other),
            otherPrompt:
                (field as { other_prompt?: string }).other_prompt ??
                'Please specify',
            classification,
            executiveSection: resolveExecutiveSection(
                field,
                classification.role,
            ),
            workshopPrompt: field.workshop_prompt ?? '',
            workshopGuidance: field.workshop_guidance ?? '',
        };
    });

    return {
        canonical_metric_id: template.canonical_metric_id ?? '',
        key: template.key,
        name: template.name,
        description: template.description ?? '',
        fields: fields.length ? fields : [defaultField()],
    };
}

export function useMetricTemplateForm(initialState?: FormState) {
    const state = initialState ?? defaultState();
    const form = useForm<FormState>({
        canonical_metric_id: state.canonical_metric_id,
        key: state.key,
        name: state.name,
        description: state.description,
        fields: state.fields.length ? state.fields : [defaultField()],
    });

    form.transform((data) => buildPayload(data));

    function addField() {
        form.fields.push(defaultField());
    }

    function removeField(index: number) {
        if (form.fields.length <= 1) return;
        form.fields.splice(index, 1);
    }

    return {
        form,
        addField,
        removeField,
        fieldTypes,
    };
}
