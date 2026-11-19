import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

const mockPageProps = {
    sidebarOpen: true,
    name: 'forms.show',
    quote: {
        message: '',
        author: '',
    },
    auth: {
        user: {
            id: 'user-1',
            name: 'Test User',
            email: 'test@example.com',
            is_internal_staff: false,
        },
        abilities: [],
    },
    abilities: [],
    flash: {},
    flags: {
        allow_public_registration: false,
    },
    clientNavigation: {
        organization: {
            id: 'org-1',
            name: 'Acme Corp',
        },
        membership: {
            id: 'member-1',
            role: 'Member',
            canViewHandbook: true,
        },
        engagements: [
            {
                id: 'eng-1',
                title: 'Q1 Review',
                status: 'AwaitingForms',
                href: '/client/engagements/eng-1',
            },
        ],
        links: {
            forms: null,
            metrics: null,
            handbook: '/',
            support: '/client/support',
        },
        activeEngagementId: 'eng-1',
    },
};
const mockPage = {
    props: mockPageProps,
    url: '/client/forms',
};

beforeEach(() => {
    if (mockPage.props.auth.user) {
        mockPage.props.auth.user.is_internal_staff = false;
        mockPage.props.auth.user.can_operate_delivery = false;
    }
});

function basicWrapper(tag: string) {
    return defineComponent({
        name: `${tag}-stub`,
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () => h(tag, attrs, slots.default ? slots.default() : []);
        },
    });
}

vi.mock('@/components/ui/button', () => ({
    Button: defineComponent({
        name: 'ButtonStub',
        inheritAttrs: false,
        setup(_, { slots, attrs }) {
            return () =>
                h(
                    'button',
                    {
                        type: attrs.type ?? 'button',
                        ...attrs,
                    },
                    slots.default ? slots.default() : [],
                );
        },
    }),
    buttonVariants: () => '',
}));

vi.mock('@/components/ui/card', () => ({
    Card: basicWrapper('div'),
    CardHeader: basicWrapper('header'),
    CardTitle: basicWrapper('h2'),
    CardContent: basicWrapper('section'),
    CardFooter: basicWrapper('footer'),
}));

vi.mock('@/components/ui/input', () => ({
    Input: defineComponent({
        name: 'InputStub',
        inheritAttrs: false,
        props: {
            modelValue: {
                type: [String, Number, Object, Array],
                default: '',
            },
            disabled: {
                type: Boolean,
                default: false,
            },
        },
        emits: ['update:modelValue', 'input', 'blur'],
        setup(props, { emit, attrs }) {
            return () =>
                h('input', {
                    ...attrs,
                    disabled: props.disabled,
                    value: props.modelValue ?? '',
                    onInput: (event: Event) => {
                        const target = event.target as HTMLInputElement;
                        emit('update:modelValue', target.value);
                        emit('input', event);
                    },
                    onBlur: (event: FocusEvent) => emit('blur', event),
                });
        },
    }),
}));

vi.mock('@/components/ui/textarea', () => ({
    Textarea: defineComponent({
        name: 'TextareaStub',
        inheritAttrs: false,
        props: {
            modelValue: {
                type: [String, Number, Object, Array],
                default: '',
            },
            disabled: {
                type: Boolean,
                default: false,
            },
        },
        emits: ['update:modelValue', 'input', 'blur'],
        setup(props, { emit, attrs }) {
            return () =>
                h('textarea', {
                    ...attrs,
                    disabled: props.disabled,
                    value: props.modelValue ?? '',
                    onInput: (event: Event) => {
                        const target = event.target as HTMLTextAreaElement;
                        emit('update:modelValue', target.value);
                        emit('input', event);
                    },
                    onBlur: (event: FocusEvent) => emit('blur', event),
                });
        },
    }),
}));

vi.mock('@/components/ui/label', () => ({
    Label: basicWrapper('label'),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        name: 'AppLayoutStub',
        inheritAttrs: false,
        props: {
            title: {
                type: String,
                default: '',
            },
            description: {
                type: String,
                default: '',
            },
            breadcrumbs: {
                type: Array,
                default: () => [],
            },
        },
        setup(props, { slots }) {
            return () =>
                h(
                    'div',
                    {
                        'data-breadcrumbs': JSON.stringify(props.breadcrumbs),
                        'data-title': props.title,
                        'data-description': props.description,
                    },
                    [
                        props.title ? h('h1', {}, props.title) : null,
                        props.description
                            ? h('p', {}, props.description)
                            : null,
                        slots.default ? slots.default() : [],
                    ],
                );
        },
    }),
}));

