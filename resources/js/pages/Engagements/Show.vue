<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { resolveCsrfHeaders } from '@/lib/csrf';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/composables/useToast';
import {
    buildTimeline,
    type TimelineItem,
    type TimelineSource,
} from '@/lib/timeline';
import { show as formsShow } from '@/routes/forms';
import org, { show as orgShow } from '@/routes/org';
import engagements, {
    handbookWorkbench as orgEngagementsHandbookWorkbench,
    index as orgEngagementsIndex,
    workshop as orgEngagementsWorkshop,
} from '@/routes/org/engagements';
import { prep as orgEngagementsMetricsPrep } from '@/routes/org/engagements/metrics/index';
import { store as orgEngagementRemindersStore } from '@/routes/org/engagements/reminders';
import { update as orgEngagementStatusUpdate } from '@/routes/org/engagements/status';
import type { BreadcrumbItem, FormRow, MemberRow, TemplateRow } from '@/types';
import { Clock, Compass, EllipsisVertical } from 'lucide-vue-next';
import MetricParticipantAssignments from './Show/MetricParticipantAssignments.vue';
import StageReadinessPanel from './Show/StageReadinessPanel.vue';

type Stakeholder = {
    name?: string | null;
    email?: string | null;
    role?: string | null;
    department?: string | null;
};
type RepositoryInfo = {
    driver?: string | null;
    reference?: string | null;
    notes?: string | null;
};

type EngagementPayload = {
    id: string;
    title: string;
    status: string;
    starts_on?: string | null;
    ends_on?: string | null;
    repository: RepositoryInfo;
    stakeholders: Stakeholder[];
    checklist?: Record<string, boolean> | null;
};

type DeliverableItem = {
    metric_id: string;
    metric_name: string;
    template?: { key: string; name: string } | null;
    signed_off_at?: string | null;
    signed_off_by?: { id: string; name: string } | null;
};

type DeliverablesPayload = {
    finalized_at?: string | null;
    total: number;
    items: DeliverableItem[];
};

type FormItem = FormRow & { metric_id?: string | null };

type FormsPayload = {
    total: number;
    status_counts: Record<string, number>;
    last_submission_at?: string | null;
    items: FormItem[];
    templates: TemplateRow[];
    members: MemberRow[];
    can_create: boolean;
    refresh_url?: string | null;
};

type MetricParticipantAssignee = {
    id: string;
    name?: string | null;
    email?: string | null;
    type: string;
    is_active: boolean;
    is_pending: boolean;
    membership_role?: string | null;
    stakeholder_role?: string | null;
};

type MetricParticipantRow = {
    id: string;
    responsibility: string;
    role_tags: string[];
    assignee: MetricParticipantAssignee | null;
};

type ParticipantOption = {
    id: string;
    name?: string | null;
    email?: string | null;
    type: string;
    is_active: boolean;
    is_pending: boolean;
    membership_role?: string | null;
    stakeholder_role?: string | null;
};

type MetricItem = {
    id: string;
    name: string;
    status: string;
    last_submitted_at?: string | null;
    workshop_schedule_readiness?: {
        state: 'ready' | 'missing_forms' | 'pending_submissions';
        forms_total: number;
        forms_ready: number;
    } | null;
    template?: { key: string; name: string } | null;
    final_definition?: {
        status?: string | null;
        signed_off_at?: string | null;
        signed_off_by?: { id: string; name: string } | null;
    } | null;
    final_definition_progress?: {
        state: 'signed_off' | 'ready' | 'missing';
        missing_count?: number;
    } | null;
    participants: MetricParticipantRow[];
};

type MetricsPayload = {
    total: number;
    status_counts: Record<string, number>;
    items: MetricItem[];
    assignment_options: ParticipantOption[];
};

type WorkshopItem = {
    id: string;
    organization_id: string;
    engagement_id: string;
    status: string;
    starts_at?: string | null;
    ends_at?: string | null;
    timezone?: string | null;
    facilitator_membership_id?: string | null;
    facilitator_name?: string | null;
    notes?: string | null;
    metric_ids: string[];
    participant_ids: string[];
    metric_count: number;
    participant_count: number;
};

type WorkshopSessionFormState = {
    session_date: string;
    starts_time: string;
    ends_time: string;
    timezone: string;
    notes: string;
    metric_ids: string[];
    participant_ids: string[];
};

type ActivityItem = {
    id: string;
    action: string;
    created_at?: string | null;
    summary?: string | null;
    user?: { id: string; name: string } | null;
};

type ReminderAttemptItem = {
    id: string;
    status?: string | null;
    channel?: string | null;
    window?: string | null;
    attempted_at?: string | null;
    metric_name?: string | null;
    recipient_email?: string | null;
    reason?: string | null;
};

type RemindersPayload = {
    last_schedule_change_at?: string | null;
    last_attempt_at?: string | null;
    pending_due_now_count?: number | null;
    failed_attempts_24h?: number | null;
    recent_attempts?: ReminderAttemptItem[];
};

type Permissions = {
    can_update: boolean;
    can_send_reminders: boolean;
    can_change_status: boolean;
    can_manage_assignments: boolean;
};

type StatusOption = { value: string; label: string };

type RailReadiness = {
    intake_ready: boolean;
    forms_ready: boolean;
    review_ready: boolean;
    sign_off_ready: boolean;
    handbook_ready: boolean;
    blockers: string[];
};

type PageProps = {
    organization: { id: string; name: string; timezone?: string | null };
    engagement: EngagementPayload;
    forms: FormsPayload;
    metrics: MetricsPayload;
    deliverables: DeliverablesPayload;
    rail_readiness: RailReadiness;
    workshops: WorkshopItem[];
    timeline: TimelineSource;
    activity: ActivityItem[];
    reminders: RemindersPayload;
    permissions: Permissions;
    statusOptions: StatusOption[];
};

const props = defineProps<PageProps>();
const { toast } = useToast();
const page = usePage();
const isInternalStaff = computed(() =>
    Boolean(page.props.auth?.user?.is_internal_staff),
);
const isStaff = isInternalStaff;

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organizations', href: org.index().url },
    {
        title: props.organization.name,
        href: orgShow(props.organization.id).url,
    },
    {
        title: 'Engagements',
        href: orgEngagementsIndex(props.organization.id).url,
    },
    { title: props.engagement.title, href: '' },
];

const formsPayload = computed(() => props.forms);
const formItems = computed(() => formsPayload.value.items ?? []);
const formShowHref = (formId: string): string => {
    const route = formsShow(formId);

    return typeof route === 'string' ? route : (route?.url ?? '#');
};
const latestFormsByMetricId = computed(() => {
    const formsByMetricId = new Map<string, FormItem>();

    for (const item of formItems.value) {
        const metricId = item.metric_id?.trim();
        if (!metricId || formsByMetricId.has(metricId)) {
            continue;
        }

        formsByMetricId.set(metricId, item);
    }

    return formsByMetricId;
});

const cloneWorkshopItems = (items: WorkshopItem[]): WorkshopItem[] =>
    (items ?? []).map((item) => ({
        id: item.id,
        organization_id: item.organization_id,
        engagement_id: item.engagement_id,
        status: item.status,
        starts_at: item.starts_at ?? null,
        ends_at: item.ends_at ?? null,
        timezone: item.timezone ?? 'UTC',
        facilitator_membership_id: item.facilitator_membership_id ?? null,
        facilitator_name: item.facilitator_name ?? null,
        notes: item.notes ?? null,
        metric_ids: [...(item.metric_ids ?? [])],
        participant_ids: [...(item.participant_ids ?? [])],
        metric_count: Number(item.metric_count ?? 0),
        participant_count: Number(item.participant_count ?? 0),
    }));

const workshopSessionsState = ref<WorkshopItem[]>(
    cloneWorkshopItems(props.workshops),
);

watch(
    () => props.workshops,
    (next) => {
        workshopSessionsState.value = cloneWorkshopItems(next ?? []);
    },
    { deep: true },
);

const workshopSessions = computed(() => workshopSessionsState.value);

const isReloadingFormMetrics = ref(false);

const metricWorkshopPrepHref = (metricId?: string | null) => {
    if (!metricId || !props.engagement?.id) {
        return '';
    }

    const route = orgEngagementsMetricsPrep([
        props.organization.id,
        props.engagement.id,
        metricId,
    ]);
    if (typeof route === 'string') {
        return route;
    }

    return route?.url ?? '';
};

const reloadFormAndMetricState = () => {
    if (isReloadingFormMetrics.value) {
        return;
    }

    isReloadingFormMetrics.value = true;
    router.reload({
        only: ['forms', 'metrics'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isReloadingFormMetrics.value = false;
        },
    });
};

const handleSubmissionStorage = (event: StorageEvent) => {
    if (event.key !== 'mh:lastSubmissionUpdate' || !event.newValue) return;

    try {
        const payload = JSON.parse(event.newValue) as {
            formId?: string;
            event?: string;
        };
        if (!payload?.formId) return;

        reloadFormAndMetricState();
    } catch (error) {
        console.error('Unable to parse submission update payload', error);
    }
};

onMounted(() => {
    window.addEventListener('storage', handleSubmissionStorage);
});

onBeforeUnmount(() => {
    window.removeEventListener('storage', handleSubmissionStorage);
});

const formatStatusLabel = (value: string) =>
    value
        .replace(/_/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (char) => char.toUpperCase());

const statusVariant = (status: string) => {
    switch (status.toLowerCase()) {
        case 'draft':
            return 'outline';
        case 'completed':
        case 'finalized':
            return 'default';
        default:
            return 'secondary';
    }
};

const finalDefinitionProgressLabel = (
    progress?: MetricItem['final_definition_progress'] | null,
) => {
    const state = progress?.state;

    if (state === 'signed_off') {
        return 'Definition signed off';
    }

    if (state === 'ready') {
        return 'Definition ready';
    }

    const missingCount = Number(progress?.missing_count ?? 0);
    return `Definition missing ${Number.isFinite(missingCount) ? missingCount : 0}`;
};

const finalDefinitionProgressVariant = (
    progress?: MetricItem['final_definition_progress'] | null,
): 'default' | 'outline' | 'secondary' => {
    const state = progress?.state;

    if (state === 'signed_off') {
        return 'default';
    }

    if (state === 'ready') {
        return 'outline';
    }

    return 'secondary';
};

const shouldShowDefinitionProgressBadge = (metric: MetricItem): boolean => {
    const progressState = metric.final_definition_progress?.state ?? '';
    const workflowStatus = metric.status.trim().toLowerCase();
    const workflowAlreadyFinalized =
        workflowStatus === 'finalized' || workflowStatus === 'completed';

    if (workflowAlreadyFinalized && progressState === 'signed_off') {
        return false;
    }

    return true;
};

const formatDate = (value?: string | null) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const formatDateTime = (value?: string | null) => {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
};

const finalDefinitionDetailLabel = (metric: MetricItem): string => {
    const definition = metric.final_definition;

    if (!definition) {
        return 'No signed definition yet';
    }

    if (definition.signed_off_at) {
        return `Signed ${formatDate(definition.signed_off_at)}`;
    }

    const status = (definition.status ?? '').trim();
    if (!status) {
        return 'Definition in progress';
    }

    return `Definition status: ${formatStatusLabel(status)}`;
};

const formatSessionDateTime = (
    value?: string | null,
    timezone?: string | null,
) => {
    if (!value) return '—';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    try {
        const runtimeTimezone = resolveIntlTimeZone(timezone ?? 'UTC') ?? 'UTC';

        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
            timeZone: runtimeTimezone,
        }).format(date);
    } catch {
        return date.toLocaleString();
    }
};

