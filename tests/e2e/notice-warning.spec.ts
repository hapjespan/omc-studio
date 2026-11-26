import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { createClosingNotice } from './support/notices';
import { signInAs } from './support/shell';

test('an unseen notice shows a status warning that leads to the list and clears after Gezien', async ({ page }) => {
    const canvas = `Waarschuwing ${randomUUID().slice(0, 8)}`;
    createClosingNotice(canvas, 5);
    await signInAs(page, 'administrator');

    const warning = page.getByRole('status').getByRole('link', { name: /nieuwe systeemmelding(en)?/ });
    await expect(warning).toBeVisible();
    await warning.click();
    await expect(page).toHaveURL(/\/admin\/system-notices$/);

    const notice = page.getByRole('listitem').filter({ hasText: canvas });
    await notice.getByRole('button', { name: /^Gezien/ }).click();
    const seen = notice.getByText('Gezien', { exact: true });
    await expect(seen).toBeVisible();
    await expect(seen).toBeFocused();
});

test('a Reader sees no warning', async ({ page }) => {
    createClosingNotice(`Lezer ${randomUUID().slice(0, 8)}`, 5);
    await signInAs(page, 'reader');

    await expect(page.getByRole('link', { name: /systeemmelding/ })).toBeHidden();
});
