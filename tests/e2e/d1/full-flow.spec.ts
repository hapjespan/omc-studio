import type { Page } from '@playwright/test';
import { expect, expectNoAxeViolations, test } from '../support/guards';
import { tinker, unique } from '../support/overview';
import { signInAs } from '../support/shell';

async function workaroundGapReferrerBack(page: Page): Promise<void> {
    await page.goto(page.url());
}

test('an Administrator creates an Organisation and a Canvas, fills it through Invoeren and Snel invoeren, writes the Koers and views the result in Bekijken', async ({ page, guards }) => {
    const organisation = unique('Stichting');
    const canvas = unique('Operating model');
    tinker('app(App\\Services\\Operations\\InstallSteps::class)->run();');
    await signInAs(page, 'administrator');

    await guards.assertNoCspViolation();
    await page.goto('/admin/organisations');
    await page.getByRole('button', { name: 'Nieuw', exact: true }).click();
    const organisationPanel = page.getByRole('dialog', { name: 'Nieuwe organisatie' });
    await expect(organisationPanel).toBeVisible();
    await expectNoAxeViolations(page);
    await organisationPanel.getByLabel('Naam').fill(organisation);
    await organisationPanel.getByRole('button', { name: 'Opslaan' }).click();
    await expect(page.getByRole('button', { name: organisation, exact: true })).toBeVisible();
    await expectNoAxeViolations(page);

    await guards.assertNoCspViolation();
    await page.goto('/overview');
    await page.getByRole('button', { name: 'Nieuw canvas' }).click();
    const dialog = page.getByRole('dialog', { name: 'Nieuw canvas' });
    await expect(dialog).toBeVisible();
    await expectNoAxeViolations(page);
    await dialog.getByLabel('Naam').fill(canvas);
    await dialog.getByRole('combobox', { name: 'Organisatie' }).click();
    await page.getByRole('option', { name: organisation }).click();
    await expectNoAxeViolations(page);
    await dialog.getByRole('button', { name: 'Aanmaken' }).click();
    await page.waitForURL(/\/canvases\/(\d+)\/entry/);
    const id = /\/canvases\/(\d+)\/entry/.exec(page.url())?.[1];
    await expectNoAxeViolations(page);
    await guards.assertNoCspViolation();
    await workaroundGapReferrerBack(page);

    const areas = page.getByRole('navigation', { name: 'Areas' });
    await areas.getByRole('button', { name: /Primaire activiteiten/ }).click();
    await page.getByRole('button', { name: 'Domein', exact: true }).click();
    const field = page.getByLabel('+ Domein');
    await field.fill('Onderwijs');
    await field.press('Enter');
    const rows = page.getByRole('list', { name: 'Structuur van Primaire activiteiten' }).getByTestId('entry-row');
    await expect(rows.filter({ hasText: 'Onderwijs' })).toHaveCount(1);
    await expectNoAxeViolations(page);

    const line = page.getByLabel('Activiteit of cluster toevoegen aan Onderwijs, Enter om op te slaan');
    await line.fill('Les geven');
    await line.press('Enter');
    await expect(rows.filter({ hasText: 'Les geven' })).toHaveCount(1);
    await page.getByLabel('Cluster', { exact: true }).check();
    await line.fill('Lessen');
    await line.press('Enter');
    await expect(rows.filter({ hasText: 'Lessen' })).toHaveCount(1);
    await expectNoAxeViolations(page);

    await rows.filter({ hasText: 'Les geven' }).getByRole('button', { name: 'Activiteit Les geven', exact: true }).click();
    const panel = page.getByRole('complementary');
    await panel.getByLabel('Hangt onder').focus();
    await page.keyboard.press('Enter');
    await page.getByRole('option', { name: 'Primaire activiteiten > Onderwijs > Lessen' }).click();
    await panel.getByRole('button', { name: 'Opslaan', exact: true }).click();
    await expect(panel.getByText('Activiteit · Primaire activiteiten › Onderwijs › Lessen')).toBeVisible();
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Snel invoeren' }).click();
    await page.getByLabel('Regels').fill('Onderzoek\n  Publiceren\n  Subsidie aanvragen\nBeheer');
    await expect(page.getByRole('list', { name: 'Voorbeeld' }).getByTestId('quick-entry-line')).toHaveCount(4);
    await page.getByRole('region', { name: /^Snel invoeren in/ }).getByRole('button', { name: 'Opslaan', exact: true }).click();
    await expect(rows.filter({ hasText: 'Publiceren' })).toHaveCount(1);
    await expect(rows.filter({ hasText: 'Subsidie aanvragen' })).toHaveCount(1);
    await expect(rows.filter({ hasText: 'Beheer' })).toHaveCount(1);
    await expectNoAxeViolations(page);

    await areas.getByRole('button', { name: /Koers/ }).click();
    const koers = page.getByRole('region', { name: 'Koers' });
    const missionForm = koers.locator('form').filter({ has: page.getByLabel('Missie', { exact: true }) });
    await missionForm.getByLabel('Missie', { exact: true }).fill('Leren voor het leven');
    await missionForm.getByRole('button', { name: 'Opslaan', exact: true }).click();
    await expect(koers.getByLabel('Missie', { exact: true })).toHaveValue('Leren voor het leven');
    const goals = koers.getByRole('region', { name: 'Strategische doelen' });
    for (const title of ['Groei', 'Kwaliteit']) {
        await goals.getByLabel('Titel', { exact: true }).fill(title);
        await goals.getByLabel('Uitleg', { exact: true }).fill(`Uitleg ${title}`);
        await goals.getByRole('button', { name: '+ strategisch doel' }).click();
        await expect(goals.getByRole('listitem').filter({ hasText: title })).toHaveCount(1);
    }
    await expectNoAxeViolations(page);

    await guards.assertNoCspViolation();
    await page.goto(`/canvases/${id}/view`);
    await expect(page.getByRole('heading', { level: 1, name: canvas })).toBeVisible();
    await expect(page.locator('[data-artboard]')).toBeVisible();
    for (const area of ['Koers', 'Richten', 'Beheersen', 'Actoren', 'Kanalen', 'Waardeproposities', 'Primaire activiteiten', 'Ondersteunende activiteiten']) {
        await expect(page.getByRole('button', { name: area, exact: true })).toBeVisible();
    }
    for (const element of ['Onderwijs', 'Lessen', 'Onderzoek']) {
        await expect(page.getByRole('button', { name: element, exact: true })).toBeVisible();
    }
    for (const element of ['Les geven', 'Publiceren', 'Subsidie aanvragen']) {
        await expect(page.getByRole('button', { name: new RegExp(`^${element}( |$)`) })).toBeVisible();
    }
    const band = page.locator('[data-direction="goals"]');
    await expect(band).toContainText('1. Groei Uitleg Groei');
    await expect(band).toContainText('2. Kwaliteit Uitleg Kwaliteit');
    await page.getByRole('button', { name: 'Koers', exact: true }).click();
    const detail = page.locator('#detail-panel');
    await expect(detail).toContainText('Leren voor het leven');
    await expect(detail).toContainText('Groei');
    await expect(detail).toContainText('Kwaliteit');
    await expectNoAxeViolations(page);
    await guards.assertNoCspViolation();
});
