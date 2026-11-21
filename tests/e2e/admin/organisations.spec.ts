import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { signInAs } from '../support/shell';
import { seedPhp } from '../support/seed';
const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function seed(name: string, options: { canvas?: boolean; logo?: boolean } = {}): void {
    const logo = options.logo ? `$p = 'logos/' . Illuminate\\Support\\Str::random(40) . '.png'; Illuminate\\Support\\Facades\\Storage::disk('local')->put($p, base64_decode('${PIXEL}')); ` : '';
    const attributes = options.logo ? `['name' => '${name}', 'logo_path' => $p]` : `['name' => '${name}']`;
    const canvas = options.canvas ? "App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id]);" : '';
    seedPhp(`${logo}$o = App\\Models\\Organisation::factory()->create(${attributes}); ${canvas}`);
}

const unique = (): string => `Stichting ${randomUUID().slice(0, 8)}`;

test('an Organisation is created, a name differing in case is refused, and the page is accessible', async ({ page }) => {
    const name = unique();
    await signInAs(page, 'administrator');
    await page.goto('/admin/organisations');
    await expect(page.getByRole('heading', { level: 1, name: 'Organisaties' })).toBeVisible();
    await expect(page).toHaveTitle(/Organisaties/);

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const panel = page.getByRole('dialog', { name: 'Nieuwe organisatie' });
    await panel.getByLabel('Naam').fill(name);
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel).toBeHidden();
    await expect(page.getByRole('button', { name, exact: true })).toBeVisible();

    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    await panel.getByLabel('Naam').fill(` ${name.toUpperCase()} `);
    await panel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(panel.getByRole('link', { name: 'Naam: er bestaat al een organisatie met deze naam' })).toBeVisible();
    await expect(panel.getByLabel('Naam')).toHaveAttribute('aria-invalid', 'true');
    await expectNoAxeViolations(page);
});

test('a file that is not an image named logo.png is refused with the logo message', async ({ page }) => {
    const name = unique();
    seed(name);
    await signInAs(page, 'administrator');
    await page.goto('/admin/organisations');
    await page.getByRole('button', { name, exact: true }).click();
    const panel = page.getByRole('dialog', { name });

    await panel.getByLabel('Logo').setInputFiles({ name: 'logo.png', mimeType: 'image/png', buffer: Buffer.from('this is not an image') });
    await panel.getByRole('button', { name: 'Opslaan' }).click();

    await expect(panel.getByRole('link', { name: 'Logo: kies een PNG- of JPEG-afbeelding van maximaal 1 MB en 4000 bij 4000 pixels' })).toBeVisible();
    await expect(panel.getByLabel('Logo')).toHaveAttribute('aria-invalid', 'true');
    await expect(panel.getByLabel('Logo')).toBeFocused();
    await expectNoAxeViolations(page);
});

test('the logo has the Organisation name as text alternative', async ({ page }) => {
    const name = unique();
    seed(name, { logo: true });
    await signInAs(page, 'administrator');
    await page.goto('/admin/organisations');
    await page.getByRole('button', { name, exact: true }).click();

    await expect(page.getByRole('dialog', { name }).getByRole('img', { name })).toBeVisible();
});

test('deleting is refused while a Canvas exists, then archive, restore and a typed-name deletion work', async ({ page }) => {
    const filled = unique();
    const empty = unique();
    seed(filled, { canvas: true });
    seed(empty);
    await signInAs(page, 'administrator');
    await page.goto('/admin/organisations');

    await page.getByRole('button', { name: `Verwijderen ${filled}` }).click();
    await expect(page.getByRole('dialog', { name: `${filled} verwijderen?` })).toContainText('Verwijdert ook het logboek van deze organisatie');
    const dialog = page.getByRole('dialog', { name: `${filled} verwijderen?` });
    await dialog.getByLabel('Naam ter bevestiging').fill(filled);
    await dialog.getByRole('button', { name: 'Verwijderen', exact: true }).click();
    await expect(page.getByRole('alert').getByText('Deze organisatie heeft nog canvassen of kostenposten. Archiveer haar, of verwijder die eerst')).toBeVisible();
    await expect(page.getByRole('button', { name: filled, exact: true })).toBeVisible();

    await page.getByRole('button', { name: filled, exact: true }).click();
    await page.getByRole('dialog', { name: filled }).getByRole('button', { name: 'Archiveren' }).click();
    await expect(page.getByRole('button', { name: filled, exact: true })).toBeHidden();
    await expect(page.getByRole('status').filter({ hasText: 'Organisatie gearchiveerd' })).toBeVisible();
    await expect(page.getByRole('searchbox', { name: 'Zoeken' })).toBeFocused();

    await page.getByLabel('Toon gearchiveerd').check();
    await expect(page.getByRole('row', { name: new RegExp(filled) })).toContainText('Gearchiveerd');
    await page.getByRole('button', { name: `Terugzetten ${filled}` }).click();
    await expect(page.getByRole('row', { name: new RegExp(filled) })).not.toContainText('Gearchiveerd');
    await expect(page.getByRole('status').filter({ hasText: 'Organisatie teruggezet' })).toBeVisible();
    await expect(page.getByRole('searchbox', { name: 'Zoeken' })).toBeFocused();

    await page.getByRole('button', { name: `Verwijderen ${empty}` }).click();
    const confirm = page.getByRole('dialog', { name: `${empty} verwijderen?` });
    const remove = confirm.getByRole('button', { name: 'Verwijderen', exact: true });
    await expect(remove).toBeDisabled();
    await confirm.getByLabel('Naam ter bevestiging').fill(empty);
    await remove.click();
    await expect(page.getByRole('button', { name: empty, exact: true })).toBeHidden();
});

test('a Reader requesting the Organisations page gets Geen toegang', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/organisations');
    expect(response?.status()).toBe(403);
});
