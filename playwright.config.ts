import { defineConfig, devices } from '@playwright/test';
import { ensureDisplay } from './tests/e2e/support/display';
import { HARNESS_URL } from './tests/e2e/support/ports';

ensureDisplay();

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
        url: `${HARNESS_URL}/`,
        reuseExistingServer: false,
        timeout: 120000,
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'], headless: !process.env.DISPLAY, launchOptions: { firefoxUserPrefs: { 'webgl.force-enabled': true, 'webgl.forbid-software': false } } } },
        { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    ],
});
