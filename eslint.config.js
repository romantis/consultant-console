import prettier from 'eslint-config-prettier/flat';
import vue from 'eslint-plugin-vue';

import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';

export default defineConfigWithVueTs(
    vue.configs['flat/essential'],
    vueTsConfigs.recommended,
    {
        ignores: [
            'vendor',
            'node_modules',
            'public',
            'public/build',
            'public/hot',
            'dist',
            'build',
            'coverage',
            'demo',
            'storage',
            'bootstrap/ssr',
            'bootstrap/cache',
            '.vite',
            '.vite/**',
            'tailwind.config.js',
            'resources/js/components/ui/*',
            'resources/js/actions',
            'resources/js/routes',
            'resources/js/wayfinder',
            '*.min.js',
            '*.log',
        ],
    },
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
        },
    },
    prettier,
);