vi.mock('@inertiajs/vue3', async () => {
    const actual = await vi.importActual<typeof import('@inertiajs/vue3')>(
        '@inertiajs/vue3',
    );

    return {
        ...actual,
        Head: defineComponent({
            name: 'HeadStub',
            props: {
                title: {
                    type: String,
                    required: false,
                },
            },
            setup(_, { slots }) {
                return () => (slots.default ? slots.default() : null);
            },
        }),
        usePage: () => mockPage,
        Form: defineComponent({
            name: 'InertiaFormStub',
            props: {
                action: {
                    type: [String, Object],
                    required: false,
                    default: '',
                },
                method: {
                    type: String,
                    default: 'post',
                },
                transform: {
                    type: Function,
                    default: (payload: unknown) => payload,
                },
            },
            setup(_, { slots }) {
                const slotProps = {
                    errors: {},
                    hasErrors: false,
                    processing: false,
                    recentlySuccessful: false,
                    data: {},
                    setData: vi.fn(),
                    submit: vi.fn(),
                    transform: vi.fn(),
                    reset: vi.fn(),
                };

                return () =>
                    slots.default
                        ? slots.default(slotProps as never)
                        : h('form', {}, []);
            },
        }),
    };
});

import FormShow from '@/pages/Forms/Show.vue';

const flushPromises = async () => {
    await Promise.resolve();
    await Promise.resolve();
};

const buildProps = () => ({
    form: {
        id: 'form-1',
        title: 'Net Retention',
        schema_json: {
            fields: [
                {
                    name: 'retention_rate',
                    type: 'number',
                    label: 'Retention Rate',
                    required: true,
                },
                {
                    name: 'notes',
                    type: 'textarea',
                    label: 'Notes',
                },
            ],
        },
        status: 'Open',
    },
    submission: {
        id: 'submission-1',
        answers_json: {
            retention_rate: null,
            notes: '',
        },
        submitted_at: null,
    },
    submissionToken: 'token-123',
    assignment: null,
    deadline: null,
});

describe('Form header description', () => {
    it('shows the completion prompt for contributors', () => {
        const wrapper = mount(FormShow, {
            props: buildProps(),
        });

        expect(wrapper.text()).toContain(
            'Provide input for the active engagement.',
        );

        wrapper.unmount();
    });

    it('shows the review prompt for staff read-only views', () => {
        mockPage.props.auth.user!.is_internal_staff = true;

        const wrapper = mount(FormShow, {
            props: {
                ...buildProps(),
                readOnly: true,
            },
        });

        expect(wrapper.text()).toContain(
            'Reviewing contributor responses for this engagement.',
        );
        expect(wrapper.text()).not.toContain(
            'Provide input for the active engagement.',
        );

        wrapper.unmount();
    });
});

describe('Form breadcrumbs', () => {
    it('builds staff breadcrumbs from the back link', () => {
        mockPage.props.auth.user!.is_internal_staff = true;

        const wrapper = mount(FormShow, {
            props: {
                ...buildProps(),
                backLink: {
                    label: 'Back to engagement',
                    href: '/organizations/org-1/engagements/eng-1',
                },
            },
        });

        const serialized = wrapper
            .get('[data-breadcrumbs]')
            .attributes('data-breadcrumbs');
        const breadcrumbs = JSON.parse(serialized);

        expect(breadcrumbs).toEqual([
            {
                title: 'Engagement',
                href: '/organizations/org-1/engagements/eng-1',
            },
            {
                title: 'Form Net Retention',
                href: '/forms/form-1',
            },
        ]);
        expect(wrapper.text()).not.toContain('Back to engagement');

        wrapper.unmount();
    });

    it('omits breadcrumbs for non-staff views', () => {
        const wrapper = mount(FormShow, {
            props: {
                ...buildProps(),
                backLink: {
                    label: 'Back to metric',
                    href: '/client/organizations/org-1/engagements/eng-1/metrics/org-metric-1',
                },
            },
        });

        const serialized = wrapper
            .get('[data-breadcrumbs]')
            .attributes('data-breadcrumbs');
        const breadcrumbs = JSON.parse(serialized);

        expect(breadcrumbs).toEqual([]);

        wrapper.unmount();
    });
});

