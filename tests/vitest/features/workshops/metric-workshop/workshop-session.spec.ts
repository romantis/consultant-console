import {
    buildInProgressWorkshopMetricIds,
    cloneWorkshopSessions,
    formatWorkshopSessionDateTime,
    isWorkshopSessionStartWindowOpen,
    resolveLastCompletedWorkshopEndedLabel,
    resolvePreferredWorkshopSession,
    resolveWorkshopProgressLabel,
    resolveWorkshopProgressVariant,
    resolveWorkshopSessionWindowClosesAt,
    resolveWorkshopSessionWindowOpensAt,
} from '@/features/workshops/metric-workshop/workshop-session';
import type { WorkshopSession } from '@/features/workshops/metric-workshop/types';

describe('metric workshop session helpers', () => {
    it('clones workshop sessions and normalizes missing defaults', () => {
        const source: WorkshopSession[] = [
            {
                id: 'session-1',
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
            },
        ];

        const cloned = cloneWorkshopSessions(source);

        source[0].metric_ids?.push('metric-2');
        source[0].participant_ids?.push('participant-2');

        expect(cloned).toEqual([
            {
                id: 'session-1',
                organization_id: null,
                engagement_id: null,
                starts_at: null,
                ends_at: null,
                timezone: 'UTC',
                status: null,
                notes: null,
                metric_ids: ['metric-1'],
                participant_ids: ['participant-1'],
            },
        ]);
    });

    it('prefers in-progress sessions before scheduled ones and uses start time as the tie-breaker', () => {
        const preferred = resolvePreferredWorkshopSession([
            {
                id: 'scheduled-late',
                status: 'scheduled',
                starts_at: '2026-03-12T12:00:00Z',
            },
            {
                id: 'scheduled-early',
                status: 'scheduled',
                starts_at: '2026-03-12T09:00:00Z',
            },
            {
                id: 'in-progress',
                status: 'inprogress',
                starts_at: '2026-03-12T10:00:00Z',
            },
        ]);

        expect(preferred?.id).toBe('in-progress');

        const scheduledPreferred = resolvePreferredWorkshopSession([
            {
                id: 'scheduled-late',
                status: 'scheduled',
                starts_at: '2026-03-12T12:00:00Z',
            },
            {
                id: 'scheduled-early',
                status: 'scheduled',
                starts_at: '2026-03-12T09:00:00Z',
            },
        ]);

        expect(scheduledPreferred?.id).toBe('scheduled-early');
    });

    it('derives live metric ids from in-progress sessions and falls back to the current metric when needed', () => {
        const explicitMetricIds = buildInProgressWorkshopMetricIds({
            sessions: [
                {
                    id: 'session-1',
                    status: 'inprogress',
                    metric_ids: ['metric-2', ''],
                },
            ],
            activeSessionStatus: 'inprogress',
            fallbackMetricId: 'metric-1',
        });

        expect(Array.from(explicitMetricIds)).toEqual(['metric-2']);

        const fallbackMetricIds = buildInProgressWorkshopMetricIds({
            sessions: [
                {
                    id: 'session-2',
                    status: 'inprogress',
                    metric_ids: [],
                },
            ],
            activeSessionStatus: 'inprogress',
            fallbackMetricId: 'metric-1',
        });

        expect(Array.from(fallbackMetricIds)).toEqual(['metric-1']);
    });

    it('maps workshop progress states to badge labels and variants', () => {
        expect(resolveWorkshopProgressLabel(null)).toBe('Missing 0');
        expect(resolveWorkshopProgressVariant(null)).toBe('secondary');
        expect(
            resolveWorkshopProgressLabel({
                state: 'missing',
                missing_count: 2,
            }),
        ).toBe('Missing 2');
        expect(
            resolveWorkshopProgressVariant({
                state: 'ready',
            }),
        ).toBe('outline');
        expect(
            resolveWorkshopProgressLabel({
                state: 'signed_off',
            }),
        ).toBe('Signed off');
        expect(
            resolveWorkshopProgressVariant({
                state: 'signed_off',
            }),
        ).toBe('default');
    });

    it('derives the workshop session start window around the scheduled start time', () => {
        const session: WorkshopSession = {
            id: 'session-1',
            starts_at: '2026-03-12T10:00:00Z',
        };

        expect(resolveWorkshopSessionWindowOpensAt(session)?.toISOString()).toBe(
            '2026-03-12T09:50:00.000Z',
        );
        expect(
            resolveWorkshopSessionWindowClosesAt(session)?.toISOString(),
        ).toBe('2026-03-12T10:15:00.000Z');
        expect(
            isWorkshopSessionStartWindowOpen({
                session,
                now: Date.parse('2026-03-12T10:05:00Z'),
            }),
        ).toBe(true);
        expect(
            isWorkshopSessionStartWindowOpen({
                session,
                now: Date.parse('2026-03-12T10:20:00Z'),
            }),
        ).toBe(false);
    });

    it('resolves the last completed workshop label from the most recent completed session', () => {
        const sessions: WorkshopSession[] = [
            {
                id: 'completed-older',
                status: 'completed',
                ends_at: '2026-03-12T08:00:00Z',
                timezone: 'UTC',
            },
            {
                id: 'completed-newer',
                status: 'completed',
                ends_at: '2026-03-12T11:30:00Z',
                timezone: 'UTC',
            },
            {
                id: 'scheduled',
                status: 'scheduled',
                starts_at: '2026-03-13T08:00:00Z',
                timezone: 'UTC',
            },
        ];

        expect(resolveLastCompletedWorkshopEndedLabel(sessions)).toBe(
            formatWorkshopSessionDateTime('2026-03-12T11:30:00Z', 'UTC'),
        );
    });
});
