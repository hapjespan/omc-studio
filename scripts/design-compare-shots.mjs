import { execFileSync } from 'node:child_process';
import { chromium } from '@playwright/test';

const [base, out] = process.argv.slice(2);
const run = (code) => execFileSync('php', ['artisan', 'tinker', `--execute=${code}`]).toString();
const canvas = /ID=(\S+)/.exec(run("echo 'ID=' . App\\Models\\Canvas::where('name', 'Voorbeeldcanvas')->value('id');"))[1];
const licences = /ID=(\S+)/.exec(run("echo 'ID=' . App\\Models\\CostRecord::where('code', 'KP-010')->value('id');"))[1];

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'nl-NL' })).newPage();
const shot = async (name) => {
    await page.waitForTimeout(800);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: true });
    console.log('shot', name);
};

await page.goto(`${base}/sign-in`);
await page.getByLabel('E-mailadres').fill('beheerder@voorbeeld.test');
await page.getByLabel('Wachtwoord').fill('Voorbeeld-wachtwoord-1');
await page.getByRole('button', { name: 'Inloggen' }).click();
await page.waitForURL((url) => url.pathname !== '/sign-in');

await page.goto(`${base}/canvases/${canvas}/cost-records?year=2026`);
await page.getByRole('button', { name: 'Team ICT', exact: true }).click();
await page.getByRole('dialog', { name: 'Team ICT' }).waitFor();
await shot('kostenposten-built-v2');

await page.goto(`${base}/canvases/${canvas}/allocation?year=2026&cost_record=${licences}`);
await page.getByRole('region', { name: 'Doelen op het canvas' }).waitFor();
for (const name of ['Financiën', 'ICT']) {
    const button = page.getByRole('button', { name: `Uitklappen: ${name}` });
    if ((await button.count()) > 0) await button.click();
}
for (const [name, value] of [['Personeel', '25'], ['Werkplek & beheer', '40'], ['Financiële administratie voeren', '15']]) {
    await page.getByRole('button', { name: `Toevoegen aan verdeling: ${name}` }).click();
    const input = page.getByLabel(new RegExp(`Percentage voor .*${name}`));
    await input.fill(value);
    await input.blur();
}
await shot('toewijzen-built-v2');

await page.goto(`${base}/canvases/${canvas}/view?overlay=financial&year=2026`);
await page.locator('[data-overlay-card] [data-total]').first().waitFor();
await page.mouse.move(0, 0);
await shot('bekijken-financieel-built-v2');

await browser.close();
