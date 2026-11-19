import { mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';

import MetricParticipantAssignments from '@/pages/Engagements/Show/MetricParticipantAssignments.vue';

const routerPatch = vi.hoisted(() => vi.fn());

function basicWrapper(tag: string) {
    return defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });
}

vi.mock('@inertiajs/vue3', () => ({
    router: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: routerPatch,
        delete: vi.fn(),
        visit: vi.fn(),
        reload: vi.fn(),
        remember: vi.fn(),
    },
}));

vi.mock(
    '@/routes/org/engagements',
    () => ({
        default: {
            metrics: {
                participants: {
                    update: () => ({ url: '/stub/metric-participants-bulk' }),
                },
            },
        },
    }),
    { virtual: true },
);

vi.mock('@/components/ui/badge', () => ({
    Badge: basicWrapper('span'),
}));

vi.mock('@/components/ui/button', () => ({
    Button: basicWrapper('button'),
}));

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('section'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
    CardDescription: basicWrapper('p'),
    CardContent: basicWrapper('div'),
}));

vi.mock('@/components/ui/table', () => ({
    Table: basicWrapper('table'),
    TableHeader: basicWrapper('thead'),
    TableBody: basicWrapper('tbody'),
    TableRow: basicWrapper('tr'),
    TableHead: basicWrapper('th'),
    TableCell: basicWrapper('td'),
}));

vi.mock('lucide-vue-next', () => ({
    Loader2: basicWrapper('span'),
    Plus: basicWrapper('span'),
    RotateCcw: basicWrapper('span'),
    Trash2: basicWrapper('span'),
}));

const createProps = (signedOff = false) => ({
    organizationId: 'org-1',
    engagementId: 'eng-1',
    metrics: [
        {
            id: 'metric-1',
            name: 'Revenue',
            status: 'Draft',
            final_definition: signedOff
                ? { signed_off_at: '2026-01-01T10:00:00Z' }
                : null,
            participants: [
                {
                    id: 'metric-participant-contrib-1',
                    responsibility: 'contributor',
                    role_tags: [],
                    assignee: {
                        id: 'participant-contrib-1',
                        name: 'Contributor One',
                        email: 'contrib1@example.test',
                        type: 'membership',
                        is_active: true,
                        is_pending: false,
                        is_staff: false,
                    },
                },
                {
                    id: 'metric-participant-contrib-2',
                    responsibility: 'contributor',
                    role_tags: [],
                    assignee: {
                        id: 'participant-contrib-2',
                        name: 'Contributor Two',
                        email: 'contrib2@example.test',
                        type: 'membership',
                        is_active: true,
                        is_pending: false,
                        is_staff: false,
                    },
                },
                {
                    id: 'metric-participant-approver',
                    responsibility: 'approver',
                    role_tags: [],
                    assignee: {
                        id: 'participant-approver',
                        name: 'Approver User',
                        email: 'approver@example.test',
                        type: 'membership',
                        is_active: true,
                        is_pending: false,
                        is_staff: false,
                    },
                },
                {
                    id: 'metric-participant-observer',
                    responsibility: 'observer',
                    role_tags: [],
                    assignee: {
                        id: 'participant-observer',
                        name: 'Observer User',
                        email: 'observer@example.test',
                        type: 'membership',
                        is_active: true,
                        is_pending: false,
                        is_staff: false,
                    },
                },
            ],
        },
    ],
    options: [
        {
            id: 'participant-contrib-1',
            name: 'Contributor One',
            email: 'contrib1@example.test',
            type: 'membership',
            is_active: true,
            is_pending: false,
            is_staff: false,
        },
        {
            id: 'participant-contrib-2',
            name: 'Contributor Two',
            email: 'contrib2@example.test',
            type: 'membership',
            is_active: true,
            is_pending: false,
            is_staff: false,
        },
        {
            id: 'participant-approver',
            name: 'Approver User',
            email: 'approver@example.test',
            type: 'membership',
            is_active: true,
            is_pending: false,
            is_staff: false,
        },
        {
            id: 'participant-observer',
            name: 'Observer User',
            email: 'observer@example.test',
            type: 'membership',
            is_active: true,
            is_pending: false,
            is_staff: false,
        },
        {
            id: 'participant-extra',
            name: 'Extra User',
            email: 'extra@example.test',
            affiliation: 'Finance',
            type: 'membership',
            is_active: true,
            is_pending: false,
            is_staff: false,
        },
        {
            id: 'membership:staff-1',
            name: 'Staff Reviewer',
            email: 'staff@example.test',
            affiliation: 'Consulting',
            type: 'membership',
            is_active: true,
            is_pending: false,
            is_staff: true,
        },
    ],
    canManage: true,
});