const formatSessionDateTimeRange = (
    startsAtValue?: string | null,
    endsAtValue?: string | null,
    timezone?: string | null,
): string => {
    if (!startsAtValue || !endsAtValue) {
        return '—';
    }

    const startsAt = new Date(startsAtValue);
    const endsAt = new Date(endsAtValue);

    if (Number.isNaN(startsAt.getTime()) || Number.isNaN(endsAt.getTime())) {
        return `${formatSessionDateTime(startsAtValue, timezone)} to ${formatSessionDateTime(endsAtValue, timezone)}`;
    }

    const timezoneLabel = toPreferredTimeZoneName(timezone ?? '') || 'UTC';
    const runtimeTimezone = resolveIntlTimeZone(timezoneLabel) ?? 'UTC';
    const dateFormatter = new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeZone: runtimeTimezone,
    });
    const timeFormatter = new Intl.DateTimeFormat(undefined, {
        timeStyle: 'short',
        timeZone: runtimeTimezone,
    });

    const startDate = dateFormatter.format(startsAt);
    const endDate = dateFormatter.format(endsAt);
    const startTime = timeFormatter.format(startsAt);
    const endTime = timeFormatter.format(endsAt);

    if (startDate === endDate) {
        return `${startDate} · ${startTime} to ${endTime} (${timezoneLabel})`;
    }

    return `${startDate} ${startTime} to ${endDate} ${endTime} (${timezoneLabel})`;
};

const formatSessionRange = (session: WorkshopItem) => {
    return formatSessionDateTimeRange(
        session.starts_at,
        session.ends_at,
        session.timezone,
    );
};

const WORKSHOP_SESSION_MIN_DURATION_MINUTES = 15;
const WORKSHOP_SESSION_MAX_DURATION_MINUTES = 120;
const WORKSHOP_SESSION_TIME_STEP_MINUTES = 5;
const WORKSHOP_SESSION_TIME_STEP_SECONDS = 300;
const WORKSHOP_SESSION_DAY_START_TIME = '08:00';
const WORKSHOP_SESSION_LATEST_START_TIME = '17:00';
const WORKSHOP_SESSION_DAY_END_TIME = '19:00';
const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;
const TIME_ONLY_PATTERN = /^\d{2}:\d{2}$/;
const TIMEZONE_PREFERRED_ALIASES: Record<string, string> = {
    'Europe/Kiev': 'Europe/Kyiv',
};
const TIMEZONE_INTL_FALLBACKS: Record<string, string> = {
    'Europe/Kyiv': 'Europe/Kiev',
};

const addMinutes = (date: Date, minutes: number): Date =>
    new Date(date.getTime() + minutes * 60_000);

const toMinutePrecision = (date: Date): Date => {
    const value = new Date(date);
    value.setSeconds(0, 0);
    return value;
};

const clampDateWithinBounds = (
    value: Date,
    minDate: Date,
    maxDate?: Date | null,
): Date => {
    if (value.getTime() < minDate.getTime()) {
        return new Date(minDate);
    }

    if (maxDate && value.getTime() > maxDate.getTime()) {
        return new Date(maxDate);
    }

    return value;
};

const parseDateInput = (value: string): string | null => {
    if (!DATE_ONLY_PATTERN.test(value)) {
        return null;
    }

    return value;
};

const padTwoDigits = (value: number): string => String(value).padStart(2, '0');

const parseTimeInputToMinutes = (value?: string): number | null => {
    if (!value || !TIME_ONLY_PATTERN.test(value)) {
        return null;
    }

    const [hours, minutes] = value.split(':').map((part) => Number(part));

    if ([hours, minutes].some((part) => Number.isNaN(part))) {
        return null;
    }

    return hours * 60 + minutes;
};

const formatMinutesAsTimeInput = (totalMinutes: number): string =>
    `${padTwoDigits(Math.floor(totalMinutes / 60))}:${padTwoDigits(totalMinutes % 60)}`;

const buildWorkshopSessionTimeOptions = (
    minTime?: string,
    maxTime?: string,
): string[] => {
    const minMinutes = parseTimeInputToMinutes(minTime);
    const maxMinutes = parseTimeInputToMinutes(maxTime);

    if (minMinutes === null || maxMinutes === null) {
        return [];
    }

    const startMinutes =
        Math.ceil(minMinutes / WORKSHOP_SESSION_TIME_STEP_MINUTES) *
        WORKSHOP_SESSION_TIME_STEP_MINUTES;
    const endMinutes =
        Math.floor(maxMinutes / WORKSHOP_SESSION_TIME_STEP_MINUTES) *
        WORKSHOP_SESSION_TIME_STEP_MINUTES;

    if (startMinutes > endMinutes) {
        return [];
    }

    const options: string[] = [];
    for (
        let minute = startMinutes;
        minute <= endMinutes;
        minute += WORKSHOP_SESSION_TIME_STEP_MINUTES
    ) {
        options.push(formatMinutesAsTimeInput(minute));
    }

    return options;
};

const withSelectedWorkshopSessionTimeOption = (
    options: string[],
    selected: string,
): string[] => {
    if (!TIME_ONLY_PATTERN.test(selected) || options.includes(selected)) {
        return options;
    }

    const selectedMinutes = parseTimeInputToMinutes(selected);

    if (selectedMinutes === null) {
        return options;
    }

    return [...options, selected].sort((left, right) => {
        const leftMinutes = parseTimeInputToMinutes(left);
        const rightMinutes = parseTimeInputToMinutes(right);

        if (leftMinutes === null || rightMinutes === null) {
            return left.localeCompare(right);
        }

        return leftMinutes - rightMinutes;
    });
};

const toPreferredTimeZoneName = (value: string): string => {
    const normalized = value.trim();

    if (normalized.length === 0) {
        return normalized;
    }

    return TIMEZONE_PREFERRED_ALIASES[normalized] ?? normalized;
};

const canFormatInTimeZone = (timezone: string): boolean => {
    try {
        new Intl.DateTimeFormat(undefined, {
            timeZone: timezone,
        }).format(new Date());
        return true;
    } catch {
        return false;
    }
};

const resolveIntlTimeZone = (
    value: string | null | undefined,
): string | null => {
    const candidate = toPreferredTimeZoneName(value ?? '');

    if (candidate.length === 0) {
        return null;
    }

    if (canFormatInTimeZone(candidate)) {
        return candidate;
    }

    const fallback = TIMEZONE_INTL_FALLBACKS[candidate];
    if (fallback && canFormatInTimeZone(fallback)) {
        return fallback;
    }

    return null;
};

const isValidIanaTimeZone = (value: string): boolean => {
    return resolveIntlTimeZone(value) !== null;
};

const resolvedBrowserTimezone = (() => {
    const browserTimezone = toPreferredTimeZoneName(
        Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
    );

    return isValidIanaTimeZone(browserTimezone) ? browserTimezone : 'UTC';
})();

const resolveValidTimeZone = (
    value: string | null | undefined,
    fallback: string,
): string => {
    const candidate = toPreferredTimeZoneName(value ?? '');
    const fallbackCandidate = toPreferredTimeZoneName(fallback);

    if (isValidIanaTimeZone(candidate)) {
        return candidate;
    }

    return isValidIanaTimeZone(fallbackCandidate) ? fallbackCandidate : 'UTC';
};

const resolveDateTimePartsInTimeZone = (
    date: Date,
    timezone: string,
): {
    year: number;
    month: number;
    day: number;
    hour: number;
    minute: number;
    second: number;
} | null => {
    const runtimeTimezone = resolveIntlTimeZone(timezone);
    if (!runtimeTimezone) {
        return null;
    }

    try {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: runtimeTimezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hourCycle: 'h23',
        }).formatToParts(date);
        const values = parts.reduce<Record<string, string>>((carry, part) => {
            if (part.type !== 'literal') {
                carry[part.type] = part.value;
            }

            return carry;
        }, {});

        const year = Number(values.year);
        const month = Number(values.month);
        const day = Number(values.day);
        const hour = Number(values.hour);
        const minute = Number(values.minute);
        const second = Number(values.second);

        if (
            [year, month, day, hour, minute, second].some((value) =>
                Number.isNaN(value),
            )
        ) {
            return null;
        }

        return {
            year,
            month,
            day,
            hour,
            minute,
            second,
        };
    } catch {
        return null;
    }
};

const toDateInputFromDateInTimeZone = (
    date: Date,
    timezone: string,
): string | null => {
    const parts = resolveDateTimePartsInTimeZone(date, timezone);

    if (!parts) {
        return null;
    }

    return `${parts.year}-${padTwoDigits(parts.month)}-${padTwoDigits(
        parts.day,
    )}`;
};

const toTimeInputFromDateInTimeZone = (
    date: Date,
    timezone: string,
): string | null => {
    const parts = resolveDateTimePartsInTimeZone(date, timezone);

    if (!parts) {
        return null;
    }

    return `${padTwoDigits(parts.hour)}:${padTwoDigits(parts.minute)}`;
};

const getTimeZoneOffsetMilliseconds = (
    date: Date,
    timezone: string,
): number | null => {
    const parts = resolveDateTimePartsInTimeZone(date, timezone);

    if (!parts) {
        return null;
    }

    const utcTimeFromParts = Date.UTC(
        parts.year,
        parts.month - 1,
        parts.day,
        parts.hour,
        parts.minute,
        parts.second,
        0,
    );

    return utcTimeFromParts - date.getTime();
};

const composeDateTimeFromInputs = (
    dateInput: string,
    timeInput: string,
    timezone: string,
): Date | null => {
    if (
        !DATE_ONLY_PATTERN.test(dateInput) ||
        !TIME_ONLY_PATTERN.test(timeInput) ||
        !isValidIanaTimeZone(timezone)
    ) {
        return null;
    }

    const [year, month, day] = dateInput.split('-').map((part) => Number(part));
    const [hours, minutes] = timeInput.split(':').map((part) => Number(part));

    if (
        [year, month, day, hours, minutes].some((value) => Number.isNaN(value))
    ) {
        return null;
    }

    const utcGuess = new Date(
        Date.UTC(year, month - 1, day, hours, minutes, 0, 0),
    );
    const initialOffset = getTimeZoneOffsetMilliseconds(utcGuess, timezone);

    if (initialOffset === null) {
        return null;
    }

    let resolved = new Date(utcGuess.getTime() - initialOffset);
    const refinedOffset = getTimeZoneOffsetMilliseconds(resolved, timezone);

    if (refinedOffset !== null && refinedOffset !== initialOffset) {
        resolved = new Date(utcGuess.getTime() - refinedOffset);
    }

    return toMinutePrecision(resolved);
};

const addDaysToDateInput = (dateInput: string, days: number): string | null => {
    if (!DATE_ONLY_PATTERN.test(dateInput)) {
        return null;
    }

    const [year, month, day] = dateInput.split('-').map((part) => Number(part));
    const base = new Date(Date.UTC(year, month - 1, day, 12, 0, 0, 0));

    if (Number.isNaN(base.getTime())) {
        return null;
    }

    base.setUTCDate(base.getUTCDate() + days);

    return `${base.getUTCFullYear()}-${padTwoDigits(
        base.getUTCMonth() + 1,
    )}-${padTwoDigits(base.getUTCDate())}`;
};

const formatRelativeTime = (value?: string | null) => {
    if (!value) return '—';
    const target = new Date(value);
    if (Number.isNaN(target.getTime())) return '—';
    const diffSeconds = (target.getTime() - Date.now()) / 1000;
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const hours = Math.round(diffSeconds / 3600);
    if (Math.abs(hours) > 48) {
        const days = Math.round(diffSeconds / 86400);
        return rtf.format(days, 'day');
    }
    return rtf.format(hours, 'hour');
};

const repositoryReference = computed(
    () => props.engagement.repository?.reference ?? '',
);
const repositoryIsLink = computed(() => {
    if (!repositoryReference.value) return false;
    try {
        new URL(repositoryReference.value);
        return true;
    } catch {
        return false;
    }
});

const stakeholderPreview = computed(
    () => props.engagement.stakeholders?.slice(0, 4) ?? [],
);
const stakeholderOverflow = computed(() =>
    Math.max(
        0,
        (props.engagement.stakeholders?.length ?? 0) -
            stakeholderPreview.value.length,
    ),
);

const metricStatusOptions = computed(() => {
    const counts = props.metrics?.status_counts ?? {};
    return Object.keys(counts).map((key) => ({
        value: key,
        label: formatStatusLabel(key),
    }));
});

const metricStatusFilter = ref<string>('all');
const metricSearch = ref('');

const filteredMetrics = computed(() => {
    const rows = props.metrics?.items ?? [];
    const status = metricStatusFilter.value;
    const query = metricSearch.value.trim().toLowerCase();
    return rows.filter((row) => {
        const matchesStatus = status === 'all' || row.status === status;
        const searchTargets = [row.name, row.template?.name, row.template?.key]
            .map((value) => value?.toLowerCase() ?? '')
            .filter((value) => value.length > 0);
        const matchesSearch =
            !query || searchTargets.some((target) => target.includes(query));
        return matchesStatus && matchesSearch;
    });
});

