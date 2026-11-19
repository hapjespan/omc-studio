import { expect, type Locator, type Page } from '@playwright/test';

export async function tabTo(page: Page, target: Locator, limit = 120): Promise<void> {
    for (let step = 0; step < limit; step += 1) {
        if (await target.evaluate((element) => element === document.activeElement).catch(() => false)) return;
        await page.keyboard.press('Tab');
    }
    await expect(target, `reached by Tab within ${limit} presses`).toBeFocused();
}

export async function type(page: Page, target: Locator, text: string): Promise<void> {
    await tabTo(page, target);
    await page.keyboard.type(text);
}

export async function activate(page: Page, target: Locator, key: 'Enter' | 'Space' = 'Enter'): Promise<void> {
    await tabTo(page, target);
    await page.keyboard.press(key);
}

export async function choose(page: Page, target: Locator, option: string): Promise<void> {
    await tabTo(page, target);
    await page.keyboard.type(option);
}
