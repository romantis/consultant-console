import type {
    DefinitionFieldRow,
    EditableImplementationGaps,
    EditableRow,
    FieldRoleEntry,
    FinalDefinition,
    FinalDefinitionDraftValues,
    FinalDefinitionJson,
    FinalDefinitionRequirement,
    ImplementationGaps,
    MatrixRow,
    OwnerOption,
    SummaryRow,
} from '@/features/workshops/metric-workshop/types';

const VALIDATION_GUIDANCE_FIELD_KEYS = new Set([
    'validation_steps',
    'common_mismatch_causes',
]);

export const isValidationGuidanceField = (fieldKey: string): boolean =>
    VALIDATION_GUIDANCE_FIELD_KEYS.has(fieldKey);

export const isConsensusFieldVisible = (entry: FieldRoleEntry): boolean =>
    entry.exclude_from_consensus !== true &&
    !isValidationGuidanceField(entry.key);

export const buildDefinitionFieldRows = ({
    fieldRoles,
    requiredFields,
    definitionJson,
}: {
    fieldRoles: FieldRoleEntry[];
    requiredFields: FinalDefinitionRequirement[];
    definitionJson: FinalDefinitionJson;
}): DefinitionFieldRow[] => {
    const rows = new Map<string, DefinitionFieldRow>();
    const requiredFieldMap = new Map(
        requiredFields.map((field) => [field.key, field]),
    );
    const requiredFieldKeySet = new Set(
        requiredFields.map((field) => field.key),
    );
    const fieldRolesByKey = new Map(
        fieldRoles.map((entry) => [entry.key, entry]),
    );

    fieldRoles.forEach((entry) => {
        if (!isConsensusFieldVisible(entry)) {
            return;
        }

        const requiredField = requiredFieldMap.get(entry.key);

        rows.set(entry.key, {
            key: entry.key,
            label: requiredField?.label ?? entry.label,
            required: requiredField !== undefined,
            companion_of: entry.companion_of ?? null,
        });
    });

    requiredFields.forEach((field) => {
        if (rows.has(field.key)) {
            return;
        }

        const roleEntry = fieldRolesByKey.get(field.key);

        rows.set(field.key, {
            key: field.key,
            label: field.label ?? roleEntry?.label ?? field.key,
            required: true,
            companion_of: roleEntry?.companion_of ?? null,
        });
    });

    Object.entries(definitionJson ?? {}).forEach(([key, entry]) => {
        if (rows.has(key)) {
            return;
        }

        if (isValidationGuidanceField(key)) {
            return;
        }

        rows.set(key, {
            key,
            label: entry?.label ?? key,
            required: requiredFieldKeySet.has(key),
            companion_of:
                entry?.companion_of ??
                fieldRolesByKey.get(key)?.companion_of ??
                null,
        });
    });

    return Array.from(rows.values());
};

export const resolveMissingFieldLabels = ({
    requiredFields,
    missingKeys,
    ownerEligible,
}: {
    requiredFields: FinalDefinitionRequirement[];
    missingKeys: string[];
    ownerEligible: boolean;
}): string[] => {
    const missing = new Map<string, string>();

    requiredFields.forEach((field) => {
        if (missingKeys.includes(field.key)) {
            missing.set(field.key, field.label);
        }
    });

    if (!ownerEligible) {
        missing.set('owner', 'Metric owner');
    }

    return Array.from(missing.values());
};

export const buildInitialEditableRows = ({
    definitionFieldRows,
    definitionJson,
    matrix,
}: {
    definitionFieldRows: DefinitionFieldRow[];
    definitionJson: FinalDefinitionJson;
    matrix: MatrixRow[];
}): EditableRow[] => {
    const definitionRows = definitionFieldRows.map((entry) => ({
        field: entry.key,
        label: entry.label,
        value: definitionJson?.[entry.key]?.value ?? '',
        companion_of:
            definitionJson?.[entry.key]?.companion_of ??
            entry.companion_of ??
            null,
    }));

    if (definitionRows.length > 0) {
        return definitionRows;
    }

    return matrix
        .filter((row) => !isValidationGuidanceField(row.field))
        .map((row) => ({
            field: row.field,
            label: row.label,
            value: definitionJson?.[row.field]?.value ?? '',
            companion_of: definitionJson?.[row.field]?.companion_of ?? null,
        }));
};

export const createFinalDefinitionDraftValues = ({
    definitionFieldRows,
    finalDefinition,
    matrix,
}: {
    definitionFieldRows: DefinitionFieldRow[];
    finalDefinition: FinalDefinition;
    matrix: MatrixRow[];
}): FinalDefinitionDraftValues => ({
    rows: buildInitialEditableRows({
        definitionFieldRows,
        definitionJson: finalDefinition.definition_json ?? {},
        matrix,
    }),
    notes: finalDefinition.notes ?? '',
    alignmentSummary: finalDefinition.alignment_summary ?? '',
    ownerParticipantId: finalDefinition.owner_participant_id ?? '',
});

export function createEditableImplementationGaps(
    implementationGaps?: ImplementationGaps | null,
): EditableImplementationGaps {
    return {
        current_state: implementationGaps?.current_state ?? '',
        gap: implementationGaps?.gap ?? '',
        owner: implementationGaps?.owner ?? '',
        suggested_next_step:
            implementationGaps?.suggested_next_step ??
            implementationGaps?.next_step ??
            '',
    };
}