const metricsEmpty = computed(() => filteredMetrics.value.length === 0);
const metricFilterLabel = computed(() => {
    if (metricStatusFilter.value === 'all') {
        return 'All statuses';
    }
    const match = metricStatusOptions.value.find(
        (item) => item.value === metricStatusFilter.value,
    );
    return match?.label ?? metricStatusFilter.value;
});

const metricsFiltered = computed(
    () =>
        metricStatusFilter.value !== 'all' ||
        metricSearch.value.trim().length > 0,
);

const metricsPanelId = 'metrics-panel';
const metricDisplayName = (metric: MetricItem): string => {
    const templateName = metric.template?.name?.trim();
    if (templateName) {
        return templateName;
    }

    return metric.name;
};
const metricTemplateKey = (metric: MetricItem): string => {
    const templateKey = metric.template?.key?.trim();
    if (templateKey) {
        return templateKey;
    }

    return 'custom_metric';
};
const metricTemplateForm = (metric: MetricItem): FormItem | null => {
    const metricId = metric.id?.trim();
    if (!metricId) {
        return null;
    }

    return latestFormsByMetricId.value.get(metricId) ?? null;
};
const metricTemplateFormHref = (metric: MetricItem): string => {
    const linkedForm = metricTemplateForm(metric);
    if (!linkedForm) {
        return '';
    }

    return formShowHref(linkedForm.id);
};

type MetricFormProgress = {
    submittedCount: number;
    totalCount: number;
    pendingCount: number;
};

const metricFormProgress = (metric: MetricItem): MetricFormProgress | null => {
    const linkedForm = metricTemplateForm(metric);
    if (!linkedForm) {
        return null;
    }

    const submittedCount = Math.max(0, Number(linkedForm.submitted_count ?? 0));
    const pendingCount = Math.max(0, Number(linkedForm.draft_count ?? 0));
    const assignmentCount = Math.max(
        0,
        Number(linkedForm.assignment_count ?? 0),
    );
    const totalCount = assignmentCount || submittedCount + pendingCount;

    return {
        submittedCount,
        totalCount,
        pendingCount,
    };
};
const metricNextDueAt = (metric: MetricItem): string | null =>
    metricTemplateForm(metric)?.next_due_at ?? null;
const metricWorkshopHref = (metricId: string) => {
    const engagementId = props.engagement?.id;
    if (!engagementId) {
        return '#';
    }
    const route = orgEngagementsWorkshop(
        [props.organization.id, engagementId],
        {
            query: { metric: metricId },
        },
    );

    return typeof route === 'string' ? route : (route?.url ?? '#');
};
const assignmentOptions = computed(
    () => props.metrics?.assignment_options ?? [],
);
const metricsWithAssignments = computed(() => props.metrics?.items ?? []);
const metricDisplayNamesById = computed(
    () =>
        new Map(
            metricsWithAssignments.value.map((metric) => [
                metric.id,
                metricDisplayName(metric),
            ]),
        ),
);
const canManageAssignments = computed(
    () => props.permissions?.can_manage_assignments ?? false,
);
const canManageWorkshopSessions = computed(
    () => props.permissions?.can_update ?? false,
);
const workshopSessionFormOpen = ref(false);
const workshopSessionFormBusy = ref(false);
const workshopSessionTransitionBusy = ref<string | null>(null);
const workshopSessionFormErrors = ref<Record<string, string>>({});
const editingWorkshopSessionId = ref<string | null>(null);

const normalizeWorkshopSessionStatus = (value?: string | null): string =>
    (value ?? '').trim().toLowerCase();

const workshopSessionStatusPriority = (value?: string | null): number => {
    const status = normalizeWorkshopSessionStatus(value);

    switch (status) {
        case 'inprogress':
            return 0;
        case 'scheduled':
            return 1;
        case 'missed':
            return 2;
        case 'completed':
            return 3;
        default:
            return 4;
    }
};

const workshopSessionStartsAtTimestamp = (session: WorkshopItem): number => {
    if (!session.starts_at) {
        return 0;
    }

    const timestamp = Date.parse(session.starts_at);
    return Number.isNaN(timestamp) ? 0 : timestamp;
};

const workshopSessionByMetricId = computed(() => {
    const grouped = new Map<string, WorkshopItem[]>();

    workshopSessions.value
        .filter(
            (session) =>
                normalizeWorkshopSessionStatus(session.status) !== 'canceled',
        )
        .forEach((session) => {
            session.metric_ids.forEach((metricId) => {
                const sessions = grouped.get(metricId) ?? [];
                sessions.push(session);
                grouped.set(metricId, sessions);
            });
        });

    const selectedByMetric = new Map<string, WorkshopItem>();

    grouped.forEach((sessions, metricId) => {
        const selected = [...sessions].sort((left, right) => {
            const leftStatus = normalizeWorkshopSessionStatus(left.status);
            const rightStatus = normalizeWorkshopSessionStatus(right.status);
            const priorityDifference =
                workshopSessionStatusPriority(leftStatus) -
                workshopSessionStatusPriority(rightStatus);

            if (priorityDifference !== 0) {
                return priorityDifference;
            }

            const leftStartsAt = workshopSessionStartsAtTimestamp(left);
            const rightStartsAt = workshopSessionStartsAtTimestamp(right);
            if (leftStatus === 'scheduled') {
                return leftStartsAt - rightStartsAt;
            }

            return rightStartsAt - leftStartsAt;
        })[0];

        if (selected) {
            selectedByMetric.set(metricId, selected);
        }
    });

    return selectedByMetric;
});

const workshopSessionForMetric = (metricId: string): WorkshopItem | null =>
    workshopSessionByMetricId.value.get(metricId) ?? null;

const metricIsTerminalForWorkshop = (metric: MetricItem): boolean => {
    const status = metric.status.trim().toLowerCase();

    return status === 'finalized' || status === 'deprecated';
};

const metricCanScheduleWorkshopSession = (metric: MetricItem): boolean => {
    if (!canManageWorkshopSessions.value) {
        return false;
    }

    if (props.engagement.status.trim().toLowerCase() === 'finalized') {
        return false;
    }

    if (metricIsTerminalForWorkshop(metric)) {
        return false;
    }

    if (!isWorkshopSessionMetricReady(metric.id)) {
        return false;
    }

    const session = workshopSessionForMetric(metric.id);
    if (!session) {
        return true;
    }

    const status = normalizeWorkshopSessionStatus(session.status);
    return status !== 'scheduled' && status !== 'inprogress';
};

const metricWorkshopCellLabel = (metric: MetricItem): string => {
    const session = workshopSessionForMetric(metric.id);
    if (!session) {
        return isWorkshopSessionMetricReady(metric.id)
            ? 'No session scheduled'
            : 'Not ready';
    }

    const status = normalizeWorkshopSessionStatus(session.status);
    const range = formatSessionRange(session);

    if (status === 'inprogress') {
        return `In progress · ${range}`;
    }

    if (status === 'scheduled') {
        return range;
    }

    return `${formatStatusLabel(session.status)} · ${range}`;
};

const metricWorkshopCellHasPrimaryAction = (metric: MetricItem): boolean => {
    if (!canManageWorkshopSessions.value) {
        return false;
    }

    const session = workshopSessionForMetric(metric.id);
    if (!session) {
        return metricCanScheduleWorkshopSession(metric);
    }

    const status = normalizeWorkshopSessionStatus(session.status);
    return status === 'scheduled' || status === 'inprogress';
};

const metricWorkshopCellPrimaryActionLabel = (metric: MetricItem): string => {
    const session = workshopSessionForMetric(metric.id);

    return session ? metricWorkshopCellLabel(metric) : 'Schedule';
};

const metricWorkshopCellHasSecondaryScheduleAction = (
    metric: MetricItem,
): boolean => {
    const session = workshopSessionForMetric(metric.id);
    if (!session) {
        return false;
    }

    const status = normalizeWorkshopSessionStatus(session.status);
    if (status !== 'completed' && status !== 'missed') {
        return false;
    }

    return metricCanScheduleWorkshopSession(metric);
};

const workshopSessionImpactedMetricNames = (session: WorkshopItem): string[] =>
    [...new Set(session.metric_ids ?? [])]
        .map((metricId) =>
            (
                metricDisplayNamesById.value.get(metricId)?.trim() || metricId
            ).trim(),
        )
        .filter((name) => name.length > 0);

const buildCancelWorkshopSessionConfirmationMessage = (
    session: WorkshopItem,
): string => {
    const impactedMetricNames = workshopSessionImpactedMetricNames(session);
    const impactedMetricsLabel =
        impactedMetricNames.length > 0
            ? impactedMetricNames.join(', ')
            : 'Unknown';

    return [
        'Cancel this workshop session for all linked metrics?',
        `Impacted metrics: ${impactedMetricsLabel}`,
        'Participants for these metrics will receive a cancellation email.',
    ].join('\n\n');
};

const workshopSessionMetricOptions = computed(() =>
    metricsWithAssignments.value.map((metric) => ({
        id: metric.id,
        name: metric.name,
        readiness: metric.workshop_schedule_readiness ?? {
            state: 'missing_forms' as const,
            forms_total: 0,
            forms_ready: 0,
        },
    })),
);
const workshopSessionParticipantOptions = computed(() =>
    assignmentOptions.value.map((participant) => ({
        id: participant.id,
        name: participant.name ?? participant.email ?? participant.id,
    })),
);

const formatWorkshopSessionParticipantRole = (
    responsibility: string,
): string => {
    const normalized = responsibility.trim().toLowerCase();

    if (normalized === 'approver') {
        return 'Approver';
    }

    if (normalized === 'contributor') {
        return 'Contributor';
    }

    if (normalized === 'viewer') {
        return 'Viewer';
    }

    if (normalized.length === 0) {
        return 'Viewer';
    }

    return `${normalized.charAt(0).toUpperCase()}${normalized.slice(1)}`;
};

const workshopSessionDefaultApproverParticipantIds = computed(() => {
    const availableParticipantIds = new Set(
        workshopSessionParticipantOptions.value.map(
            (participant) => participant.id,
        ),
    );
    const approverParticipantIds = new Set<string>();

    metricsWithAssignments.value.forEach((metric) => {
        metric.participants.forEach((participant) => {
            const assigneeId = participant.assignee?.id;
            const responsibility = participant.responsibility.toLowerCase();

            if (responsibility !== 'approver' || !assigneeId) {
                return;
            }

            if (!availableParticipantIds.has(assigneeId)) {
                return;
            }

            approverParticipantIds.add(assigneeId);
        });
    });

    return [...approverParticipantIds];
});

const workshopSessionDefaultTimezone = resolveValidTimeZone(
    props.organization.timezone,
    resolvedBrowserTimezone,
);
const workshopSessionLocalTimezone = resolvedBrowserTimezone;

const defaultWorkshopSessionForm = (): WorkshopSessionFormState => {
    const firstReadyMetric = workshopSessionMetricOptions.value.find(
        (metric) => metric.readiness.state === 'ready',
    );

    return {
        session_date: '',
        starts_time: '',
        ends_time: '',
        timezone: workshopSessionDefaultTimezone,
        notes: '',
        metric_ids: firstReadyMetric ? [firstReadyMetric.id] : [],
        participant_ids:
            workshopSessionDefaultApproverParticipantIds.value.length > 0
                ? [...workshopSessionDefaultApproverParticipantIds.value]
                : workshopSessionParticipantOptions.value[0]
                  ? [workshopSessionParticipantOptions.value[0].id]
                  : [],
    };
};

const workshopSessionForm = ref<WorkshopSessionFormState>(
    defaultWorkshopSessionForm(),
);

const workshopSessionNow = ref(toMinutePrecision(new Date()));

const refreshWorkshopSessionNow = () => {
    workshopSessionNow.value = toMinutePrecision(new Date());
};

const workshopSessionTimezone = computed(() =>
    resolveValidTimeZone(
        workshopSessionForm.value.timezone,
        workshopSessionDefaultTimezone,
    ),
);

const workshopSessionTimelineEndAt = computed(() => {
    const timelineEnd = props.timeline?.engagement?.ends_on;

    if (!timelineEnd) {
        return null;
    }

    if (DATE_ONLY_PATTERN.test(timelineEnd)) {
        return composeDateTimeFromInputs(
            timelineEnd,
            '23:59',
            workshopSessionTimezone.value,
        );
    }

    const parsed = new Date(timelineEnd);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed;
});

