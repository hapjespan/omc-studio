import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '../../tests/e2e/proof',
    reporter: 'list',
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:4173', viewport: { width: 1700, height: 1200 }, deviceScaleFactor: 1 },
    webServer: { command: 'node serve.mjs', url: 'http://127.0.0.1:4173/', reuseExistingServer: true },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1700, height: 1200 }, deviceScaleFactor: 1 } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'], headless: false, viewport: { width: 1700, height: 1200 }, deviceScaleFactor: 1, launchOptions: { firefoxUserPrefs: { 'webgl.force-enabled': true, 'webgl.forbid-software': false } } } },
        { name: 'webkit', use: { ...devices['Desktop Safari'], viewport: { width: 1700, height: 1200 }, deviceScaleFactor: 1 } },
    ],
});