export function serializeImplementationGaps(
    implementationGaps: EditableImplementationGaps,
): string {
    return JSON.stringify({
        current_state: implementationGaps.current_state ?? '',
        gap: implementationGaps.gap ?? '',
        owner: implementationGaps.owner ?? '',
        suggested_next_step: implementationGaps.suggested_next_step ?? '',
    });
}

export function normalizeOptionalDraftText(value: string): string | null {
    return value.trim() === '' ? null : value;
}

export const serializeEditableRows = (rows: EditableRow[]): string =>
    JSON.stringify(
        rows.map((row) => ({
            field: row.field,
            label: row.label,
            value: row.value ?? '',
            companion_of: row.companion_of ?? null,
        })),
    );

export const parseEditableRowsSnapshot = (snapshot: string): EditableRow[] =>
    (JSON.parse(snapshot) as EditableRow[]).map((row) => ({
        field: row.field,
        label: row.label,
        value: row.value ?? '',
        companion_of: row.companion_of ?? null,
    }));

export const parseImplementationGapsSnapshot = (
    snapshot: string,
): EditableImplementationGaps => {
    const parsed = JSON.parse(snapshot) as EditableImplementationGaps;

    return {
        current_state: parsed.current_state ?? '',
        gap: parsed.gap ?? '',
        owner: parsed.owner ?? '',
        suggested_next_step: parsed.suggested_next_step ?? '',
    };
};

export const cloneFinalDefinitionDraftValues = (
    draftValues: FinalDefinitionDraftValues,
): FinalDefinitionDraftValues => ({
    rows: parseEditableRowsSnapshot(serializeEditableRows(draftValues.rows)),
    notes: draftValues.notes ?? '',
    alignmentSummary: draftValues.alignmentSummary ?? '',
    ownerParticipantId: draftValues.ownerParticipantId ?? '',
});

export const hasFinalDefinitionDraftChanges = ({
    current,
    saved,
}: {
    current: FinalDefinitionDraftValues;
    saved: FinalDefinitionDraftValues;
}): boolean =>
    serializeEditableRows(current.rows) !== serializeEditableRows(saved.rows) ||
    (current.notes ?? '') !== (saved.notes ?? '') ||
    (current.ownerParticipantId ?? '') !== (saved.ownerParticipantId ?? '') ||
    (current.alignmentSummary ?? '') !== (saved.alignmentSummary ?? '');

export const buildImplementationGapsPayload = (
    implementationGaps: EditableImplementationGaps,
): ImplementationGaps | null => {
    const payload: ImplementationGaps = {
        current_state: normalizeOptionalDraftText(
            implementationGaps.current_state,
        ),
        gap: normalizeOptionalDraftText(implementationGaps.gap),
        owner: normalizeOptionalDraftText(implementationGaps.owner),
        suggested_next_step: normalizeOptionalDraftText(
            implementationGaps.suggested_next_step,
        ),
    };

    return [
        payload.current_state,
        payload.gap,
        payload.owner,
        payload.suggested_next_step,
    ].some((value) => value !== null)
        ? payload
        : null;
};

export const buildFinalDefinitionDraftPayload = (
    draftValues: FinalDefinitionDraftValues,
): {
    definition: Array<{
        field: string;
        label: string;
        value: string;
        companion_of: string | null;
    }>;
    notes: string;
    alignment_summary: string | null;
    owner_participant_id: string | null;
} => ({
    definition: draftValues.rows.map((row) => ({
        field: row.field,
        label: row.label,
        value: row.value,
        companion_of: row.companion_of ?? null,
    })),
    notes: draftValues.notes,
    alignment_summary: normalizeOptionalDraftText(draftValues.alignmentSummary),
    owner_participant_id: draftValues.ownerParticipantId || null,
});

export const formatOwnerOptionDetails = (
    option: OwnerOption,
): string | null => {
    const details = [option.responsibility, option.affiliation]
        .filter((value) => value && value.trim() !== '')
        .join(' • ');

    return details !== '' ? details : null;
};

export const formatOwnerOptionLabel = (option: OwnerOption): string => {
    const details = formatOwnerOptionDetails(option);

    return details ? `${option.label} - ${details}` : option.label;
};

export const buildSummaryRows = ({
    definitionFieldRows,
    definitionJson,
}: {
    definitionFieldRows: DefinitionFieldRow[];
    definitionJson: FinalDefinitionJson;
}): SummaryRow[] =>
    definitionFieldRows
        .map((field) => ({
            key: field.key,
            label: field.label,
            required: field.required,
            value: definitionJson?.[field.key]?.value ?? null,
        }))
        .filter(
            (row) =>
                row.required ||
                (typeof row.value === 'string'
                    ? row.value.trim() !== ''
                    : row.value !== null),
        );

export const buildFinalDefinitionValuesByField = (
    definitionJson: FinalDefinitionJson,
): Record<string, string | null> =>
    Object.fromEntries(
        Object.entries(definitionJson ?? {}).map(([field, entry]) => [
            field,
            entry.value ?? null,
        ]),
    );

export const resolveOwnerSummaryLabel = ({
    finalDefinition,
    ownerOptions,
}: {
    finalDefinition: FinalDefinition;
    ownerOptions: OwnerOption[];
}): string | null => {
    if (finalDefinition.owner_participant?.user?.name) {
        return finalDefinition.owner_participant.user.name;
    }

    if (finalDefinition.owner_participant_id) {
        const match = ownerOptions.find(
            (option) => option.id === finalDefinition.owner_participant_id,
        );

        return match?.label ?? 'Pending invite';
    }

    return null;
};
