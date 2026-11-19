import vue from '@vitejs/plugin-vue';
import path from 'path';
import { defineConfig } from 'vitest/config';

if (process.env.NODE_ENV !== 'test') {
    process.env.NODE_ENV = 'test';
}

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: [
            {
                find: '@/routes/forms',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/forms.ts',
                ),
            },
            {
                find: '@/routes/org/settings',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/settings.ts',
                ),
            },
            {
                find: '@/routes/org/forms',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/forms.ts',
                ),
            },
            {
                find: '@/routes/api/metrics/discrepancies',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/api/metrics/discrepancies.ts',
                ),
            },
            {
                find: '@/routes/api/metrics/workshops',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/api/metrics/workshops.ts',
                ),
            },
            {
                find: '@/routes/org/engagements',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/engagements',
                ),
            },
            {
                find: '@/routes/org/metrics/final-definition',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/metrics/final-definition.ts',
                ),
            },
            {
                find: '@/routes/org/metrics',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/metrics/index.ts',
                ),
            },
            {
                find: '@/routes/org/metrics/',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/metrics/index.ts',
                ),
            },
            {
                find: '@/routes/metric-templates',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/metric-templates.ts',
                ),
            },
            {
                find: '@/routes/profile',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/profile.ts',
                ),
            },
            {
                find: '@/routes/owner',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/owner.ts',
                ),
            },
            {
                find: '@/routes/client',
                replacement: path.resolve(
                    __dirname,
                    'resources/js/routes/client',
                ),
            },
            {
                find: '@/routes/client/',
                replacement: path.resolve(
                    __dirname,
                    'resources/js/routes/client',
                ),
            },
            {
                find: '@/routes/org/org-metrics/signals',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/org-metrics/signals.ts',
                ),
            },
            {
                find: '@/routes/org/org-metrics',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/org-metrics/index.ts',
                ),
            },
            {
                find: '@/routes/org/org-metrics/',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/org-metrics/index.ts',
                ),
            },
            {
                find: '@/routes/org/handbook-exports/index',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/handbook-exports/index.ts',
                ),
            },
            {
                find: '@/routes/org/handbook-exports',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/handbook-exports/index.ts',
                ),
            },
            {
                find: '@/routes/org',
                replacement: path.resolve(
                    __dirname,
                    'tests/vitest/routes/org/index.ts',
                ),
            },
            {
                find: '@/routes',
                replacement: path.resolve(__dirname, 'tests/vitest/routes.ts'),
            },
            {
                find: '@',
                replacement: path.resolve(__dirname, 'resources/js'),
            },
        ],
    },
    test: {
        environment: 'jsdom',
        globals: true,
        isolate: true,
        pool: 'threads',
        setupFiles: ['tests/vitest/setup/a11y.ts'],
    },
});
