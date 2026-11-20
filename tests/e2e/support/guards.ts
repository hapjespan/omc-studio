import AxeBuilder from '@axe-core/playwright';
import { expect, test as base, type Page } from '@playwright/test';

const wcagTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

export interface Guards {
    assertNoOutsideRequest(): void;
    assertNoCspViolation(): Promise<void>;
}

export async function installGuards(page: Page, origin: string): Promise<Guards> {
    const outside: string[] = [];
    const violations: string[] = [];
    page.on('request', (request) => {
        const url = request.url();
        if (!url.startsWith('data:') && !url.startsWith('blob:') && new URL(url).origin !== origin) outside.push(url);
    });
    await page.exposeFunction('__cspViolation', (entry: string) => violations.push(entry));
    page.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => (window as unknown as { __cspViolation: (entry: string) => void }).__cspViolation(`${event.violatedDirective} ${event.blockedURI}`));
    });

    return {
        assertNoOutsideRequest() {
            expect(outside, 'requests outside the own origin').toEqual([]);
        },
        async assertNoCspViolation() {
            await page.evaluate(() => new Promise((resolve) => setTimeout(resolve, 100))).catch(() => undefined);
            expect(violations, 'securitypolicyviolation events').toEqual([]);
        },
    };
}

export async function expectNoAxeViolations(page: Page, exclude?: string, include?: string): Promise<void> {
    const scoped = include ? new AxeBuilder({ page }).include(include) : new AxeBuilder({ page });
    const builder = scoped.withTags(wcagTags);
    const { violations } = await (exclude ? builder.exclude(exclude) : builder).analyze();
    expect(violations.map((violation) => `${violation.id}: ${violation.help}`), 'axe violations').toEqual([]);
}

export const test = base.extend<{ guards: Guards }>({
    guards: [
        async ({ page, baseURL }, use) => {
            const guards = await installGuards(page, new URL(baseURL as string).origin);
            await use(guards);
            guards.assertNoOutsideRequest();
            await guards.assertNoCspViolation();
        },
        { auto: true },
    ],
});

export { expect };
