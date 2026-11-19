import { expect, type Locator, type Page } from '@playwright/test';

export async function tabTo(page: Page, target: Locator, limit = 500): Promise<void> {
    await target.waitFor({ state: 'visible' });
    if (await page.evaluate(() => { const first = !(window as unknown as { __skipped?: boolean }).__skipped && (document.activeElement === document.body || document.activeElement === null); (window as unknown as { __skipped?: boolean }).__skipped = true; return first; })) {
        await page.keyboard.press('Tab');
        await page.keyboard.press('Enter');
    }
    for (const key of ['Tab', 'Shift+Tab']) {
        for (let step = 0; step < limit; step += 1) {
            if (await target.evaluate((element) => element === document.activeElement).catch(() => false)) return;
            await page.keyboard.press(key);
        }
    }
    await expect(target, `reached by Tab or Shift+Tab within ${limit} presses`).toBeFocused();
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