describe('Form autosave behaviour', () => {
    const originalFetch = global.fetch;
    let setItemSpy: ReturnType<typeof vi.spyOn>;
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        vi.useFakeTimers();
        fetchMock = vi.fn();
        global.fetch = fetchMock as typeof global.fetch;
        setItemSpy = vi.spyOn(Storage.prototype, 'setItem');
    });

    afterEach(() => {
        vi.clearAllTimers();
        vi.useRealTimers();
        global.fetch = originalFetch;
        vi.restoreAllMocks();
    });

    it('debounces autosave updates and records successful saves', async () => {
        fetchMock.mockResolvedValue({
            ok: true,
            status: 200,
            json: () => Promise.resolve({ saved: true }),
        } as Response);

        const wrapper = mount(FormShow, {
            props: buildProps(),
        });

        const input = wrapper.get('input#retention_rate');

        await input.setValue('89');
        await vi.advanceTimersByTimeAsync(300);
        await input.setValue('92');
        await input.trigger('blur');

        expect(fetchMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, options] = fetchMock.mock.calls[0] as [
            string,
            RequestInit,
        ];
        expect(url).toBe('/forms/form-1/autosave');
        expect(options?.method).toBe('PUT');
        expect(options?.headers).toMatchObject({
            'Content-Type': 'application/json',
        });

        const body = JSON.parse((options?.body as string) ?? '{}');
        expect(body.answers_json.retention_rate).toBe(92);
        expect(body.submission_token).toBe('token-123');

        expect(setItemSpy).toHaveBeenCalledWith(
            'mh:lastSubmissionUpdate',
            expect.stringContaining('"event":"autosave"'),
        );

        expect(wrapper.text()).toContain('Draft saved.');

        await vi.advanceTimersByTimeAsync(2500);
        await flushPromises();

        expect(wrapper.text()).not.toContain('Draft saved.');

        wrapper.unmount();
    });

    it('surfaces autosave errors when the request fails', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            status: 500,
            json: () => Promise.resolve({}),
        } as Response);

        const consoleSpy = vi
            .spyOn(console, 'error')
            .mockImplementation(() => undefined);

        const wrapper = mount(FormShow, {
            props: buildProps(),
        });

        const input = wrapper.get('input#retention_rate');
        await input.setValue('85');
        await input.trigger('blur');

        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('Autosave failed (500)');
        expect(setItemSpy).not.toHaveBeenCalled();

        consoleSpy.mockRestore();
        wrapper.unmount();
    });

    it('queues a retry when the server throttles autosave requests', async () => {
        const throttleResponse = {
            ok: false,
            status: 409,
            headers: {
                get: (name: string) => {
                    if (name.toLowerCase() === 'retry-after') return '2';
                    if (name.toLowerCase() === 'content-type') {
                        return 'application/json';
                    }
                    return null;
                },
            },
            json: () =>
                Promise.resolve({
                    message: 'Autosave temporarily throttled.',
                    retry_after: 2,
                }),
        } as Response;

        const successResponse = {
            ok: true,
            status: 200,
            headers: {
                get: (name: string) => {
                    if (name.toLowerCase() === 'content-type') {
                        return 'application/json';
                    }
                    return null;
                },
            },
            json: () => Promise.resolve({ saved: true }),
        } as Response;

        fetchMock.mockResolvedValueOnce(throttleResponse);
        fetchMock.mockResolvedValueOnce(successResponse);

        const wrapper = mount(FormShow, {
            props: buildProps(),
        });

        const input = wrapper.get('input#retention_rate');
        await input.setValue('75');
        await input.trigger('blur');

        await vi.advanceTimersByTimeAsync(600);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain(
            'Autosave temporarily throttled. Retrying shortly.',
        );
        expect(setItemSpy).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(2000);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(setItemSpy).toHaveBeenCalledWith(
            'mh:lastSubmissionUpdate',
            expect.stringContaining('"event":"autosave"'),
        );
        expect(wrapper.text()).toContain('Draft saved.');

        wrapper.unmount();
    });
});
