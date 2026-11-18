import AxeBuilder from '@axe-core/playwright';
import { expect, test as base, type Page } from '@playwright/test';

const wcagTags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

export interface Guards {
    assertNoOutsideRequest(): void;
    assertNoCspViolation(): Promise<void>;
}

export function installGuards(page: Page, origin: string): Guards {
    const outside: string[] = [];
    page.on('request', (request) => {
        const url = request.url();
        if (!url.startsWith('data:') && !url.startsWith('blob:') && new URL(url).origin !== origin) outside.push(url);
    });
    page.addInitScript(() => {
        const store = ((window as unknown as { __csp: string[] }).__csp = []);
        document.addEventListener('securitypolicyviolation', (event) => store.push(`${event.violatedDirective} ${event.blockedURI}`));
    });

    return {
        assertNoOutsideRequest() {
            expect(outside, 'requests outside the own origin').toEqual([]);
        },
        async assertNoCspViolation() {
            const violations = await page.evaluate(() => (window as unknown as { __csp?: string[] }).__csp ?? []);
            expect(violations, 'securitypolicyviolation events').toEqual([]);
        },
    };
}

export async function expectNoAxeViolations(page: Page): Promise<void> {
    const { violations } = await new AxeBuilder({ page }).withTags(wcagTags).analyze();
    expect(violations.map((violation) => `${violation.id}: ${violation.help}`), 'axe violations').toEqual([]);
}

export const test = base.extend<{ guards: Guards }>({
    guards: [
        async ({ page, baseURL }, use) => {
            const guards = installGuards(page, new URL(baseURL as string).origin);
            await use(guards);
            guards.assertNoOutsideRequest();
            await guards.assertNoCspViolation();
        },
        { auto: true },
    ],
});

export { expect };
