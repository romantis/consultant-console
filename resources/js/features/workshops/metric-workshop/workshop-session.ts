import type {
    FinalDefinitionProgress,
    WorkshopProgressVariant,
    WorkshopSession,
} from '@/features/workshops/metric-workshop/types';

export const WORKSHOP_SESSION_START_EARLY_GRACE_MINUTES = 10;
export const WORKSHOP_SESSION_START_LATE_GRACE_MINUTES = 15;

export const cloneWorkshopSessions = (
    items: WorkshopSession[] | null | undefined,
): WorkshopSession[] =>
    (items ?? []).map((item) => ({
        id: item.id,
        organization_id: item.organization_id ?? null,
        engagement_id: item.engagement_id ?? null,
        starts_at: item.starts_at ?? null,
        ends_at: item.ends_at ?? null,
        timezone: item.timezone ?? 'UTC',
        status: item.status ?? null,
        notes: item.notes ?? null,
        metric_ids: [...(item.metric_ids ?? [])],
        participant_ids: [...(item.participant_ids ?? [])],
    }));

export const workshopSessionPriority = (
    status: string | null | undefined,
): number => {
    switch ((status ?? '').toLowerCase()) {
        case 'inprogress':
            return 0;
        case 'scheduled':
            return 1;
        case 'missed':
            return 2;
        default:
            return 3;
    }
};

export const resolvePreferredWorkshopSession = (
    sessions: WorkshopSession[],
): WorkshopSession | null =>
    [...sessions].sort((left, right) => {
        const priorityDelta =
            workshopSessionPriority(left.status) -
            workshopSessionPriority(right.status);

        if (priorityDelta !== 0) {
            return priorityDelta;
        }

        const leftStartsAt = left.starts_at ? new Date(left.starts_at) : null;
        const rightStartsAt = right.starts_at
            ? new Date(right.starts_at)
            : null;
        const leftTimestamp =
            leftStartsAt?.getTime() ?? Number.POSITIVE_INFINITY;
        const rightTimestamp =
            rightStartsAt?.getTime() ?? Number.POSITIVE_INFINITY;

        return leftTimestamp - rightTimestamp;
    })[0] ?? null;

export const formatWorkshopSessionDateTime = (
    value?: string | null,
    timezone?: string | null,
): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const resolvedTimezone = timezone?.trim() || 'UTC';

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: resolvedTimezone,
        }).format(date);
    } catch {
        return date.toLocaleString();
    }
};

export const resolveWorkshopSessionStartDate = (
    session?: WorkshopSession | null,
): Date | null => {
    const startsAt = session?.starts_at;
    if (!startsAt) {
        return null;
    }

    const parsed = new Date(startsAt);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed;
};

export const resolveWorkshopSessionWindowOpensAt = (
    session?: WorkshopSession | null,
): Date | null => {
    const startsAt = resolveWorkshopSessionStartDate(session);
    if (!startsAt) {
        return null;
    }

    return new Date(
        startsAt.getTime() -
            WORKSHOP_SESSION_START_EARLY_GRACE_MINUTES * 60_000,
    );
};

export const resolveWorkshopSessionWindowClosesAt = (
    session?: WorkshopSession | null,
): Date | null => {
    const startsAt = resolveWorkshopSessionStartDate(session);
    if (!startsAt) {
        return null;
    }

    return new Date(
        startsAt.getTime() + WORKSHOP_SESSION_START_LATE_GRACE_MINUTES * 60_000,
    );
};

export const isWorkshopSessionStartWindowOpen = ({
    session,
    now = Date.now(),
}: {
    session?: WorkshopSession | null;
    now?: number;
}): boolean => {
    const windowOpensAt = resolveWorkshopSessionWindowOpensAt(session);
    const windowClosesAt = resolveWorkshopSessionWindowClosesAt(session);

    if (!windowOpensAt || !windowClosesAt) {
        return false;
    }

    return windowOpensAt.getTime() <= now && now <= windowClosesAt.getTime();
};

export const resolveWorkshopProgressLabel = (
    progress?: FinalDefinitionProgress | null,
): string => {
    if (!progress) {
        return 'Missing 0';
    }

    if (progress.state === 'signed_off') {
        return 'Signed off';
    }

    if (progress.state === 'ready') {
        return 'Ready';
    }

    return `Missing ${progress.missing_count ?? 0}`;
};

export const resolveWorkshopProgressVariant = (
    progress?: FinalDefinitionProgress | null,
): WorkshopProgressVariant => {
    if (!progress || progress.state === 'missing') {
        return 'secondary';
    }

    if (progress.state === 'ready') {
        return 'outline';
    }

    return 'default';
};

export const buildInProgressWorkshopMetricIds = ({
    sessions,
    activeSessionStatus,
    fallbackMetricId,
}: {
    sessions: WorkshopSession[];
    activeSessionStatus?: string | null;
    fallbackMetricId?: string | null;
}): Set<string> => {
    const metricIds = new Set<string>();

    sessions.forEach((session) => {
        if ((session.status ?? '').toLowerCase() !== 'inprogress') {
            return;
        }

        (session.metric_ids ?? []).forEach((metricId) => {
            if (metricId.trim() !== '') {
                metricIds.add(metricId);
            }
        });
    });

    if (
        metricIds.size === 0 &&
        activeSessionStatus === 'inprogress' &&
        fallbackMetricId &&
        fallbackMetricId.trim() !== ''
    ) {
        metricIds.add(fallbackMetricId);
    }

    return metricIds;
};

export const hasUpcomingOrInProgressWorkshopSession = (
    sessions: WorkshopSession[],
): boolean =>
    sessions.some((session) => {
        const normalized = (session.status ?? '').toLowerCase();
        return normalized === 'scheduled' || normalized === 'inprogress';
    });

export const resolveLastCompletedWorkshopEndedLabel = (
    sessions: WorkshopSession[],
): string | null => {
    const completedSessions = sessions.filter(
        (session) => (session.status ?? '').toLowerCase() === 'completed',
    );

    if (completedSessions.length === 0) {
        return null;
    }

    const mostRecentSession = [...completedSessions].sort((left, right) => {
        const leftTimestamp = Date.parse(left.ends_at ?? left.starts_at ?? '');
        const rightTimestamp = Date.parse(
            right.ends_at ?? right.starts_at ?? '',
        );
        const safeLeft = Number.isNaN(leftTimestamp) ? 0 : leftTimestamp;
        const safeRight = Number.isNaN(rightTimestamp) ? 0 : rightTimestamp;
        return safeRight - safeLeft;
    })[0];

    const referenceTime =
        mostRecentSession?.ends_at ?? mostRecentSession?.starts_at ?? null;
    if (!referenceTime) {
        return null;
    }

    return formatWorkshopSessionDateTime(
        referenceTime,
        mostRecentSession?.timezone,
    );
};
