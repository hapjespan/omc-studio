import { randomUUID } from 'node:crypto';
import { expect, expectNoAxeViolations, test } from './support/guards';
import { createClosingNotice } from './support/notices';
import { signInAs } from './support/shell';

test('an Administrator finds the closing-check notice with its facts and filters on Canvas', async ({ page }) => {
    const canvas = `Melding ${randomUUID().slice(0, 8)}`;
    createClosingNotice(canvas, 1);
    await signInAs(page, 'administrator');
    await page.goto('/admin');
    await page.getByRole('link', { name: 'Systeemmeldingen' }).click();

    await expect(page.getByRole('heading', { level: 1, name: 'Systeemmeldingen' })).toBeVisible();
    const notice = page.getByRole('listitem').filter({ hasText: canvas });
    await expect(notice).toContainText('Cijfers sluiten niet');
    await expect(notice).toContainText('2026');
    await expect(notice).toContainText('Budget: som van de verdeelde bedragen');
    await expect(notice).toContainText('Geen filter');
    await expect(notice).toContainText('1 cent');

    await page.getByLabel('Canvas').selectOption({ label: canvas });
    await page.getByRole('button', { name: 'Filteren' }).click();
    await expect(page.getByTestId('notice-count')).toHaveText('1 melding');
    await expectNoAxeViolations(page);
});

test('a Reader gets no access to the list', async ({ page }) => {
    await signInAs(page, 'reader');
    const response = await page.goto('/admin/system-notices');

    expect(response?.status()).toBe(403);
    await expect(page.getByText('Geen toegang')).toBeVisible();
});
