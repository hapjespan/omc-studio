import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: 'tests/e2e',
    testIgnore: 'proof/**',
    fullyParallel: true,
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    use: {
        baseURL: process.env.APP_URL ?? 'http://localhost:8080',
    },
    webServer: {
        command: 'npx vite build -c tests/e2e/support/harness/vite.config.ts && npx vite preview -c tests/e2e/support/harness/vite.config.ts',
        url: `http://127.0.0.1:${process.env.E2E_HARNESS_PORT ?? 4187}/`,
        reuseExistingServer: false,
        timeout: 120000,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
});
