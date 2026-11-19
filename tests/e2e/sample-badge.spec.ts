import { expect, expectNoAxeViolations, test } from './support/guards';
import { signInAs } from './support/shell';

test('the sample Organisation carries the text label Voorbeeld on its overview section', async ({ page }) => {
    await signInAs(page, 'administrator');
    await page.goto('/overview');
    const section = page.getByRole('region', { name: /Voorbeeld BV/ });
    await expect(section.getByText('Voorbeeld', { exact: true })).toBeVisible();
    await expectNoAxeViolations(page);
});
