import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };
const HEADER = 'code;naam;soort;leverancier;eigenaar;kostenplaats;applicatie;jaar;budget;prognose;run_pct;change_pct;fte';

function organisation(): number {
    const code = "$o = App\\Models\\Organisation::factory()->create(); App\\Models\\FiscalYear::factory()->create(['organisation_id' => $o->id, 'year' => 2026]); echo 'ID=' . $o->id;";
    return Number(/ID=(\d+)/.exec(execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString())?.[1]);
}

const csv = (...lines: string[]) => ({ name: 'import.csv', mimeType: 'text/csv', buffer: Buffer.from([HEADER, ...lines].join('\n') + '\n') });

test('a faulty file disables Importeren and announces the error count, a clean file imports', async ({ page }) => {
    const id = organisation();
    const code = randomUUID().slice(0, 8);
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${id}/cost-records/import`);
    await expect(page.getByRole('heading', { level: 1, name: 'Kostenposten importeren' })).toBeVisible();

    await page.getByLabel('CSV-bestand').setInputFiles(csv(`${code};Licenties;Project;;Ada;;;2026;12.50;;;;`));
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('status')).toContainText('Controle klaar: 1 fouten');
    await expect(page.getByRole('heading', { level: 2, name: 'Regels' })).toBeVisible();
    await expect(page.getByRole('cell', { name: /is geen bedrag/ })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Importeren' })).toBeDisabled();
    await expectNoAxeViolations(page);

    await page.getByLabel('CSV-bestand').setInputFiles(csv(`${code};Licenties;Project;;Ada;;;2026;12,50;;;;`));
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByRole('status')).toContainText('Controle klaar: geen fouten');
    await expect(page.getByRole('heading', { level: 2, name: 'Wordt aangemaakt: Eigenaren' })).toBeVisible();
    await page.getByRole('button', { name: 'Importeren' }).click();
    await expect(page.getByRole('status')).toContainText('Geïmporteerd: 1 nieuw');
});

test('a file with another header is refused with the file message', async ({ page }) => {
    const id = organisation();
    await signInAs(page, 'administrator');
    await page.goto(`/organisations/${id}/cost-records/import`);
    await page.getByLabel('CSV-bestand').setInputFiles({ name: 'x.csv', mimeType: 'text/csv', buffer: Buffer.from('a;b\n1;2\n') });
    await page.getByRole('button', { name: 'Controleren' }).click();
    await expect(page.getByText(/^Bestand: kies een CSV-bestand van maximaal 5 MB/)).toBeVisible();
    await expect(page.getByLabel('CSV-bestand')).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#import-file')).toBeFocused();
});
