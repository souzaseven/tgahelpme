import globals from 'globals';

const commonRules = {
    'no-unused-vars': ['warn', { argsIgnorePattern: '^_' }],
    'no-console': ['warn', { allow: ['warn', 'error'] }],
    'prefer-const': 'error',
    eqeqeq: ['error', 'smart'],
};

export default [
    {
        files: ['assets/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: { ...globals.browser },
        },
        rules: commonRules,
    },
    {
        files: ['sw.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'script',
            globals: { ...globals.serviceworker, caches: 'readonly' },
        },
        rules: commonRules,
    },
    {
        files: ['worker/**/*.js'],
        languageOptions: { ecmaVersion: 2023, sourceType: 'module', globals: {} },
        rules: commonRules,
    },
    {
        files: ['test/**/*.js'],
        languageOptions: {
            ecmaVersion: 2023,
            sourceType: 'module',
            globals: { ...globals.node },
        },
        rules: { ...commonRules, 'no-console': 'off' },
    },
];
