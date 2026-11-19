import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MatrixAnswersView from '@/components/metrics/MatrixAnswersView.vue';

describe('MatrixAnswersView.vue component', () => {
    let mockCreateObjectURL: any;
    let mockRevokeObjectURL: any;
    let mockClick: any;
    let originalCreateElement: any;

    const dummyMetric = {
        matrix: [
            {
                field: 'lead_definition',
                label: 'What do you count as a lead?',
                values: [
                    {
                        definition_id: 'def-1',
                        raw: 'marketing_qualified',
                        user: { id: 'usr-1', name: 'Lia Price' },
                    },
                    {
                        definition_id: 'def-2',
                        raw: 'any_contact',
                        user: { id: 'usr-2', name: 'Lila Martin' },
                    },
                ],
            },
        ],
        field_roles: [
            {
                key: 'lead_definition',
                label: 'What do you count as a lead?',
                role: 'definition' as const,
            },
        ],
    };

    beforeEach(() => {
        mockCreateObjectURL = vi.fn(() => 'blob:url');
        mockRevokeObjectURL = vi.fn();
        mockClick = vi.fn();

        global.URL.createObjectURL = mockCreateObjectURL;
        global.URL.revokeObjectURL = mockRevokeObjectURL;

        originalCreateElement = document.createElement;
        document.createElement = function (tagName: string) {
            const el = originalCreateElement.call(document, tagName);
            if (tagName === 'a') {
                el.click = mockClick;
            }
            return el;
        };
    });

    afterEach(() => {
        document.createElement = originalCreateElement;
    });

    it('renders the questions and contributor names inside the table', () => {
        const wrapper = mount(MatrixAnswersView, {
            props: {
                metric: dummyMetric,
                title: 'Test Matrix',
            },
        });

        expect(wrapper.text()).toContain('What do you count as a lead?');
        expect(wrapper.text()).toContain('Lia Price');
        expect(wrapper.text()).toContain('Lila Martin');
        expect(wrapper.find('[data-test="download-matrix-csv"]').exists()).toBe(true);
        wrapper.unmount();
    });

    it('generates a CSV download trigger with correctly formatted content on export click', async () => {
        const wrapper = mount(MatrixAnswersView, {
            props: {
                metric: dummyMetric,
                title: 'Test Matrix',
            },
        });

        const downloadButton = wrapper.get('[data-test="download-matrix-csv"]');
        await downloadButton.trigger('click');

        expect(mockCreateObjectURL).toHaveBeenCalled();
        const [blob] = mockCreateObjectURL.mock.calls[0];
        expect(blob).toBeInstanceOf(Blob);

        // Read the blob content using FileReader (since Blob.text() is not available in all JSDOM environments)
        const reader = new FileReader();
        const textPromise = new Promise<string>((resolve) => {
            reader.onload = () => resolve(reader.result as string);
        });
        reader.readAsText(blob);
        const text = await textPromise;

        expect(text).toContain('Question,Lia Price,Lila Martin');
        expect(text).toContain('What do you count as a lead?,Marketing Qualified,Any Contact');

        expect(mockClick).toHaveBeenCalled();
        expect(mockRevokeObjectURL).toHaveBeenCalled();
        wrapper.unmount();
    });

    it('does not render the download button if there are no rows or contributors', () => {
        const wrapper = mount(MatrixAnswersView, {
            props: {
                metric: { matrix: [] },
            },
        });

        expect(wrapper.find('[data-test="download-matrix-csv"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('uses the custom downloadFilename prop when provided', async () => {
        const wrapper = mount(MatrixAnswersView, {
            props: {
                metric: dummyMetric,
                title: 'Test Matrix',
                downloadFilename: 'custom_matrix_name'
            },
        });

        let capturedFilename = '';
        const originalSetAttribute = HTMLAnchorElement.prototype.setAttribute;
        HTMLAnchorElement.prototype.setAttribute = function (name: string, value: string) {
            if (name === 'download') {
                capturedFilename = value;
            }
            originalSetAttribute.call(this, name, value);
        };

        const downloadButton = wrapper.get('[data-test="download-matrix-csv"]');
        await downloadButton.trigger('click');

        expect(capturedFilename).toBe('custom_matrix_name.csv');

        HTMLAnchorElement.prototype.setAttribute = originalSetAttribute;
        wrapper.unmount();
    });
});
