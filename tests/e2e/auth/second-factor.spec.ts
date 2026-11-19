import { createHmac } from 'node:crypto';
import type { Locator, Page } from '@playwright/test';
import { expectNoAxeViolations, expect, test } from '../support/guards';
import { createUser } from '../support/accounts';

const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function decodeKey(key: string): Buffer {
    let bits = '';
    for (const character of key.replace(/[\s=]/g, '').toUpperCase()) {
        bits += alphabet.indexOf(character).toString(2).padStart(5, '0');
    }

    return Buffer.from((bits.match(/.{8}/g) ?? []).map((byte) => parseInt(byte, 2)));
}

function step(): number {
    return Math.floor(Date.now() / 30000);
}

function totp(key: string, at: number): string {
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(at));
    const digest = createHmac('sha1', decodeKey(key)).update(counter).digest();
    const offset = digest[digest.length - 1] & 0xf;

    return ((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000).toString().padStart(6, '0');
}

async function unusedCode(key: string, used: number): Promise<string> {
    while (step() <= used) {
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }

    return totp(key, step());
}

function factorSection(page: Page): Locator {
    return page.getByRole('region', { name: 'Tweede factor' });
}

async function signIn(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(email);
    await page.getByLabel('Wachtwoord', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.waitForURL((url) => url.pathname !== '/sign-in');
}

async function readCodes(page: Page): Promise<string[]> {
    return (await page.getByTestId('recovery-codes').innerText()).trim().split(/\s+/);
}

test.beforeEach(async ({ context, browserName }) => {
    if (browserName === 'chromium') {
        await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    }
});

test('turn the second factor on, sign in with a code and once with a recovery code', async ({ page, browserName }) => {
    test.slow();
    const user = createUser();
    const section = factorSection(page);

    await signIn(page, user.email, user.password);
    await page.goto('/account');
    await expectNoAxeViolations(page);

    await section.getByLabel('Huidig wachtwoord').fill(user.password);
    await section.getByRole('button', { name: 'Tweede factor aanzetten' }).click();

    const key = (await page.getByTestId('second-factor-key').innerText()).trim();
    await expect(page.getByRole('img', { name: 'QR-code met de sleutel die hieronder als tekst staat' })).toBeVisible();
    await expect(page.getByLabel('Code uit de app')).toBeFocused();
    await expectNoAxeViolations(page);

    const enrolled = step();
    await page.getByLabel('Code uit de app').fill(totp(key, enrolled));
    await page.getByRole('button', { name: 'Bevestigen' }).click();

    const codes = await readCodes(page);
    expect(codes).toHaveLength(8);

    await page.getByRole('button', { name: 'Kopiëren' }).click();
    await expect(page.getByText('Herstelcodes gekopieerd')).toBeVisible();

    if (browserName === 'chromium') {
        expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(codes.join('\n'));
    }

    const download = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Downloaden als tekstbestand' }).click();
    expect((await download).suggestedFilename()).toBe('herstelcodes.txt');

    await page.goto('/');
    await page.goBack();
    await expect(page.getByTestId('recovery-codes')).toHaveCount(0);
    await expect(page.getByText('Nog 8 herstelcodes')).toBeVisible();

    await page.goto('/');
    await page.getByRole('banner').getByRole('button', { name: /^(Lezer|Administrator)$/ }).click();
    await page.getByRole('menuitem', { name: 'Uitloggen' }).click();
    await expect(page.getByRole('heading', { name: 'Inloggen' })).toBeVisible();

    await signIn(page, user.email, user.password);
    await expect(page.getByRole('heading', { name: 'Tweede factor' })).toBeVisible();
    await expect(page.getByLabel('Code of herstelcode')).toHaveAttribute('autocomplete', 'one-time-code');
    await expectNoAxeViolations(page);

    await page.getByLabel('Code of herstelcode').fill(await unusedCode(key, enrolled));
    await page.getByRole('button', { name: 'Doorgaan' }).click();
    await expect(page.getByRole('heading', { name: 'Overzicht' })).toBeVisible();

    await page.getByRole('banner').getByRole('button', { name: /^(Lezer|Administrator)$/ }).click();
    await page.getByRole('menuitem', { name: 'Uitloggen' }).click();
    await signIn(page, user.email, user.password);
    await page.getByLabel('Code of herstelcode').fill(codes[0]);
    await page.getByRole('button', { name: 'Doorgaan' }).click();
    await expect(page.getByRole('heading', { name: 'Overzicht' })).toBeVisible();

    await page.goto('/account');
    await expect(page.getByText('Nog 7 herstelcodes')).toBeVisible();
});

test('new recovery codes replace the earlier ones and the factor can be turned off', async ({ page }) => {
    test.slow();
    const user = createUser();
    const section = factorSection(page);

    await signIn(page, user.email, user.password);
    await page.goto('/account');
    await section.getByLabel('Huidig wachtwoord').fill(user.password);
    await section.getByRole('button', { name: 'Tweede factor aanzetten' }).click();

    const key = (await page.getByTestId('second-factor-key').innerText()).trim();
    await page.getByLabel('Code uit de app').fill(totp(key, step()));
    await page.getByRole('button', { name: 'Bevestigen' }).click();

    const earlier = await readCodes(page);

    await section.getByLabel('Huidig wachtwoord').fill(user.password);
    await section.getByRole('button', { name: 'Nieuwe herstelcodes' }).click();
    await expect(page.getByText('Nieuwe herstelcodes aangemaakt')).toBeVisible();

    const later = await readCodes(page);
    expect(later).toHaveLength(8);
    expect(later).not.toEqual(earlier);
    await expect(page.getByText('Nog 8 herstelcodes')).toBeVisible();
    await expectNoAxeViolations(page);

    await section.getByLabel('Huidig wachtwoord').fill(user.password);
    await section.getByRole('button', { name: 'Tweede factor uitzetten' }).click();

    await expect(section.getByText('Tweede factor staat uit')).toBeVisible();
    await expect(section.getByRole('button', { name: 'Tweede factor aanzetten' })).toBeVisible();

    await page.goto('/');
    await page.getByRole('banner').getByRole('button', { name: /^(Lezer|Administrator)$/ }).click();
    await page.getByRole('menuitem', { name: 'Uitloggen' }).click();
    await signIn(page, user.email, user.password);

    await expect(page.getByRole('heading', { name: 'Overzicht' })).toBeVisible();
});