const resolveWorkshopSessionDayStart = (
    sessionDate: string,
    timezone: string,
): Date | null =>
    composeDateTimeFromInputs(
        sessionDate,
        WORKSHOP_SESSION_DAY_START_TIME,
        timezone,
    );

const resolveWorkshopSessionLatestStartForDate = (
    sessionDate: string,
    timezone: string,
): Date | null =>
    composeDateTimeFromInputs(
        sessionDate,
        WORKSHOP_SESSION_LATEST_START_TIME,
        timezone,
    );

const resolveWorkshopSessionDayEnd = (
    sessionDate: string,
    timezone: string,
): Date | null =>
    composeDateTimeFromInputs(
        sessionDate,
        WORKSHOP_SESSION_DAY_END_TIME,
        timezone,
    );

const resolveWorkshopSessionEndsAtMaxDate = (
    startsAt: Date,
    timezone: string,
): Date => {
    const durationCap = addMinutes(
        startsAt,
        WORKSHOP_SESSION_MAX_DURATION_MINUTES,
    );
    const startsAtDateInput = toDateInputFromDateInTimeZone(startsAt, timezone);
    const sameDayCap = startsAtDateInput
        ? resolveWorkshopSessionDayEnd(startsAtDateInput, timezone)
        : null;

    if (!sameDayCap) {
        return durationCap;
    }

    return durationCap.getTime() <= sameDayCap.getTime()
        ? durationCap
        : sameDayCap;
};

const clampWorkshopSessionStartDate = (
    input: Date,
    timezone: string,
): Date | null => {
    const minDate = workshopSessionNow.value;
    const maxDate = workshopSessionTimelineEndAt.value;

    if (maxDate && maxDate.getTime() < minDate.getTime()) {
        return null;
    }

    const normalized = toMinutePrecision(input);
    const clamped = clampDateWithinBounds(normalized, minDate, maxDate);
    const clampedDateInput = toDateInputFromDateInTimeZone(clamped, timezone);
    const dayStart = clampedDateInput
        ? resolveWorkshopSessionDayStart(clampedDateInput, timezone)
        : null;
    const latestStart = clampedDateInput
        ? resolveWorkshopSessionLatestStartForDate(clampedDateInput, timezone)
        : null;

    if (!dayStart || !latestStart) {
        return null;
    }

    if (clamped.getTime() < dayStart.getTime()) {
        const dayStartIsValid =
            dayStart.getTime() >= minDate.getTime() &&
            (!maxDate || dayStart.getTime() <= maxDate.getTime());

        return dayStartIsValid ? dayStart : null;
    }

    if (clamped.getTime() <= latestStart.getTime()) {
        return clamped;
    }

    const previousCandidate = latestStart;
    const nextDateInput = clampedDateInput
        ? addDaysToDateInput(clampedDateInput, 1)
        : null;
    const nextCandidate = nextDateInput
        ? resolveWorkshopSessionDayStart(nextDateInput, timezone)
        : null;
    const previousIsValid =
        previousCandidate.getTime() >= minDate.getTime() &&
        (!maxDate || previousCandidate.getTime() <= maxDate.getTime());
    const nextIsValid =
        nextCandidate !== null &&
        nextCandidate.getTime() >= minDate.getTime() &&
        (!maxDate || nextCandidate.getTime() <= maxDate.getTime());

    if (previousIsValid && nextIsValid && nextCandidate) {
        const previousDistance = Math.abs(
            clamped.getTime() - previousCandidate.getTime(),
        );
        const nextDistance = Math.abs(
            clamped.getTime() - nextCandidate.getTime(),
        );
        return previousDistance <= nextDistance
            ? previousCandidate
            : nextCandidate;
    }

    if (previousIsValid) {
        return previousCandidate;
    }

    if (nextIsValid && nextCandidate) {
        return nextCandidate;
    }

    return null;
};

const clampWorkshopSessionEndDate = (
    input: Date,
    startsAt: Date,
    timezone: string,
): Date => {
    const minDate = addMinutes(startsAt, WORKSHOP_SESSION_MIN_DURATION_MINUTES);
    const maxDate = resolveWorkshopSessionEndsAtMaxDate(startsAt, timezone);

    return clampDateWithinBounds(toMinutePrecision(input), minDate, maxDate);
};

const resolveWorkshopSessionStartWindowForDate = (
    sessionDate: string,
    timezone: string,
): { min: Date; max: Date } | null => {
    const dayStart = resolveWorkshopSessionDayStart(sessionDate, timezone);
    const latestStartForDay = resolveWorkshopSessionLatestStartForDate(
        sessionDate,
        timezone,
    );

    if (!dayStart || !latestStartForDay) {
        return null;
    }

    const timelineEnd = workshopSessionTimelineEndAt.value;
    const minDate =
        workshopSessionNow.value.getTime() > dayStart.getTime()
            ? workshopSessionNow.value
            : dayStart;

    let maxDate = latestStartForDay;
    if (timelineEnd && timelineEnd.getTime() < maxDate.getTime()) {
        maxDate = timelineEnd;
    }

    if (maxDate.getTime() < minDate.getTime()) {
        return null;
    }

    return { min: minDate, max: maxDate };
};

const workshopSessionDateMin = computed(
    () =>
        toDateInputFromDateInTimeZone(
            workshopSessionNow.value,
            workshopSessionTimezone.value,
        ) ?? undefined,
);
const workshopSessionDateMax = computed(() =>
    workshopSessionTimelineEndAt.value
        ? (toDateInputFromDateInTimeZone(
              workshopSessionTimelineEndAt.value,
              workshopSessionTimezone.value,
          ) ?? undefined)
        : undefined,
);

const workshopSessionSelectedDate = computed(() =>
    parseDateInput(workshopSessionForm.value.session_date),
);
const workshopSessionStartWindow = computed(() =>
    workshopSessionSelectedDate.value
        ? resolveWorkshopSessionStartWindowForDate(
              workshopSessionSelectedDate.value,
              workshopSessionTimezone.value,
          )
        : null,
);
const workshopSessionStartsTimeMin = computed(() =>
    workshopSessionStartWindow.value
        ? (toTimeInputFromDateInTimeZone(
              workshopSessionStartWindow.value.min,
              workshopSessionTimezone.value,
          ) ?? undefined)
        : undefined,
);
const workshopSessionStartsTimeMax = computed(() =>
    workshopSessionStartWindow.value
        ? (toTimeInputFromDateInTimeZone(
              workshopSessionStartWindow.value.max,
              workshopSessionTimezone.value,
          ) ?? undefined)
        : undefined,
);
const workshopSessionStartsTimeOptions = computed(() =>
    withSelectedWorkshopSessionTimeOption(
        buildWorkshopSessionTimeOptions(
            workshopSessionStartsTimeMin.value,
            workshopSessionStartsTimeMax.value,
        ),
        workshopSessionForm.value.starts_time,
    ),
);

const workshopSessionStartsAtDate = computed(() =>
    composeDateTimeFromInputs(
        workshopSessionForm.value.session_date,
        workshopSessionForm.value.starts_time,
        workshopSessionTimezone.value,
    ),
);
const workshopSessionEndsAtDate = computed(() =>
    composeDateTimeFromInputs(
        workshopSessionForm.value.session_date,
        workshopSessionForm.value.ends_time,
        workshopSessionTimezone.value,
    ),
);
const workshopSessionEndsAtMinDate = computed(() =>
    workshopSessionStartsAtDate.value
        ? addMinutes(
              workshopSessionStartsAtDate.value,
              WORKSHOP_SESSION_MIN_DURATION_MINUTES,
          )
        : null,
);
const workshopSessionEndsAtMaxDate = computed(() =>
    workshopSessionStartsAtDate.value
        ? resolveWorkshopSessionEndsAtMaxDate(
              workshopSessionStartsAtDate.value,
              workshopSessionTimezone.value,
          )
        : null,
);

const workshopSessionEndsTimeMin = computed(() =>
    workshopSessionEndsAtMinDate.value
        ? (toTimeInputFromDateInTimeZone(
              workshopSessionEndsAtMinDate.value,
              workshopSessionTimezone.value,
          ) ?? undefined)
        : undefined,
);
const workshopSessionEndsTimeMax = computed(() =>
    workshopSessionEndsAtMaxDate.value
        ? (toTimeInputFromDateInTimeZone(
              workshopSessionEndsAtMaxDate.value,
              workshopSessionTimezone.value,
          ) ?? undefined)
        : undefined,
);
const workshopSessionEndsTimeOptions = computed(() =>
    withSelectedWorkshopSessionTimeOption(
        buildWorkshopSessionTimeOptions(
            workshopSessionEndsTimeMin.value,
            workshopSessionEndsTimeMax.value,
        ),
        workshopSessionForm.value.ends_time,
    ),
);

const workshopSessionSchedulingTimezoneLabel = computed(
    () => workshopSessionTimezone.value,
);
const workshopSessionLocalTimezoneLabel = computed(
    () => workshopSessionLocalTimezone,
);
const workshopSessionTimezonesDiffer = computed(
    () =>
        toPreferredTimeZoneName(
            workshopSessionSchedulingTimezoneLabel.value,
        ) !== toPreferredTimeZoneName(workshopSessionLocalTimezoneLabel.value),
);
const workshopSessionLocalStartPreview = computed(() => {
    if (!workshopSessionStartsAtDate.value) {
        return null;
    }

    return formatSessionDateTime(
        workshopSessionStartsAtDate.value.toISOString(),
        workshopSessionLocalTimezoneLabel.value,
    );
});
const workshopSessionConsultantLocalPreview = computed(() => {
    if (
        !workshopSessionTimezonesDiffer.value ||
        !workshopSessionStartsAtDate.value
    ) {
        return null;
    }

    const startsAtIso = workshopSessionStartsAtDate.value.toISOString();
    const endsAtIso = workshopSessionEndsAtDate.value?.toISOString();

    if (!endsAtIso) {
        return workshopSessionLocalStartPreview.value;
    }

    return formatSessionDateTimeRange(
        startsAtIso,
        endsAtIso,
        workshopSessionLocalTimezoneLabel.value,
    );
});

const workshopSessionMetricsById = computed(
    () =>
        new Map(
            metricsWithAssignments.value.map((metric) => [metric.id, metric]),
        ),
);

const workshopSessionParticipantRoleMap = computed(() => {
    const selectedMetrics = workshopSessionForm.value.metric_ids
        .map((metricId) => workshopSessionMetricsById.value.get(metricId))
        .filter((metric): metric is MetricItem => metric !== undefined);
    const roleMap = new Map<string, string>();

    workshopSessionParticipantOptions.value.forEach((participant) => {
        if (selectedMetrics.length === 0) {
            roleMap.set(participant.id, 'Viewer');
            return;
        }

        const perMetricRoles = selectedMetrics.map((metric) => {
            const roleSet = new Set<string>();

            metric.participants.forEach((metricParticipant) => {
                if (metricParticipant.assignee?.id !== participant.id) {
                    return;
                }

                roleSet.add(
                    formatWorkshopSessionParticipantRole(
                        metricParticipant.responsibility,
                    ),
                );
            });

            if (roleSet.size === 0) {
                roleSet.add('Viewer');
            }

            return {
                metricName: metric.name,
                roleLabel: [...roleSet].join('/'),
            };
        });

        if (perMetricRoles.length === 1) {
            roleMap.set(
                participant.id,
                perMetricRoles[0]?.roleLabel ?? 'Viewer',
            );
            return;
        }

        roleMap.set(
            participant.id,
            perMetricRoles
                .map((item) => `${item.metricName}: ${item.roleLabel}`)
                .join(' | '),
        );
    });

    return roleMap;
});

const workshopSessionParticipantRoleSummary = (participantId: string): string =>
    workshopSessionParticipantRoleMap.value.get(participantId) ?? 'Viewer';

const workshopSessionMetricOptionById = computed(
    () =>
        new Map(
            workshopSessionMetricOptions.value.map((metric) => [
                metric.id,
                metric,
            ]),
        ),
);

const isWorkshopSessionMetricReady = (metricId: string): boolean =>
    workshopSessionMetricOptionById.value.get(metricId)?.readiness.state ===
    'ready';

