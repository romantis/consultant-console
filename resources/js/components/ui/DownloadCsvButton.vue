<script setup lang="ts">
type RowValue = unknown;

const props = withDefaults(
    defineProps<{
        headers: string[];
        rows: RowValue[][];
        filename: string;
        title?: string;
    }>(),
    {
        title: 'Export to CSV',
    },
);

const downloadCsv = (): void => {
    const escapeCsvCell = (value: unknown): string => {
        const stringified = String(value ?? '');
        if (/[",\n\r]/.test(stringified)) {
            return `"${stringified.replace(/"/g, '""')}"`;
        }
        return stringified;
    };

    const csvContent = [
        props.headers.map(escapeCsvCell).join(','),
        ...props.rows.map((row) => row.map(escapeCsvCell).join(',')),
    ].join('\r\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    const filenameWithExtension = props.filename.endsWith('.csv')
        ? props.filename
        : `${props.filename}.csv`;

    link.href = url;
    link.setAttribute('download', filenameWithExtension);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
};
</script>

<template>
    <button
        data-test="download-matrix-csv"
        @click="downloadCsv"
        :title="title"
        class="inline-flex size-8 items-center justify-center rounded-md border border-input bg-background/80 p-0 text-sm font-medium shadow-sm backdrop-blur transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            class="size-4"
        >
            <path
                fill-rule="evenodd"
                d="M10 3a.75.75 0 0 1 .75.75v6.59l1.95-2.1a.75.75 0 1 1 1.1 1.02l-3.25 3.5a.75.75 0 0 1-1.1 0L6.2 9.26a.75.75 0 1 1 1.1-1.02l1.95 2.1V3.75A.75.75 0 0 1 10 3ZM3 15a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1Z"
                clip-rule="evenodd"
            />
        </svg>
    </button>
</template>
