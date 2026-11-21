import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { createCanvas, signInAs } from '../support/shell';
import { HARNESS_URL } from '../support/ports';

type PageType = import('@playwright/test').Page;

const enlargeText = (page: PageType, factor: number): Promise<{ before: number; after: number }> =>
    page.evaluate((scale) => {
        const nodes = [...document.querySelectorAll<HTMLElement>('body, body *')];
        const sizes = nodes.map((node) => parseFloat(getComputedStyle(node).fontSize));
        nodes.forEach((node, index) => node.style.setProperty('font-size', `${sizes[index] * scale}px`, 'important'));
        const sample = document.querySelector<HTMLElement>('h1, h2') ?? document.body;

        return { before: sizes[nodes.indexOf(sample)], after: parseFloat(getComputedStyle(sample).fontSize) };
    }, factor);

const applyTextSpacing = (page: PageType): Promise<void> =>
    page.evaluate(() => {
        for (const node of document.querySelectorAll<HTMLElement>('body, body *')) {
            node.style.setProperty('line-height', '1.5', 'important');
            node.style.setProperty('letter-spacing', '0.12em', 'important');
            node.style.setProperty('word-spacing', '0.16em', 'important');
            if (node.tagName === 'P') node.style.setProperty('margin-bottom', '2em', 'important');
        }
    });

const clippedText = (page: PageType): Promise<string[]> =>
    page.evaluate(() =>
        [...document.querySelectorAll<HTMLElement>('body *')]
            .filter((node) => {
                const style = getComputedStyle(node);
                const hides = ['hidden', 'clip'].includes(style.overflowX) || ['hidden', 'clip'].includes(style.overflowY);
                const visible = style.display !== 'none' && style.visibility !== 'hidden' && node.getBoundingClientRect().width > 4;
                const text = [...node.childNodes].some((child) => child.nodeType === 3 && child.textContent?.trim());

                return hides && visible && text && !node.classList.contains('sr-only') && (node.scrollWidth > node.clientWidth + 1 || node.scrollHeight > node.clientHeight + 1);
            })
            .map((node) => `${node.tagName.toLowerCase()}: ${node.textContent?.trim().slice(0, 40)}`),
    );

const horizontalOverflow = (page: import('@playwright/test').Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

test.describe('at 320 CSS pixels', () => {
    test.use({ viewport: { width: 320, height: 640 } });

    test('the tabs collapse behind a Menu button that opens them without horizontal scrolling', async ({ page }) => {
        await signInAs(page, 'administrator');
        const menu = page.getByRole('button', { name: 'Menu' });
        const tabs = page.getByRole('navigation', { name: 'Hoofdnavigatie' });

        await expect(menu).toBeVisible();
        await expect(menu).toHaveAttribute('aria-expanded', 'false');
        await expect(tabs).toBeHidden();
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

        await menu.focus();
        await page.keyboard.press('Enter');
        await expect(menu).toHaveAttribute('aria-expanded', 'true');
        await expect(tabs.getByRole('link')).toHaveText(['Overzicht', 'Beheer']);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

        await page.keyboard.press('Tab');
        await expect(tabs.getByRole('link', { name: 'Overzicht' })).toBeFocused();
        await expectNoAxeViolations(page);
    });

    test('at 200% text size the shell neither scrolls horizontally nor clips text', async ({ page }) => {
        const longName = (word: string): string => `${word.repeat(3)}${randomUUID().slice(0, 8)}`;
        const organisation = longName('Stichting-Kinderopvang-en-Onderwijs-');
        const canvas = longName('Voorbeeldcanvasmetheellangenaam');
        expect(Number.isFinite(createCanvas({ organisation, canvas }))).toBe(true);
        await signInAs(page, 'administrator');
        await expect(page.getByRole('heading', { name: organisation })).toBeVisible();
        await expect(page.getByRole('link', { name: canvas })).toBeVisible();
        const enlarged = await enlargeText(page, 2);
        expect(enlarged.after).toBeCloseTo(enlarged.before * 2, 1);
        await page.getByRole('button', { name: 'Menu' }).click();
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
        expect(await clippedText(page)).toEqual([]);

        await page.goto('/account');
        const account = await enlargeText(page, 2);
        expect(account.after).toBeCloseTo(account.before * 2, 1);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
        expect(await clippedText(page)).toEqual([]);
    });

    test('with the WCAG 1.4.12 text spacing the shell neither scrolls horizontally nor clips text', async ({ page }) => {
        await signInAs(page, 'administrator');
        await applyTextSpacing(page);
        await page.getByRole('button', { name: 'Menu' }).click();
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
        expect(await clippedText(page)).toEqual([]);

        await page.goto('/account');
        await applyTextSpacing(page);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
        expect(await clippedText(page)).toEqual([]);
    });

    test.describe('on the harness', () => {
    test.use({ baseURL: HARNESS_URL });

    test('the detail panel covers the main content instead of sitting next to it', async ({ page }) => {
        await page.goto('/?demo=Shell');
        await page.getByRole('button', { name: 'Eerste rij' }).click();

        const panel = await page.locator('#detail-panel').boundingBox();
        expect(panel?.x).toBeLessThanOrEqual(1);
        expect(panel?.width).toBeGreaterThanOrEqual(318);
        await expect(page.locator('#main')).toBeHidden();
        await expect(page.getByRole('button', { name: 'Eerste rij' })).toBeHidden();
    });
    });
});

test('at a wide screen the tabs show without the Menu button', async ({ page }) => {
    await signInAs(page, 'administrator');
    await expect(page.getByRole('button', { name: 'Menu' })).toBeHidden();
    await expect(page.getByRole('navigation', { name: 'Hoofdnavigatie' })).toBeVisible();
});