const isWorkshopSessionMetricSelectable = (metricId: string): boolean =>
    isWorkshopSessionMetricReady(metricId) ||
    workshopSessionForm.value.metric_ids.includes(metricId);

const workshopSessionMetricReadinessLabel = (metricId: string): string => {
    const readiness =
        workshopSessionMetricOptionById.value.get(metricId)?.readiness;

    if (!readiness || readiness.state !== 'ready') {
        return 'Not all forms are submitted yet';
    }

    return 'Ready for scheduling';
};

const workshopSessionsIndexRoute = computed(() =>
    engagements.workshopSessions.index([
        props.organization.id,
        props.engagement.id,
    ]),
);

const applyWorkshopSessionErrors = (
    errors: Record<string, string | string[]>,
) => {
    workshopSessionFormErrors.value = Object.entries(errors).reduce<
        Record<string, string>
    >((carry, [field, value]) => {
        if (Array.isArray(value)) {
            carry[field] = value[0] ?? '';
            return carry;
        }

        carry[field] = value ?? '';
        return carry;
    }, {});
};

const loadWorkshopSessions = async () => {
    try {
        const response = await fetch(workshopSessionsIndexRoute.value.url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(
                `Unable to load workshop sessions (${response.status})`,
            );
        }

        const payload = (await response.json()) as { data?: WorkshopItem[] };
        workshopSessionsState.value = cloneWorkshopItems(payload.data ?? []);
    } catch (error) {
        console.error('Unable to load workshop sessions', error);
    }
};

const openCreateWorkshopSessionForm = (preferredMetricId?: string | null) => {
    refreshWorkshopSessionNow();
    const form = defaultWorkshopSessionForm();

    if (
        preferredMetricId &&
        workshopSessionMetricOptionById.value.has(preferredMetricId)
    ) {
        form.metric_ids = [preferredMetricId];
    }

    editingWorkshopSessionId.value = null;
    workshopSessionFormErrors.value = {};
    workshopSessionForm.value = form;
    workshopSessionFormOpen.value = true;
};

const openEditWorkshopSessionForm = (session: WorkshopItem) => {
    refreshWorkshopSessionNow();
    const startsAtDate = session.starts_at ? new Date(session.starts_at) : null;
    const endsAtDate = session.ends_at ? new Date(session.ends_at) : null;
    const sessionTimezone = resolveValidTimeZone(
        session.timezone,
        workshopSessionDefaultTimezone,
    );
    editingWorkshopSessionId.value = session.id;
    workshopSessionFormErrors.value = {};
    workshopSessionForm.value = {
        session_date:
            startsAtDate && !Number.isNaN(startsAtDate.getTime())
                ? (toDateInputFromDateInTimeZone(
                      startsAtDate,
                      sessionTimezone,
                  ) ?? '')
                : '',
        starts_time:
            startsAtDate && !Number.isNaN(startsAtDate.getTime())
                ? (toTimeInputFromDateInTimeZone(
                      startsAtDate,
                      sessionTimezone,
                  ) ?? '')
                : '',
        ends_time:
            endsAtDate && !Number.isNaN(endsAtDate.getTime())
                ? (toTimeInputFromDateInTimeZone(endsAtDate, sessionTimezone) ??
                  '')
                : '',
        timezone: sessionTimezone,
        notes: session.notes ?? '',
        metric_ids: [...(session.metric_ids ?? [])],
        participant_ids: [...(session.participant_ids ?? [])],
    };
    workshopSessionFormOpen.value = true;
};

const openMetricWorkshopSessionForm = (metric: MetricItem) => {
    if (!canManageWorkshopSessions.value) {
        return;
    }

    const session = workshopSessionForMetric(metric.id);
    if (!session) {
        if (metricCanScheduleWorkshopSession(metric)) {
            openCreateWorkshopSessionForm(metric.id);
        }
        return;
    }

    const status = normalizeWorkshopSessionStatus(session.status);
    if (status === 'scheduled' || status === 'inprogress') {
        openEditWorkshopSessionForm(session);
        return;
    }

    if (metricCanScheduleWorkshopSession(metric)) {
        openCreateWorkshopSessionForm(metric.id);
    }
};

const closeWorkshopSessionForm = () => {
    workshopSessionFormOpen.value = false;
};

const handleWorkshopSessionDialogUpdate = (value: boolean) => {
    if (!value) {
        closeWorkshopSessionForm();
        return;
    }

    workshopSessionFormOpen.value = value;
};

const toggleWorkshopSessionMetric = (metricId: string) => {
    const current = new Set(workshopSessionForm.value.metric_ids);
    if (current.has(metricId)) {
        current.delete(metricId);
    } else {
        if (!isWorkshopSessionMetricReady(metricId)) {
            return;
        }
        current.add(metricId);
    }
    workshopSessionForm.value.metric_ids = [...current];
};

const applyWorkshopSessionStartSelection = (
    sessionDate: string,
    startsTime: string,
    timezone: string,
): Date | null => {
    const startsAt = composeDateTimeFromInputs(
        sessionDate,
        startsTime,
        timezone,
    );
    if (!startsAt) {
        workshopSessionForm.value.ends_time = '';
        return null;
    }

    const clampedStartsAt = clampWorkshopSessionStartDate(startsAt, timezone);
    if (!clampedStartsAt) {
        workshopSessionForm.value.ends_time = '';
        return null;
    }

    const clampedSessionDate = toDateInputFromDateInTimeZone(
        clampedStartsAt,
        timezone,
    );
    const clampedStartsTime = toTimeInputFromDateInTimeZone(
        clampedStartsAt,
        timezone,
    );

    if (!clampedSessionDate || !clampedStartsTime) {
        workshopSessionForm.value.ends_time = '';
        return null;
    }

    workshopSessionForm.value.session_date = clampedSessionDate;
    workshopSessionForm.value.starts_time = clampedStartsTime;

    const endsAtDefault = clampWorkshopSessionEndDate(
        addMinutes(clampedStartsAt, WORKSHOP_SESSION_MIN_DURATION_MINUTES),
        clampedStartsAt,
        timezone,
    );
    workshopSessionForm.value.ends_time =
        toTimeInputFromDateInTimeZone(endsAtDefault, timezone) ?? '';

    return clampedStartsAt;
};

const handleWorkshopSessionDateChange = (value: string) => {
    refreshWorkshopSessionNow();
    workshopSessionForm.value.session_date = value;

    if (!value) {
        workshopSessionForm.value.starts_time = '';
        workshopSessionForm.value.ends_time = '';
        return;
    }

    const fallbackStartsTime =
        workshopSessionForm.value.starts_time ||
        workshopSessionStartsTimeOptions.value[0] ||
        workshopSessionStartsTimeMin.value ||
        WORKSHOP_SESSION_DAY_START_TIME;

    applyWorkshopSessionStartSelection(
        workshopSessionForm.value.session_date,
        fallbackStartsTime,
        workshopSessionTimezone.value,
    );
};

const handleWorkshopSessionStartsTimeChange = (value: string) => {
    refreshWorkshopSessionNow();
    workshopSessionForm.value.starts_time = value;

    if (!value || !workshopSessionForm.value.session_date) {
        workshopSessionForm.value.ends_time = '';
        return;
    }

    applyWorkshopSessionStartSelection(
        workshopSessionForm.value.session_date,
        value,
        workshopSessionTimezone.value,
    );
};

const handleWorkshopSessionEndsTimeChange = (value: string) => {
    workshopSessionForm.value.ends_time = value;

    if (!value) {
        return;
    }

    const startsAt = workshopSessionStartsAtDate.value;
    const endsAt = composeDateTimeFromInputs(
        workshopSessionForm.value.session_date,
        value,
        workshopSessionTimezone.value,
    );

    if (!startsAt || !endsAt) {
        return;
    }

    const clampedEndsAt = clampWorkshopSessionEndDate(
        endsAt,
        startsAt,
        workshopSessionTimezone.value,
    );
    workshopSessionForm.value.ends_time =
        toTimeInputFromDateInTimeZone(
            clampedEndsAt,
            workshopSessionTimezone.value,
        ) ?? value;
};

const toggleWorkshopSessionParticipant = (participantId: string) => {
    const current = new Set(workshopSessionForm.value.participant_ids);
    if (current.has(participantId)) {
        current.delete(participantId);
    } else {
        current.add(participantId);
    }
    workshopSessionForm.value.participant_ids = [...current];
};

