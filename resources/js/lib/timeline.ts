export type TimelineEngagement = {
    starts_on?: string | null;
    ends_on?: string | null;
    status?: string | null;
};

export type TimelineForm = {
    id: string;
    title: string;
    status?: string | null;
    last_submission_at?: string | null;
};

export type TimelineWorkshop = {
    id: string;
    status?: string | null;
    starts_at?: string | null;
    ends_at?: string | null;
    timezone?: string | null;
    workshop_date?: string | null;
    notes?: string | null;
    participant_count?: number | null;
    metric_count?: number | null;
};

export type TimelineDefinition = {
    metric_id: string;
    metric_name: string;
    status?: string | null;
    signed_off_at?: string | null;
};

export type TimelineSource = {
    engagement: TimelineEngagement;
    forms?: TimelineForm[];
    workshops?: TimelineWorkshop[];
    final_definitions?: TimelineDefinition[];
};

export type TimelineItem = {
    id: string;
    date: string;
    type: 'milestone' | 'form' | 'workshop' | 'definition';
    title: string;
    description?: string | null;
    meta?: Record<string, unknown>;
};

const isValidDate = (input?: string | null): input is string => {
    if (!input) return false;
    const date = new Date(input);
    return !Number.isNaN(date.getTime());
};

const sortByDate = (a: TimelineItem, b: TimelineItem) => {
    return new Date(a.date).getTime() - new Date(b.date).getTime();
};

export const buildTimeline = (source: TimelineSource): TimelineItem[] => {
    const items: TimelineItem[] = [];

    if (isValidDate(source.engagement?.starts_on)) {
        items.push({
            id: `engagement-start-${source.engagement.starts_on}`,
            date: source.engagement.starts_on!,
            type: 'milestone',
            title: 'Engagement kickoff',
            description: source.engagement.status
                ? `Status: ${source.engagement.status}`
                : null,
        });
    }

    source.forms?.forEach((form) => {
        if (!isValidDate(form.last_submission_at)) {
            return;
        }

        items.push({
            id: `form-${form.id}`,
            date: form.last_submission_at!,
            type: 'form',
            title: form.title,
            description: form.status ? `Marked ${form.status}` : null,
            meta: {
                status: form.status ?? null,
            },
        });
    });

    source.workshops?.forEach((workshop) => {
        const scheduledAt =
            workshop.starts_at ?? workshop.workshop_date ?? null;

        if (!isValidDate(scheduledAt)) {
            return;
        }

        items.push({
            id: `workshop-${workshop.id}`,
            date: scheduledAt!,
            type: 'workshop',
            title: 'Workshop session',
            description: workshop.status
                ? `Status: ${workshop.status}`
                : (workshop.notes ?? null),
            meta: {
                status: workshop.status ?? null,
                starts_at: workshop.starts_at ?? null,
                ends_at: workshop.ends_at ?? null,
                timezone: workshop.timezone ?? null,
                participant_count: workshop.participant_count ?? null,
                metric_count: workshop.metric_count ?? null,
            },
        });
    });

    source.final_definitions?.forEach((definition) => {
        if (!isValidDate(definition.signed_off_at)) {
            return;
        }

        items.push({
            id: `definition-${definition.metric_id}`,
            date: definition.signed_off_at!,
            type: 'definition',
            title: `${definition.metric_name} finalized`,
            description: definition.status
                ? `Status: ${definition.status}`
                : null,
            meta: {
                metric_id: definition.metric_id,
            },
        });
    });

    if (isValidDate(source.engagement?.ends_on)) {
        items.push({
            id: `engagement-end-${source.engagement.ends_on}`,
            date: source.engagement.ends_on!,
            type: 'milestone',
            title: 'Target completion',
            description: source.engagement.status
                ? `Status: ${source.engagement.status}`
                : null,
        });
    }

    return items.sort(sortByDate);
};
