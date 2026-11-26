import type { Page } from '@playwright/test';
import { expect, test } from './support/guards';
import { openDrawing } from './support/drawing';
import { seedViewCanvas } from './support/view';

const signOutRequests = (page: Page): string[] => {
    const methods: string[] = [];
    page.on('request', (request) => {
        if (new URL(request.url()).pathname === '/sign-out') methods.push(request.method());
    });

    return methods;
};

const editX = async (page: Page): Promise<void> => {
    await page.getByRole('navigation', { name: 'Lagen' }).getByRole('button', { name: 'Onderwijs', exact: true }).click();
    await page.getByLabel('X van Onderwijs').fill('50');
    await page.getByLabel('X van Onderwijs').press('Enter');
};

const chooseSignOut = async (page: Page): Promise<void> => {
    await page.getByRole('button', { name: 'Administrator' }).click();
    await page.getByRole('menuitem', { name: 'Uitloggen' }).click();
};

const expectSignedOut = async (page: Page): Promise<void> => {
    await expect(page.getByRole('heading', { name: 'Inloggen' })).toBeVisible();
    const protectedPage = await page.request.get('/', { maxRedirects: 0 });
    expect(protectedPage.status()).toBe(302);
    expect(protectedPage.headers().location).toContain('/sign-in');
};

test('signing out from a saved drawing ends the session with one POST', async ({ page }) => {
    const methods = signOutRequests(page);
    await openDrawing(page, seedViewCanvas());
    await chooseSignOut(page);
    await expectSignedOut(page);
    expect(methods).toEqual(['POST']);
});

test('signing out during a save waits for it and then sends the original POST once', async ({ page }) => {
    const methods = signOutRequests(page);
    await openDrawing(page, seedViewCanvas());
    let release: () => void = () => undefined;
    const gate = new Promise<void>((resolve) => (release = resolve));
    await page.route('**/layout', async (route) => {
        if (route.request().method() !== 'PUT') return route.fallback();
        await gate;
        await route.fallback();
    });
    await editX(page);
    await expect(page.locator('[data-save-state]')).toContainText('Opslaan');
    await chooseSignOut(page);
    expect(methods).toEqual([]);
    release();
    await expectSignedOut(page);
    expect(methods).toEqual(['POST']);
});

test('a failed save keeps the session when staying and signs out when discarding', async ({ page, context }) => {
    const methods = signOutRequests(page);
    await openDrawing(page, seedViewCanvas());
    await context.setOffline(true);
    await editX(page);
    await expect(page.locator('[data-save-state]')).toContainText('Opmaak niet opgeslagen');
    await chooseSignOut(page);
    const leave = page.getByRole('dialog', { name: 'Wijziging niet opgeslagen' });
    await expect(leave).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(leave).toBeHidden();
    await context.setOffline(false);
    expect(methods).toEqual([]);
    expect((await page.request.get('/', { maxRedirects: 0 })).status()).toBe(200);
    await chooseSignOut(page);
    await page.getByRole('dialog', { name: 'Wijziging niet opgeslagen' }).getByRole('button', { name: 'Wijziging weggooien' }).click();
    await expectSignedOut(page);
    expect(methods).toEqual(['POST']);
});

test('ordinary navigation to and from the drawing stays a GET with a full page load', async ({ page }) => {
    const canvas = seedViewCanvas();
    await openDrawing(page, canvas);
    await page.getByRole('link', { name: 'Bekijken' }).click();
    await page.waitForURL(/\/view$/);
    await page.getByRole('link', { name: 'Tekenen' }).click();
    await page.waitForURL(/\/drawing$/);
    await page.locator('[data-state="ready"]').waitFor({ timeout: 15000 });
});