describe('MetricParticipantAssignments participant-first editor', () => {
    beforeEach(() => {
        routerPatch.mockReset();
    });

    it('keeps one approver in draft and submits the full participant payload on save', async () => {
        const wrapper = mount(MetricParticipantAssignments, {
            props: createProps(false),
        });

        const rows = wrapper.findAll('tbody tr');
        const observerRow = rows.find((row) =>
            row.text().includes('Observer User'),
        );
        expect(observerRow).toBeTruthy();

        await observerRow!.find('select').setValue('approver');

        const approverRow = wrapper
            .findAll('tbody tr')
            .find((row) => row.text().includes('Approver User'));
        expect(approverRow).toBeTruthy();
        expect(
            (approverRow!.find('select').element as HTMLSelectElement).value,
        ).toBe('observer');

        const saveButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Save'));
        expect(saveButton).toBeTruthy();

        await saveButton!.trigger('click');

        expect(routerPatch).toHaveBeenCalledTimes(1);
        expect(routerPatch.mock.calls[0][0]).toBe('/stub/metric-participants-bulk');
        const payload = routerPatch.mock.calls[0][1] as {
            participants: Array<{
                engagement_participant_id: string;
                responsibility: string;
            }>;
        };

        expect(payload.participants).toHaveLength(4);
        expect(payload.participants).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    engagement_participant_id: 'participant-contrib-1',
                    responsibility: 'contributor',
                }),
                expect.objectContaining({
                    engagement_participant_id: 'participant-contrib-2',
                    responsibility: 'contributor',
                }),
                expect.objectContaining({
                    engagement_participant_id: 'participant-approver',
                    responsibility: 'observer',
                }),
                expect.objectContaining({
                    engagement_participant_id: 'participant-observer',
                    responsibility: 'approver',
                }),
            ]),
        );

        expect(wrapper.text()).toContain(
            'Internal-first warning: approver is not staff',
        );

        wrapper.unmount();
    });

    it('highlights newly added participants as unsaved before save', async () => {
        const wrapper = mount(MetricParticipantAssignments, {
            props: createProps(false),
        });

        const addButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Add participant'));
        expect(addButton).toBeTruthy();

        await addButton!.trigger('click');

        const optionButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Extra User'));
        expect(optionButton).toBeTruthy();
        expect(optionButton!.text()).toContain('extra@example.test');
        expect(optionButton!.text()).toContain('Finance');

        await optionButton!.trigger('click');

        const extraRow = wrapper
            .findAll('tbody tr')
            .find((row) => row.text().includes('Extra User'));

        expect(extraRow).toBeTruthy();
        expect(extraRow!.classes()).toContain('bg-emerald-50/40');
        expect(extraRow!.find('td').classes()).toContain('border-l-4');
        expect(extraRow!.text()).toContain('extra@example.test');
        expect(extraRow!.text()).toContain('Finance');
        expect(wrapper.text()).toContain('New');

        wrapper.unmount();
    });

    it('closes add-participant dropdown on outside click', async () => {
        const wrapper = mount(MetricParticipantAssignments, {
            props: createProps(false),
        });

        const addButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Add participant'));
        expect(addButton).toBeTruthy();

        await addButton!.trigger('click');

        const optionButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Extra User'));
        expect(optionButton).toBeTruthy();

        document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        await nextTick();

        const optionButtonAfterOutsideClick = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Extra User'));
        expect(optionButtonAfterOutsideClick).toBeUndefined();

        wrapper.unmount();
    });

    it('restricts staff participants to observer or approver roles', async () => {
        const wrapper = mount(MetricParticipantAssignments, {
            props: createProps(false),
        });

        const addButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Add participant'));
        expect(addButton).toBeTruthy();

        await addButton!.trigger('click');

        const staffOptionButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Staff Reviewer'));
        expect(staffOptionButton).toBeTruthy();

        await staffOptionButton!.trigger('click');

        const staffRow = wrapper
            .findAll('tbody tr')
            .find((row) => row.text().includes('Staff Reviewer'));
        expect(staffRow).toBeTruthy();

        const staffRoleSelect = staffRow!.find('select');
        const contributorOption = staffRoleSelect.find('option[value="contributor"]');
        expect((contributorOption.element as HTMLOptionElement).disabled).toBe(true);

        await staffRoleSelect.setValue('approver');
        expect((staffRoleSelect.element as HTMLSelectElement).value).toBe('approver');

        wrapper.unmount();
    });

    it('locks participant editing once metric is signed off', async () => {
        const wrapper = mount(MetricParticipantAssignments, {
            props: createProps(true),
        });

        expect(wrapper.text()).toContain('Signed off on');

        const saveButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Save'));
        expect(saveButton).toBeTruthy();
        expect(saveButton!.attributes('disabled')).toBeDefined();

        await saveButton!.trigger('click');
        expect(routerPatch).not.toHaveBeenCalled();

        wrapper.unmount();
    });
});
