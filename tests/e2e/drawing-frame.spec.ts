import { execFileSync } from 'node:child_process';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';
import { seedViewCanvas } from './support/view';

test('Tekenen shows Lagen, the Artboard, properties, toolbar and header', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });

    const layers = page.getByRole('navigation', { name: 'Lagen' });
    for (const area of ['Richten', 'Beheersen', 'Actoren', 'Kanalen', 'Waardeproposities', 'Primaire activiteiten', 'Ondersteunende activiteiten']) {
        await expect(layers.getByRole('button', { name: area, exact: true })).toBeVisible();
    }
    await expect(layers.getByRole('button', { name: 'Koers', exact: true })).toHaveCount(0);
    await expect(layers.getByRole('button', { name: 'Les geven', exact: true })).toBeVisible();

    await expect(page.locator('[data-drawing-surface]')).toBeVisible();
    await expect(page.getByRole('complementary', { name: 'Eigenschappen' })).toBeVisible();
    const toolbar = page.getByRole('toolbar', { name: 'Gereedschap' });
    for (const tool of ['Selecteren en verplaatsen', 'Schuiven', 'Uitlijnen', 'Gelijk verdelen']) await expect(toolbar.getByRole('button', { name: tool })).toBeVisible();
    await expect(toolbar.getByText(/^Zoom \d+%$/)).toBeVisible();
    for (const name of ['Ongedaan maken', 'Opnieuw', 'Alles automatisch opmaken', 'Passend maken']) await expect(page.getByRole('button', { name })).toBeVisible();
    await expect(page.getByText('Tekenvlak: OpenPencil, lokaal meegeleverd')).toBeVisible();

    for (const absent of ['Tekst', 'Pen', 'Vorm', 'Afbeelding', 'Component', 'Samenwerken']) await expect(toolbar.getByRole('button', { name: absent })).toHaveCount(0);
    await expectNoAxeViolations(page);
});

test('Tekenen reflows at 320 pixels without scrolling the page sideways', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });

    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(320);
});

test('Tekenen selection in Lagen reaches the drawing', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await expect(page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true })).toHaveAttribute('aria-pressed', 'true');
});

test('the mode switch loads Tekenen as a full page under its own policy and leaves it again', async ({ page }) => {
    const canvas = seedViewCanvas();
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/entry`);
    await page.getByRole('link', { name: 'Tekenen' }).click();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await page.getByRole('link', { name: 'Bekijken' }).click();
    await page.waitForURL(/\/view$/);
    const policy = await page.evaluate(async () => (await fetch(location.href)).headers.get('content-security-policy'));
    expect(policy).not.toContain('wasm-unsafe-eval');
    await page.getByRole('link', { name: 'Tekenen' }).click();
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await expect(page.getByRole('group', { name: 'Modus' })).toHaveCount(1);
});

test('Tekenen opens a Canvas with a stored Layout', async ({ page }) => {
    const canvas = seedViewCanvas();
    const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };
    const code = `$a = App\\Models\\Area::where('key', 'primary_activities')->value('id'); App\\Models\\Layout::create(['canvas_id' => ${canvas.id}, 'area_id' => $a, 'element_id' => null, 'x' => 40, 'y' => 220, 'width' => 900, 'height' => 400, 'z' => 0, 'style' => [], 'format_version' => 1]); App\\Models\\Canvas::whereKey(${canvas.id})->increment('layout_version');`;
    execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env });
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas.id}/drawing`);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
    await expect(page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true })).toBeVisible();
});
