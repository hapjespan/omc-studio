import { expect, test } from '../support/guards';
import { openEntry, seedEntryCanvas } from '../support/entry';
import { expectWithinBudget, PERFORMANCE_TAG, sample } from '../support/timing';

const fill = "for ($i = 0; $i < 25; $i++) { $d = App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'domain', 'name' => 'Domein ' . $i, 'position' => $i]); for ($j = 0; $j < 19; $j++) { App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'activity', 'parent_id' => $d->id, 'name' => 'Activiteit ' . $i . '-' . $j, 'position' => $j]); } }";

test('Invoeren opens within one second on a Canvas of 500 Elements', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(240_000);
    const canvas = seedEntryCanvas('primary_activities', [], fill);
    await openEntry(page, canvas);

    const samples = await sample(async () => {
        await page.goto('about:blank');
        const opened = Date.now();
        await page.goto(`/canvases/${canvas}/entry`);
        await page.getByRole('navigation', { name: 'Area’s' }).getByRole('button', { name: /Primaire activiteiten/ }).click();
        await expect(page.getByTestId('entry-row').first()).toBeVisible();

        return Date.now() - opened;
    });

    await expect(page.getByTestId('entry-row')).toHaveCount(500);
    expectWithinBudget(samples, 'Invoeren opens', browserName);

    const last = page.getByTestId('entry-row').last().getByRole('button').first();
    await last.focus();
    await expect(last).toBeInViewport();
    await last.press('Enter');
    await expect(page.getByRole('complementary').getByRole('button', { name: 'Opslaan', exact: true })).toBeVisible();
});

test('an Element is added within one second on a Canvas of 500 Elements', { tag: PERFORMANCE_TAG }, async ({ page, browserName }) => {
    test.setTimeout(240_000);
    const canvas = seedEntryCanvas('primary_activities', [], fill);
    await openEntry(page, canvas);
    const line = page.getByLabel('Activiteit of cluster toevoegen aan Domein 0, Enter om op te slaan');
    let counter = 0;

    const samples = await sample(async () => {
        const name = `Nieuw ${(counter += 1)}`;
        await line.fill(name);
        await page.evaluate((added) => {
            const target = window as unknown as { __added: Promise<number> };
            target.__added = new Promise<number>((resolve) => {
                let started = 0;
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') started = performance.now();
                }, { capture: true, once: true });
                const watcher = new MutationObserver(() => {
                    const present = Array.from(document.querySelectorAll('[data-testid="entry-row"]')).some((row) => row.textContent?.includes(added));
                    if (present && started > 0) {
                        watcher.disconnect();
                        resolve(performance.now() - started);
                    }
                });
                watcher.observe(document.body, { childList: true, subtree: true, characterData: true });
            });
        }, name);
        await line.press('Enter');

        return page.evaluate(() => (window as unknown as { __added: Promise<number> }).__added);
    });

    expectWithinBudget(samples, 'Invoeren adds an Element', browserName);
});
