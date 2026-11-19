import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '../../tests/e2e/proof',
    reporter: 'list',
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:4173', viewport: { width: 1700, height: 1200 } },
    webServer: { command: 'node serve.mjs', url: 'http://127.0.0.1:4173/', reuseExistingServer: true },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'], viewport: { width: 1700, height: 1200 } } }],
});
