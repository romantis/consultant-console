import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import DownloadCsvButton from '@/components/ui/DownloadCsvButton.vue';

describe('DownloadCsvButton.vue component', () => {
    let mockCreateObjectURL: any;
    let mockRevokeObjectURL: any;
    let mockClick: any;
    let originalCreateElement: any;

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

    it('renders the button', () => {
        const wrapper = mount(DownloadCsvButton, {
            props: {
                headers: ['Header 1', 'Header 2'],
                rows: [['Val 1', 'Val 2']],
                filename: 'test-export',
                title: 'Export to CSV',
            },
        });

        expect(wrapper.find('[data-test="download-matrix-csv"]').exists()).toBe(true);
        expect(wrapper.find('button').attributes('title')).toBe('Export to CSV');
        wrapper.unmount();
    });

    it('generates a CSV download trigger with correctly formatted content on click', async () => {
        const wrapper = mount(DownloadCsvButton, {
            props: {
                headers: ['Header 1', 'Header 2'],
                rows: [
                    ['Val 1, with comma', 'Val 2'],
                    ['Val "with quotes"', 'Val 4\nwith newline'],
                ],
                filename: 'test-export',
            },
        });

        const button = wrapper.get('[data-test="download-matrix-csv"]');
        await button.trigger('click');

        expect(mockCreateObjectURL).toHaveBeenCalled();
        const [blob] = mockCreateObjectURL.mock.calls[0];
        expect(blob).toBeInstanceOf(Blob);

        // Read the blob content using FileReader
        const reader = new FileReader();
        const textPromise = new Promise<string>((resolve) => {
            reader.onload = () => resolve(reader.result as string);
        });
        reader.readAsText(blob);
        const text = await textPromise;

        // Verify CSV content format with quoting and escaping
        expect(text).toContain('Header 1,Header 2');
        expect(text).toContain('"Val 1, with comma",Val 2');
        expect(text).toContain('"Val ""with quotes""",');
        expect(text).toContain('"Val 4\nwith newline"');

        expect(mockClick).toHaveBeenCalled();
        expect(mockRevokeObjectURL).toHaveBeenCalled();
        wrapper.unmount();
    });

    it('uses the filename prop and appends .csv extension', async () => {
        const wrapper = mount(DownloadCsvButton, {
            props: {
                headers: ['Col'],
                rows: [['Data']],
                filename: 'custom-file-name',
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

        const button = wrapper.get('[data-test="download-matrix-csv"]');
        await button.trigger('click');

        expect(capturedFilename).toBe('custom-file-name.csv');

        // Clean up
        HTMLAnchorElement.prototype.setAttribute = originalSetAttribute;
        wrapper.unmount();
    });
});
