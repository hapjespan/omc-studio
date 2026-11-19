import { readFileSync } from 'node:fs';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedViewCanvas } from '../support/view';

const contract = readFileSync('specs/001-omc-studio-v1/design-contract.md', 'utf8');
const rgb = (hex: string): string => `rgb(${[1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16)).join(', ')})`;
const row = (name: string): string[] => [...(contract.split('\n').find((line) => line.startsWith(`| ${name}`)) ?? '').matchAll(/#[0-9A-F]{6}/g)].map((match) => match[0]);

const luminance = (colour: string): number => {
    const [r, g, b] = (colour.match(/\d+/g) ?? []).slice(0, 3).map((value) => {
        const s = Number(value) / 255;
        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const ratio = (a: string, b: string): number => {
    const [high, low] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (high + 0.05) / (low + 0.05);
};

test('every Area and level of a new Canvas shows the contract colours and every text meets the contrast minimum', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);

    const expected: [string, string][] = [
        ['Strategie', row('Richten, Beheersen')[0]],
        ['Onderwijs', row('Primaire activiteiten')[0]],
        ['Beheer', row('Ondersteunende activiteiten')[0]],
        ['Koers', row('Direction band')[0]],
    ];
    for (const [name, hex] of expected) await expect(page.getByRole('button', { name, exact: true })).toHaveCSS('background-color', rgb(hex));
    await expect(page.getByRole('button', { name: 'Plannen', exact: true })).toHaveCSS('background-color', 'rgb(255, 255, 255)');

    const pairs = await page.locator('[data-block]').evaluateAll((nodes) =>
        nodes.map((node) => ({ background: getComputedStyle(node).backgroundColor, text: getComputedStyle(node).color })).filter((pair) => pair.background !== 'rgba(0, 0, 0, 0)'),
    );
    expect(pairs.length).toBeGreaterThan(8);
    for (const pair of pairs) expect(ratio(pair.background, pair.text), `${pair.background} on ${pair.text}`).toBeGreaterThanOrEqual(4.5);
    await expectNoAxeViolations(page);
});

test('no control in Bekijken changes a colour', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'reader');
    await page.goto(`/canvases/${canvas.id}/view`);
    await expect(page.locator('input[type="color"], [aria-label*="kleur" i], [aria-label*="colour" i]')).toHaveCount(0);
});
