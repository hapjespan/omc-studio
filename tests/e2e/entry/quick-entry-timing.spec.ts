import { expect, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';

const domains = ['Inkoop', 'Verkoop', 'Productie'];
const input = domains.flatMap((domain) => [domain, ...[1, 2, 3, 4, 5].map((number) => `  ${domain} activiteit ${number}`)]).join('\n');

test('3 Domains with 5 Activities each are on the Canvas within one minute', async ({ page }) => {
    test.setTimeout(120_000);
    const canvas = seedEntryCanvas('primary_activities');
    await openEntry(page, canvas);

    const started = Date.now();
    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    await page.getByLabel('Regels').fill(input);
    await expect(page.getByTestId('quick-entry-line')).toHaveCount(18);
    await page.getByRole('button', { name: 'Opslaan', exact: true }).click();

    const rows = page.getByRole('list', { name: 'Structuur van Primaire activiteiten' }).getByTestId('entry-row');
    await expect(rows).toHaveCount(18);
    expect(Date.now() - started).toBeLessThan(60_000);

    const listed = await rows.evaluateAll((nodes) => nodes.map((node) => ({ level: Number(node.getAttribute('data-depth')), text: node.textContent ?? '' })));
    expect(listed.filter((row) => row.level === 0)).toHaveLength(3);
    for (const domain of domains) {
        const at = listed.findIndex((row) => row.level === 0 && row.text.includes(domain));
        const children = listed.slice(at + 1, at + 6);

        expect(at, `${domain} is a Domain row`).toBeGreaterThanOrEqual(0);
        expect(children.map((row) => row.level)).toEqual([1, 1, 1, 1, 1]);
        children.forEach((row, index) => expect(row.text).toContain(`${domain} activiteit ${index + 1}`));
    }
});
