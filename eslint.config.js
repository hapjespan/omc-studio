import pluginVue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

export default [
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**'] },
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/essential'],
    {
        files: ['**/*.vue'],
        languageOptions: { parserOptions: { parser: tseslint.parser } },
    },
    {
        files: ['resources/js/**/*.{ts,vue}'],
        rules: {
            'vue/no-v-html': 'error',
            'no-restricted-globals': [
                'error',
                { name: 'localStorage', message: 'Browser storage is forbidden (V3).' },
                { name: 'sessionStorage', message: 'Browser storage is forbidden (V3).' },
            ],
            'no-restricted-properties': [
                'error',
                { object: 'window', property: 'localStorage', message: 'Browser storage is forbidden (V3).' },
                { object: 'window', property: 'sessionStorage', message: 'Browser storage is forbidden (V3).' },
            ],
        },
    },
    {
        files: ['resources/js/pages/**/*.vue'],
        rules: { 'vue/multi-word-component-names': 'off' },
    },
    {
        files: ['resources/js/security/history.ts'],
        rules: { 'no-restricted-globals': 'off', 'no-restricted-properties': 'off' },
    },
];