const submitWorkshopSessionForm = async () => {
    if (workshopSessionFormBusy.value) return;

    workshopSessionFormErrors.value = {};

    refreshWorkshopSessionNow();

    const requestedTimezone = workshopSessionTimezone.value;
    workshopSessionForm.value.timezone = requestedTimezone;

    const startsAtDate = composeDateTimeFromInputs(
        workshopSessionForm.value.session_date,
        workshopSessionForm.value.starts_time,
        requestedTimezone,
    );
    if (!startsAtDate) {
        workshopSessionFormErrors.value = {
            starts_at: 'Start and end times are required.',
        };
        return;
    }

    const clampedStartsAt = clampWorkshopSessionStartDate(
        startsAtDate,
        requestedTimezone,
    );
    if (!clampedStartsAt) {
        workshopSessionFormErrors.value = {
            starts_at:
                'No valid start time is available within the engagement timeline.',
        };
        return;
    }

    const clampedSessionDate = toDateInputFromDateInTimeZone(
        clampedStartsAt,
        requestedTimezone,
    );
    const clampedStartsTime = toTimeInputFromDateInTimeZone(
        clampedStartsAt,
        requestedTimezone,
    );

    if (!clampedSessionDate || !clampedStartsTime) {
        workshopSessionFormErrors.value = {
            starts_at: 'Start and end times are required.',
        };
        return;
    }

    workshopSessionForm.value.session_date = clampedSessionDate;
    workshopSessionForm.value.starts_time = clampedStartsTime;

    const endsAtDate = composeDateTimeFromInputs(
        clampedSessionDate,
        workshopSessionForm.value.ends_time,
        requestedTimezone,
    );
    if (!endsAtDate) {
        workshopSessionFormErrors.value = {
            starts_at: 'Start and end times are required.',
        };
        return;
    }

    const clampedEndsAt = clampWorkshopSessionEndDate(
        endsAtDate,
        clampedStartsAt,
        requestedTimezone,
    );
    const clampedEndsTime = toTimeInputFromDateInTimeZone(
        clampedEndsAt,
        requestedTimezone,
    );

    if (!clampedEndsTime) {
        workshopSessionFormErrors.value = {
            ends_at: 'End time is invalid.',
        };
        return;
    }

    workshopSessionForm.value.ends_time = clampedEndsTime;

    const payload = {
        session_date: clampedSessionDate,
        starts_time: clampedStartsTime,
        ends_time: clampedEndsTime,
        timezone: requestedTimezone,
        notes: workshopSessionForm.value.notes || null,
        metric_ids: workshopSessionForm.value.metric_ids,
        participant_ids: workshopSessionForm.value.participant_ids,
    };

    const route = editingWorkshopSessionId.value
        ? engagements.workshopSessions.update([
              props.organization.id,
              props.engagement.id,
              editingWorkshopSessionId.value,
          ])
        : engagements.workshopSessions.store([
              props.organization.id,
              props.engagement.id,
          ]);

    workshopSessionFormBusy.value = true;

    try {
        const response = await fetch(route.url, {
            method: editingWorkshopSessionId.value ? 'PATCH' : 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...resolveCsrfHeaders(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        if (response.status === 422) {
            const data = (await response.json()) as {
                errors?: Record<string, string | string[]>;
            };
            applyWorkshopSessionErrors(data.errors ?? {});
            return;
        }

        if (!response.ok) {
            throw new Error(
                `Unable to save workshop session (${response.status})`,
            );
        }

        closeWorkshopSessionForm();
        await loadWorkshopSessions();
        toast(
            'success',
            editingWorkshopSessionId.value
                ? 'Workshop session updated.'
                : 'Workshop session scheduled.',
        );
    } catch (error) {
        console.error('Unable to save workshop session', error);
        toast('error', 'Unable to save workshop session.');
    } finally {
        workshopSessionFormBusy.value = false;
    }
};

const runWorkshopSessionTransition = async (
    session: WorkshopItem,
    transition: 'complete' | 'cancel',
) => {
    if (workshopSessionTransitionBusy.value) return false;

    if (transition === 'cancel') {
        const confirmed = window.confirm(
            buildCancelWorkshopSessionConfirmationMessage(session),
        );

        if (!confirmed) {
            return false;
        }
    }

    const routeMap = {
        complete: engagements.workshopSessions.complete,
        cancel: engagements.workshopSessions.cancel,
    } as const;

    const route = routeMap[transition]([
        props.organization.id,
        props.engagement.id,
        session.id,
    ]);

    workshopSessionTransitionBusy.value = session.id;

    try {
        const response = await fetch(route.url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...resolveCsrfHeaders(),
            },
            credentials: 'same-origin',
            body:
                transition === 'cancel' && session.notes
                    ? JSON.stringify({ notes: session.notes })
                    : '{}',
        });

        if (!response.ok) {
            throw new Error(
                `Unable to ${transition} workshop session (${response.status})`,
            );
        }

        await loadWorkshopSessions();
        toast(
            'success',
            transition === 'complete'
                ? 'Workshop session completed.'
                : 'Workshop session canceled.',
        );

        if (
            (transition === 'cancel' || transition === 'complete') &&
            editingWorkshopSessionId.value === session.id
        ) {
            closeWorkshopSessionForm();
        }

        return true;
    } catch (error) {
        console.error(`Unable to ${transition} workshop session`, error);
        toast('error', `Unable to ${transition} workshop session.`);
        return false;
    } finally {
        workshopSessionTransitionBusy.value = null;
    }
};

const editingWorkshopSession = computed(() => {
    if (!editingWorkshopSessionId.value) {
        return null;
    }

    return (
        workshopSessions.value.find(
            (session) => session.id === editingWorkshopSessionId.value,
        ) ?? null
    );
});

const canCancelEditingWorkshopSession = computed(() => {
    if (!canManageWorkshopSessions.value || !editingWorkshopSession.value) {
        return false;
    }

    const status = normalizeWorkshopSessionStatus(
        editingWorkshopSession.value.status,
    );
    return status === 'scheduled' || status === 'inprogress';
});

const canCompleteEditingWorkshopSession = computed(() => {
    if (!canManageWorkshopSessions.value || !editingWorkshopSession.value) {
        return false;
    }

    return (
        normalizeWorkshopSessionStatus(editingWorkshopSession.value.status) ===
        'inprogress'
    );
});

const cancelEditingWorkshopSession = async () => {
    if (!editingWorkshopSession.value) {
        return;
    }

    await runWorkshopSessionTransition(editingWorkshopSession.value, 'cancel');
};

const completeEditingWorkshopSession = async () => {
    if (!editingWorkshopSession.value) {
        return;
    }

    await runWorkshopSessionTransition(
        editingWorkshopSession.value,
        'complete',
    );
};

const railReadiness = computed<RailReadiness>(() => {
    const payload = props.rail_readiness ?? {
        intake_ready: false,
        forms_ready: false,
        review_ready: false,
        sign_off_ready: false,
        handbook_ready: false,
        blockers: [],
    };

    return {
        intake_ready: Boolean(payload.intake_ready),
        forms_ready: Boolean(payload.forms_ready),
        review_ready: Boolean(payload.review_ready),
        sign_off_ready: Boolean(payload.sign_off_ready),
        handbook_ready: Boolean(payload.handbook_ready),
        blockers: Array.isArray(payload.blockers)
            ? payload.blockers.filter((blocker) => typeof blocker === 'string')
            : [],
    };
});

const timelineItems = computed<TimelineItem[]>(() =>
    buildTimeline(props.timeline),
);
const timelineEmpty = computed(() => timelineItems.value.length === 0);

const reminders = computed(() => props.reminders ?? {});
const parseReminderCount = (value: number | null | undefined): number => {
    if (typeof value !== 'number' || Number.isNaN(value) || value < 0) {
        return 0;
    }

    return Math.floor(value);
};
const pendingDueNowCount = computed(() =>
    parseReminderCount(reminders.value.pending_due_now_count),
);
const failedAttemptsLast24Hours = computed(() =>
    parseReminderCount(reminders.value.failed_attempts_24h),
);
const reminderAttempts = computed<ReminderAttemptItem[]>(() => {
    const attempts = reminders.value.recent_attempts;

    return Array.isArray(attempts) ? attempts : [];
});
const reminderAttemptsEmpty = computed(
    () => reminderAttempts.value.length === 0,
);
const formatReminderAttemptStatus = (status?: string | null): string => {
    const normalized = (status ?? '').toLowerCase();

    if (normalized === 'sent') return 'Sent';
    if (normalized === 'failed') return 'Failed';
    if (normalized === 'skipped') return 'Skipped';

    return status ?? 'Unknown';
};

const canSendReminders = computed(
    () => props.permissions?.can_send_reminders ?? false,
);
const isAwaitingForms = computed(
    () => props.engagement.status === 'AwaitingForms',
);
const canRunReminderPass = computed(
    () => canSendReminders.value && isAwaitingForms.value,
);
const reminderPassExplanation = computed(() => {
    if (isAwaitingForms.value) {
        return null;
    }

    return 'Reminder pass is available only when engagement status is Awaiting Forms.';
});
const canChangeStatus = computed(
    () => props.permissions?.can_change_status ?? false,
);
const isDraftEngagement = computed(() => props.engagement.status === 'Draft');
const kickoffBlockedReason = computed(() =>
    isDraftEngagement.value && !railReadiness.value.intake_ready
        ? 'Select metrics and generate forms to begin.'
        : null,
);
type StatusUpdateTarget =
    | 'AwaitingForms'
    | 'InReview'
    | 'InWorkshop'
    | 'Finalized';
type StatusUpdateError = string | string[] | undefined;
type StatusUpdateErrorBag = Record<string, StatusUpdateError>;
type StatusUpdateOptions = {
    override?: boolean;
    overrideReason?: string;
    onSuccess?: () => void;
    onError?: (errors: StatusUpdateErrorBag) => void;
};
type StageRecoveryRecommendation = {
    target: Exclude<StatusUpdateTarget, 'AwaitingForms'>;
    label: string;
    reason: string;
};
type StageOverrideRecommendation = {
    target: Extract<StatusUpdateTarget, 'InWorkshop' | 'Finalized'>;
    label: string;
    reason: string;
};
const stageRecoveryRecommendation =
    computed<StageRecoveryRecommendation | null>(() => {
        switch (props.engagement.status) {
            case 'AwaitingForms':
                if (!railReadiness.value.forms_ready) {
                    return null;
                }

                return {
                    target: 'InReview',
                    label: 'Recover Stage: In Review',
                    reason: 'All forms are complete and this engagement can move to review.',
                };
            case 'InReview':
                if (!railReadiness.value.review_ready) {
                    return null;
                }

                return {
                    target: 'InWorkshop',
                    label: 'Recover Stage: In Workshop',
                    reason: 'A valid workshop session exists and this engagement can move into workshop.',
                };
            case 'InWorkshop':
                if (!railReadiness.value.sign_off_ready) {
                    return null;
                }

                return {
                    target: 'Finalized',
                    label: 'Recover Stage: Finalized',
                    reason: 'All metrics are finalized or deprecated and this engagement can be closed.',
                };
            default:
                return null;
        }
    });
const canRecoverStage = computed(
    () => canChangeStatus.value && stageRecoveryRecommendation.value !== null,
);
const stageOverrideRecommendation =
    computed<StageOverrideRecommendation | null>(() => {
        switch (props.engagement.status) {
            case 'InReview':
                if (railReadiness.value.review_ready) {
                    return null;
                }

                return {
                    target: 'InWorkshop',
                    label: 'Override to In Workshop',
                    reason: 'Bypass missing workshop-session records when the workshop has already occurred.',
                };
            case 'InWorkshop':
                if (railReadiness.value.sign_off_ready) {
                    return null;
                }

                return {
                    target: 'Finalized',
                    label: 'Override to Finalized',
                    reason: 'Bypass missing finalized metrics only when closure is operationally required.',
                };
            default:
                return null;
        }
    });
const canApplyStageOverride = computed(
    () => canChangeStatus.value && stageOverrideRecommendation.value !== null,
);
const recoverStage = () => {
    const recommendation = stageRecoveryRecommendation.value;
    if (!canRecoverStage.value || !recommendation) {
        return;
    }
    if (updatingStatus.value) {
        return;
    }

    updateStatus(recommendation.target);
};

const reminderRoute = computed(() =>
    orgEngagementRemindersStore([props.organization.id, props.engagement.id]),
);
const statusRoute = computed(() =>
    orgEngagementStatusUpdate([props.organization.id, props.engagement.id]),
);

const sendingReminders = ref(false);
const updatingStatus = ref(false);
const stageOverrideDialogOpen = ref(false);
const stageOverrideReason = ref('');
const stageOverrideReasonError = ref<string | null>(null);
const normalizedStageOverrideReason = computed(() =>
    stageOverrideReason.value.trim(),
);

const firstStatusUpdateError = (value: StatusUpdateError): string | null => {
    if (Array.isArray(value)) {
        return value[0] ?? null;
    }

    if (typeof value !== 'string') {
        return null;
    }

    const normalized = value.trim();

    return normalized === '' ? null : normalized;
};

const resetStageOverrideForm = () => {
    stageOverrideReason.value = '';
    stageOverrideReasonError.value = null;
};

const openStageOverrideDialog = () => {
    if (!canApplyStageOverride.value) {
        return;
    }

    resetStageOverrideForm();
    stageOverrideDialogOpen.value = true;
};

const closeStageOverrideDialog = () => {
    stageOverrideDialogOpen.value = false;
    stageOverrideReasonError.value = null;
};

const handleStageOverrideDialogUpdate = (value: boolean) => {
    if (!value) {
        closeStageOverrideDialog();
        return;
    }

    if (!canApplyStageOverride.value) {
        return;
    }

    stageOverrideDialogOpen.value = value;
};

const submitStageOverride = () => {
    const recommendation = stageOverrideRecommendation.value;
    if (!recommendation || updatingStatus.value) {
        return;
    }

    if (normalizedStageOverrideReason.value.length === 0) {
        stageOverrideReasonError.value = 'Override reason is required.';
        return;
    }

    stageOverrideReasonError.value = null;

    updateStatus(recommendation.target, {
        override: true,
        overrideReason: normalizedStageOverrideReason.value,
        onSuccess: () => {
            closeStageOverrideDialog();
        },
        onError: (errors) => {
            stageOverrideReasonError.value = firstStatusUpdateError(
                errors.override_reason,
            );
        },
    });
};

watch(stageOverrideReason, () => {
    if (stageOverrideReasonError.value !== null) {
        stageOverrideReasonError.value = null;
    }
});

const sendReminders = () => {
    if (!canRunReminderPass.value) return;
    router.post(
        reminderRoute.value.url,
        {},
        {
            preserveScroll: true,
            onStart: () => {
                sendingReminders.value = true;
            },
            onFinish: () => {
                sendingReminders.value = false;
            },
        },
    );
};

const updateStatus = (
    nextStatus: StatusUpdateTarget,
    options: StatusUpdateOptions = {},
) => {
    if (!canChangeStatus.value || updatingStatus.value) return;

    const payload: Record<string, unknown> = { status: nextStatus };
    if (options.override === true) {
        payload.override = true;
        payload.override_reason = options.overrideReason ?? null;
    }

    router.patch(statusRoute.value.url, payload, {
        preserveScroll: true,
        onStart: () => {
            updatingStatus.value = true;
        },
        onFinish: () => {
            updatingStatus.value = false;
        },
        onSuccess: () => {
            toast(
                'success',
                options.override === true
                    ? 'Override applied and audit logged.'
                    : 'Engagement status updated.',
            );
            options.onSuccess?.();
        },
        onError: (errors: StatusUpdateErrorBag) => {
            options.onError?.(errors);

            const message =
                firstStatusUpdateError(errors.override_reason) ??
                firstStatusUpdateError(errors.status);
            toast('error', message ?? 'Unable to update engagement status.');
        },
    });
};

const kickOffEngagement = () => {
    if (!isDraftEngagement.value) return;
    if (!railReadiness.value.intake_ready) return;
    if (!canChangeStatus.value || updatingStatus.value) return;
    updateStatus('AwaitingForms');
};
</script>

<template>
    <Head :title="`${engagement.title} — ${organization.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-8 p-6">
            <div class="space-y-3">
                <div class="space-y-2">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1
                                    class="text-3xl font-semibold tracking-tight"
                                >
                                    {{ engagement.title }}
                                </h1>
                                <Badge
                                    :variant="statusVariant(engagement.status)"
                                    >{{
                                        formatStatusLabel(engagement.status)
                                    }}</Badge
                                >
                            </div>
                            <p class="text-muted-foreground">
                                {{ formatDate(engagement.starts_on) }} →
                                {{ formatDate(engagement.ends_on) }} ·
                                {{ metrics.total }} metrics
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2 sm:justify-end"
                        >
                            <Button
                                v-if="isStaff"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        orgEngagementsHandbookWorkbench([
                                            organization.id,
                                            engagement.id,
                                        ]).url
                                    "
                                >
                                    Handbook Workbench
                                </Link>
                            </Button>
                            <Button
                                v-if="isDraftEngagement"
                                size="sm"
                                :disabled="
                                    !railReadiness.intake_ready ||
                                    !canChangeStatus ||
                                    updatingStatus
                                "
                                @click="kickOffEngagement"
                            >
                                Kick Off Engagement
                            </Button>
                            <DropdownMenu
                                v-if="
                                    canApplyStageOverride &&
                                    stageOverrideRecommendation
                                "
                            >
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="size-8"
                                        aria-label="Engagement actions"
                                    >
                                        <EllipsisVertical class="size-4" />
                                        <span class="sr-only"
                                            >Open engagement actions</span
                                        >
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-72">
                                    <DropdownMenuLabel
                                        >Stage actions</DropdownMenuLabel
                                    >
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        data-test="override-stage-action"
                                        @click="openStageOverrideDialog"
                                    >
                                        <div class="flex flex-col gap-0.5">
                                            <span>{{
                                                stageOverrideRecommendation.label
                                            }}</span>
                                            <span
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    stageOverrideRecommendation.reason
                                                }}
                                            </span>
                                        </div>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <span
                                v-if="updatingStatus"
                                class="text-xs text-muted-foreground"
                            >
                                Updating…
                            </span>
                        </div>
                    </div>
                    <p
                        v-if="kickoffBlockedReason"
                        class="text-xs text-muted-foreground"
                    >
                        {{ kickoffBlockedReason }}
                    </p>
                    <div
                        v-if="canRecoverStage && stageRecoveryRecommendation"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            data-test="recover-stage-action"
                            :disabled="updatingStatus"
                            @click="recoverStage"
                        >
                            {{ stageRecoveryRecommendation.label }}
                        </Button>
                        <span
                            data-test="recover-stage-reason"
                            class="text-xs text-muted-foreground"
                        >
                            {{ stageRecoveryRecommendation.reason }}
                        </span>
                    </div>
                </div>
                <StageReadinessPanel :readiness="railReadiness" />
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card :id="metricsPanelId" class="lg:col-span-3">
                    <CardHeader
                        class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div>
                            <CardTitle>Metrics</CardTitle>
                            <CardDescription
                                >Filter by status and search to focus
                                reviews.</CardDescription
                            >
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline">{{
                                        metricFilterLabel
                                    }}</Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuLabel
                                        >Metric status</DropdownMenuLabel
                                    >
                                    <DropdownMenuSeparator />
                                    <DropdownMenuRadioGroup
                                        v-model="metricStatusFilter"
                                    >
                                        <DropdownMenuRadioItem value="all"
                                            >All statuses</DropdownMenuRadioItem
                                        >
                                        <DropdownMenuRadioItem
                                            v-for="option in metricStatusOptions"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <Input
                                v-model="metricSearch"
                                placeholder="Search metrics…"
                                class="w-48"
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="!metricsEmpty">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Forms</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Workshop</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="metric in filteredMetrics"
                                    :key="metric.id"
                                >
                                    <TableCell class="font-medium">
                                        <Link
                                            :href="
                                                metricWorkshopHref(metric.id)
                                            "
                                            class="hover:underline"
                                        >
                                            {{ metricDisplayName(metric) }}
                                        </Link>
                                    </TableCell>
                                    <TableCell class="text-sm">
                                        <template
                                            v-if="metricTemplateForm(metric)"
                                        >
                                            <div class="flex flex-col gap-1">
                                                <Link
                                                    :href="
                                                        metricTemplateFormHref(
                                                            metric,
                                                        )
                                                    "
                                                    class="font-mono text-xs hover:underline"
                                                >
                                                    {{
                                                        metricTemplateKey(
                                                            metric,
                                                        )
                                                    }}
                                                </Link>
                                                <div>
                                                    <span class="font-medium">{{
                                                        metricFormProgress(
                                                            metric,
                                                        )?.submittedCount
                                                    }}</span>
                                                    <span
                                                        class="text-xs opacity-70"
                                                    >
                                                        /{{
                                                            metricFormProgress(
                                                                metric,
                                                            )?.totalCount
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            metricFormProgress(
                                                                metric,
                                                            )?.pendingCount
                                                        "
                                                        class="ml-2 text-xs text-amber-600"
                                                    >
                                                        {{
                                                            metricFormProgress(
                                                                metric,
                                                            )?.pendingCount
                                                        }}
                                                        pending
                                                    </span>
                                                </div>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Next due
                                                    {{
                                                        formatRelativeTime(
                                                            metricNextDueAt(
                                                                metric,
                                                            ),
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                        </template>
                                        <div
                                            v-else
                                            class="flex flex-col gap-1 text-muted-foreground"
                                        >
                                            <span class="font-mono text-xs">{{
                                                metricTemplateKey(metric)
                                            }}</span>
                                            <span class="text-sm"
                                                >No form linked</span
                                            >
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-sm">
                                        <div class="flex flex-col gap-1.5">
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                            >
                                                <Badge
                                                    :variant="
                                                        statusVariant(
                                                            metric.status,
                                                        )
                                                    "
                                                    class="w-fit"
                                                >
                                                    {{
                                                        formatStatusLabel(
                                                            metric.status,
                                                        )
                                                    }}
                                                </Badge>
                                                <Badge
                                                    v-if="
                                                        shouldShowDefinitionProgressBadge(
                                                            metric,
                                                        )
                                                    "
                                                    :variant="
                                                        finalDefinitionProgressVariant(
                                                            metric.final_definition_progress,
                                                        )
                                                    "
                                                    class="w-fit border-dashed text-[11px]"
                                                >
                                                    {{
                                                        finalDefinitionProgressLabel(
                                                            metric.final_definition_progress,
                                                        )
                                                    }}
                                                </Badge>
                                            </div>
                                            <p
                                                class="text-xs leading-5 text-muted-foreground"
                                            >
                                                {{
                                                    finalDefinitionDetailLabel(
                                                        metric,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="text-sm"
                                        :data-test="`metric-workshop-${metric.id}`"
                                    >
                                        <div class="">
                                            <Button
                                                v-if="
                                                    metricWorkshopCellHasPrimaryAction(
                                                        metric,
                                                    )
                                                "
                                                variant="link"
                                                class="h-auto p-0 text-left whitespace-normal"
                                                :disabled="
                                                    workshopSessionTransitionBusy ===
                                                    workshopSessionForMetric(
                                                        metric.id,
                                                    )?.id
                                                "
                                                @click="
                                                    openMetricWorkshopSessionForm(
                                                        metric,
                                                    )
                                                "
                                            >
                                                {{
                                                    metricWorkshopCellPrimaryActionLabel(
                                                        metric,
                                                    )
                                                }}
                                            </Button>
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                            >
                                                {{
                                                    metricWorkshopCellLabel(
                                                        metric,
                                                    )
                                                }}
                                            </span>
                                            <Button
                                                v-if="
                                                    metricWorkshopCellHasSecondaryScheduleAction(
                                                        metric,
                                                    )
                                                "
                                                variant="link"
                                                class="mt-3 block h-auto w-fit p-0 text-xs"
                                                @click="
                                                    openMetricWorkshopSessionForm(
                                                        metric,
                                                    )
                                                "
                                            >
                                                Schedule new
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <div
                            v-else
                            class="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground"
                        >
                            <span v-if="metricsFiltered"
                                >No metrics match the selected filters.</span
                            >
                            <span v-else
                                >Metrics will appear once forms are
                                submitted.</span
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                :open="stageOverrideDialogOpen"
                @update:open="handleStageOverrideDialogUpdate"
            >
                <DialogContent class="max-w-lg" data-test="override-dialog">
                    <DialogHeader>
                        <DialogTitle>Apply stage override</DialogTitle>
                        <DialogDescription
                            >Use this only for operational exceptions. Override
                            activity is auditable.</DialogDescription
                        >
                    </DialogHeader>
                    <div v-if="stageOverrideRecommendation" class="space-y-3">
                        <p class="text-xs text-muted-foreground">
                            Current:
                            {{
                                formatStatusLabel(engagement.status) ??
                                engagement.status
                            }}
                            · Target:
                            {{
                                formatStatusLabel(
                                    stageOverrideRecommendation.target,
                                ) ?? stageOverrideRecommendation.target
                            }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ stageOverrideRecommendation.reason }}
                        </p>
                        <div class="space-y-2">
                            <Label for="stage-override-reason"
                                >Override reason</Label
                            >
                            <Textarea
                                id="stage-override-reason"
                                v-model="stageOverrideReason"
                                data-test="override-reason-input"
                                rows="4"
                                placeholder="Explain why this override is required."
                            />
                            <p class="text-xs text-muted-foreground">
                                Required. Reason is logged with actor and scope.
                            </p>
                            <p
                                v-if="stageOverrideReasonError"
                                data-test="override-validation-error"
                                class="text-xs text-destructive"
                            >
                                {{ stageOverrideReasonError }}
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            :disabled="updatingStatus"
                            @click="closeStageOverrideDialog"
                            >Cancel</Button
                        >
                        <Button
                            data-test="override-confirm"
                            :disabled="
                                updatingStatus ||
                                normalizedStageOverrideReason.length === 0
                            "
                            @click="submitStageOverride"
                        >
                            Apply override
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                :open="workshopSessionFormOpen"
                @update:open="handleWorkshopSessionDialogUpdate"
            >
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{{
                            editingWorkshopSessionId
                                ? 'Edit workshop session'
                                : 'Schedule workshop session'
                        }}</DialogTitle>
                        <DialogDescription
                            >Set session time, timezone, metric scope, and
                            participant scope.</DialogDescription
                        >
                    </DialogHeader>
                    <div class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-12">
                            <div class="space-y-2 sm:col-span-6">
                                <Label for="workshop-session-date">
                                    Session date ({{
                                        workshopSessionSchedulingTimezoneLabel
                                    }})
                                </Label>
                                <Input
                                    id="workshop-session-date"
                                    v-model="workshopSessionForm.session_date"
                                    type="date"
                                    :min="workshopSessionDateMin"
                                    :max="workshopSessionDateMax"
                                    @change="
                                        handleWorkshopSessionDateChange(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    "
                                />
                                <p
                                    v-if="workshopSessionFormErrors.starts_at"
                                    class="text-xs text-destructive"
                                >
                                    {{ workshopSessionFormErrors.starts_at }}
                                </p>
                            </div>
                            <div class="space-y-2 sm:col-span-3">
                                <Label for="workshop-session-start-time"
                                    >Starts at</Label
                                >
                                <select
                                    id="workshop-session-start-time"
                                    v-model="workshopSessionForm.starts_time"
                                    :step="WORKSHOP_SESSION_TIME_STEP_SECONDS"
                                    :min="workshopSessionStartsTimeMin"
                                    :max="workshopSessionStartsTimeMax"
                                    :disabled="
                                        !workshopSessionForm.session_date ||
                                        workshopSessionStartsTimeOptions.length ===
                                            0
                                    "
                                    class="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
                                    @change="
                                        handleWorkshopSessionStartsTimeChange(
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="">Select time</option>
                                    <option
                                        v-for="timeOption in workshopSessionStartsTimeOptions"
                                        :key="`workshop-session-start-${timeOption}`"
                                        :value="timeOption"
                                    >
                                        {{ timeOption }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2 sm:col-span-3">
                                <Label for="workshop-session-end-time"
                                    >Ends at</Label
                                >
                                <select
                                    id="workshop-session-end-time"
                                    v-model="workshopSessionForm.ends_time"
                                    :step="WORKSHOP_SESSION_TIME_STEP_SECONDS"
                                    :min="workshopSessionEndsTimeMin"
                                    :max="workshopSessionEndsTimeMax"
                                    :disabled="
                                        !workshopSessionForm.session_date ||
                                        !workshopSessionForm.starts_time ||
                                        workshopSessionEndsTimeOptions.length ===
                                            0
                                    "
                                    class="flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
                                    @change="
                                        handleWorkshopSessionEndsTimeChange(
                                            ($event.target as HTMLSelectElement)
                                                .value,
                                        )
                                    "
                                >
                                    <option value="">Select time</option>
                                    <option
                                        v-for="timeOption in workshopSessionEndsTimeOptions"
                                        :key="`workshop-session-end-${timeOption}`"
                                        :value="timeOption"
                                    >
                                        {{ timeOption }}
                                    </option>
                                </select>
                                <p
                                    v-if="workshopSessionFormErrors.ends_at"
                                    class="text-xs text-destructive"
                                >
                                    {{ workshopSessionFormErrors.ends_at }}
                                </p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <p
                                v-if="workshopSessionConsultantLocalPreview"
                                class="text-xs text-muted-foreground"
                            >
                                Consultant local time ({{
                                    workshopSessionLocalTimezoneLabel
                                }}):
                                {{ workshopSessionConsultantLocalPreview }}
                            </p>
                            <p
                                v-if="workshopSessionFormErrors.timezone"
                                class="text-xs text-destructive"
                            >
                                {{ workshopSessionFormErrors.timezone }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Metric scope</Label>
                            <div class="grid gap-2">
                                <label
                                    v-for="metric in workshopSessionMetricOptions"
                                    :key="metric.id"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <input
                                        :checked="
                                            workshopSessionForm.metric_ids.includes(
                                                metric.id,
                                            )
                                        "
                                        type="checkbox"
                                        :disabled="
                                            !isWorkshopSessionMetricSelectable(
                                                metric.id,
                                            )
                                        "
                                        @change="
                                            toggleWorkshopSessionMetric(
                                                metric.id,
                                            )
                                        "
                                    />
                                    <div class="flex flex-col">
                                        <span class="flex items-center gap-2">
                                            <span>{{ metric.name }}</span>
                                            <Badge
                                                v-if="
                                                    metric.readiness.state ===
                                                    'ready'
                                                "
                                                variant="outline"
                                                >Ready</Badge
                                            >
                                        </span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                workshopSessionMetricReadinessLabel(
                                                    metric.id,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                            <p
                                v-if="workshopSessionFormErrors.metric_ids"
                                class="text-xs text-destructive"
                            >
                                {{ workshopSessionFormErrors.metric_ids }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label>Participant scope</Label>
                            <div class="grid gap-2">
                                <label
                                    v-for="participant in workshopSessionParticipantOptions"
                                    :key="participant.id"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <input
                                        :checked="
                                            workshopSessionForm.participant_ids.includes(
                                                participant.id,
                                            )
                                        "
                                        type="checkbox"
                                        @change="
                                            toggleWorkshopSessionParticipant(
                                                participant.id,
                                            )
                                        "
                                    />
                                    <div class="flex flex-col">
                                        <span>{{ participant.name }}</span>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                workshopSessionParticipantRoleSummary(
                                                    participant.id,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </label>
                            </div>
                            <p
                                v-if="workshopSessionFormErrors.participant_ids"
                                class="text-xs text-destructive"
                            >
                                {{ workshopSessionFormErrors.participant_ids }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="workshop-session-notes">Notes</Label>
                            <Input
                                id="workshop-session-notes"
                                v-model="workshopSessionForm.notes"
                                type="text"
                                placeholder="Optional session notes"
                            />
                            <p
                                v-if="workshopSessionFormErrors.session"
                                class="text-xs text-destructive"
                            >
                                {{ workshopSessionFormErrors.session }}
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            :disabled="workshopSessionFormBusy"
                            @click="closeWorkshopSessionForm"
                            >Close</Button
                        >
                        <Button
                            v-if="canCompleteEditingWorkshopSession"
                            :disabled="
                                workshopSessionFormBusy ||
                                workshopSessionTransitionBusy ===
                                    editingWorkshopSessionId
                            "
                            @click="completeEditingWorkshopSession"
                        >
                            Complete session
                        </Button>
                        <Button
                            v-if="canCancelEditingWorkshopSession"
                            variant="destructive"
                            :disabled="
                                workshopSessionFormBusy ||
                                workshopSessionTransitionBusy ===
                                    editingWorkshopSessionId
                            "
                            @click="cancelEditingWorkshopSession"
                        >
                            Cancel session
                        </Button>
                        <Button
                            :disabled="workshopSessionFormBusy"
                            @click="submitWorkshopSessionForm"
                        >
                            {{
                                workshopSessionFormBusy
                                    ? 'Saving...'
                                    : editingWorkshopSessionId
                                      ? 'Save session'
                                      : 'Create session'
                            }}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <MetricParticipantAssignments
                :organization-id="organization.id"
                :engagement-id="engagement.id"
                :metrics="metricsWithAssignments"
                :options="assignmentOptions"
                :can-manage="canManageAssignments"
            />

            <Card>
                <CardHeader>
                    <CardTitle>Workflow timeline</CardTitle>
                    <CardDescription
                        >Key milestones across forms, workshops, and
                        approvals.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <ul v-if="!timelineEmpty" class="space-y-4 text-sm">
                        <li
                            v-for="item in timelineItems"
                            :key="item.id"
                            class="space-y-1"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{
                                    item.title
                                }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    formatDateTime(item.date)
                                }}</span>
                            </div>
                            <p
                                v-if="item.description"
                                class="text-muted-foreground"
                            >
                                {{ item.description }}
                            </p>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">
                        Activity will appear here as forms are submitted,
                        workshops are held, and definitions are finalized.
                    </p>
                </CardContent>
            </Card>

            <Card
                v-if="isStaff"
                id="workshop-prep-panel"
                data-test="workshop-prep-panel"
            >
                <CardHeader>
                    <CardTitle>Workshop Prep</CardTitle>
                    <CardDescription
                        >Prepare alignments and review guidance by
                        metric.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Table v-if="metrics.items.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Metric</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="metric in metrics.items"
                                :key="`prep-${metric.id}`"
                            >
                                <TableCell class="font-medium">
                                    {{ metricDisplayName(metric) }}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        :variant="statusVariant(metric.status)"
                                        class="w-fit"
                                    >
                                        {{ formatStatusLabel(metric.status) }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                            class="border-primary/20 text-primary hover:bg-primary/5"
                                        >
                                            <Link
                                                :href="
                                                    metricWorkshopPrepHref(
                                                        metric.id,
                                                    )
                                                "
                                            >
                                                <Compass
                                                    class="mr-1.5 size-3.5"
                                                />
                                                Prep
                                            </Link>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="text-sm text-muted-foreground">
                        No metrics available for workshop preparation.
                    </p>
                </CardContent>
            </Card>

            <Card id="reminders-panel" class="lg:col-span-3">
                <CardHeader
                    class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
                >
                    <div>
                        <CardTitle>Reminders</CardTitle>
                        <CardDescription
                            >Track reminder cadence and delivery health for form
                            assignments.</CardDescription
                        >
                        <p class="mt-2 text-xs text-muted-foreground">
                            Auto reminder pass runs every 5 minutes. Last
                            schedule change:
                            {{
                                formatDateTime(
                                    reminders.last_schedule_change_at,
                                )
                            }}
                            · Last delivery attempt:
                            {{ formatDateTime(reminders.last_attempt_at) }}
                            · Due now: {{ pendingDueNowCount }} · Failed (24h):
                            {{ failedAttemptsLast24Hours }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="canSendReminders"
                            variant="outline"
                            size="sm"
                            :title="
                                isAwaitingForms
                                    ? 'Recompute schedules + dispatch reminders due now.'
                                    : 'Reminder pass is available only when engagement status is Awaiting Forms.'
                            "
                            :disabled="sendingReminders || !isAwaitingForms"
                            @click="sendReminders"
                        >
                            <Clock class="mr-2 h-4 w-4" />
                            {{
                                sendingReminders
                                    ? 'Queuing reminders…'
                                    : 'Run reminder pass now'
                            }}
                        </Button>
                        <p
                            v-if="canSendReminders && reminderPassExplanation"
                            class="text-xs text-muted-foreground md:max-w-xs"
                        >
                            {{ reminderPassExplanation }}
                        </p>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2 rounded-md border p-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-medium">
                                Recent reminder attempts
                            </h4>
                            <span class="text-xs text-muted-foreground">
                                Last 10
                            </span>
                        </div>
                        <Table v-if="!reminderAttemptsEmpty" class="w-full">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Attempted</TableHead>
                                    <TableHead>Metric</TableHead>
                                    <TableHead>Recipient</TableHead>
                                    <TableHead>Window</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Details</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="attempt in reminderAttempts"
                                    :key="attempt.id"
                                >
                                    <TableCell>{{
                                        formatDateTime(attempt.attempted_at)
                                    }}</TableCell>
                                    <TableCell>{{
                                        attempt.metric_name ?? '—'
                                    }}</TableCell>
                                    <TableCell>{{
                                        attempt.recipient_email ?? '—'
                                    }}</TableCell>
                                    <TableCell>{{
                                        attempt.window ?? '—'
                                    }}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline">{{
                                            formatReminderAttemptStatus(
                                                attempt.status,
                                            )
                                        }}</Badge>
                                    </TableCell>
                                    <TableCell>{{
                                        attempt.reason ?? attempt.channel ?? '—'
                                    }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-sm text-muted-foreground">
                            No reminder attempts for this engagement yet.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card id="repository-panel" data-test="repository-panel">
                    <CardHeader>
                        <CardTitle>Repository</CardTitle>
                        <CardDescription
                            >Source reference and notes for this
                            engagement.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-2 text-sm">
                        <p>
                            <span class="font-medium">Driver:</span>
                            {{ engagement.repository?.driver ?? '—' }}
                        </p>
                        <p class="break-all">
                            <span class="font-medium">Reference:</span>
                            <template v-if="repositoryReference">
                                <a
                                    v-if="repositoryIsLink"
                                    :href="repositoryReference"
                                    class="text-primary underline-offset-2 hover:underline"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    {{ repositoryReference }}
                                </a>
                                <span v-else>{{ repositoryReference }}</span>
                            </template>
                            <span v-else>—</span>
                        </p>
                        <p class="whitespace-pre-wrap text-muted-foreground">
                            {{
                                engagement.repository?.notes ??
                                'No repository notes provided.'
                            }}
                        </p>
                    </CardContent>
                </Card>

                <Card id="stakeholders-panel" data-test="stakeholders-panel">
                    <CardHeader>
                        <CardTitle>Stakeholders</CardTitle>
                        <CardDescription
                            >Contacts currently linked to this
                            engagement.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <ul
                            v-if="stakeholderPreview.length"
                            class="space-y-1 text-sm"
                        >
                            <li
                                v-for="stakeholder in stakeholderPreview"
                                :key="`${stakeholder.email}-${stakeholder.name}`"
                            >
                                <span class="font-medium">{{
                                    stakeholder.name ?? 'Unnamed stakeholder'
                                }}</span>
                                <div
                                    class="flex flex-col text-xs text-muted-foreground sm:flex-row sm:items-center sm:gap-1"
                                >
                                    <span v-if="stakeholder.role">{{
                                        stakeholder.role
                                    }}</span>
                                    <span
                                        v-if="
                                            stakeholder.role &&
                                            stakeholder.department
                                        "
                                        >·</span
                                    >
                                    <span v-if="stakeholder.department">{{
                                        stakeholder.department
                                    }}</span>
                                    <span
                                        v-if="
                                            !stakeholder.role &&
                                            !stakeholder.department
                                        "
                                        >—</span
                                    >
                                </div>
                                <div class="text-muted-foreground">
                                    {{ stakeholder.email ?? '—' }}
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-muted-foreground">
                            No stakeholders added yet.
                        </p>
                        <p
                            v-if="stakeholderOverflow > 0"
                            class="text-xs text-muted-foreground"
                        >
                            +{{ stakeholderOverflow }} more collaborators
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
