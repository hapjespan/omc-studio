# Foundation gaps F-2, F-6, G-8, K-28 (T386): evidence

Branch `feat/d1-20g-foundation-gaps`, cut from `release`.

## Delivery 1 before and after

Method: the five delivery 1 screens (sign-in, overview, invoeren, bekijken, gebruikers) at 1440 by 900, full page, chromium, reduced motion, signed in as the administrator of the neutral seed `.scratch/compare-seed.php`. Before: the tree at the start of the work, built and shot before any change. After: the final tree, rebuilt. The top bar of each signed-in screen was also shot as an element. Each pair was also byte-identical as a PNG file (md5 equal for all nine pairs), and was compared pixel by pixel in the browser (any channel difference counts). Reproduce with `.scratch/serve-shots.sh seed` (migrate, seed with `compare-seed.php`, serve on 8324), `node .scratch/shots.mjs <before|after> 2` and `node .scratch/diff.mjs`; the capture script signs in, then shoots sign-in, overview, `/canvases/2/entry` (Primaire activiteiten, Roosters maken), `/canvases/2/view` and `/admin/users` (Mila de Vries opened) full page plus the `header` element, and the diff script draws each pair into a canvas through Playwright `page.evaluate` and counts pixels where any channel differs.

| Screen | Size before and after | Differing pixels | Top bar (1440 by 56) differing pixels |
|---|---|---|---|
| sign-in | 1440 by 900 | 0 | no top bar |
| overview | 1440 by 900 | 0 | 0 |
| invoeren | 1440 by 1526 | 0 | 0 |
| bekijken | 1440 by 900 | 0 | 0 |
| gebruikers | 1440 by 900 | 0 | 0 |

No component is used on a delivery 1 screen; the only shared markup touched is `Field` (a `hideLabel` prop, off by default) and `TopBar` (wrapping classes that only act when content is too wide).

## F-6: top bar at 320 CSS pixels

Harness page `TopBarDemo.vue` mirrors the shell header with an organisation name and a canvas name of 71 and 89 characters with hyphens (the longest unbreakable run is 44 characters), the sample pill, the fiscal year slot ("Boekjaar 2026", "Concept") and the role menu.

| | Horizontal overflow of the document |
|---|---|
| Before (failing test first) | 20 pixels, with the Menu open as well |
| After | 0 |

The Menu button still precedes the tabs in tab order, the Menu, fiscal year and role menu all lie inside the bar, and axe passes. The fix is `[overflow-wrap:anywhere]` and `max-w-full` on the breadcrumb and `flex-wrap` with `min-w-0` on the right-hand group.

## Browser checks

`tests/e2e/components/foundation-gaps.spec.ts` (harness `GapsDemo.vue`): inline field 84 by 36, hidden label still the accessible name, "%" suffix, description and error, border colours; native select 36 high, border `#848B95`, labelled empty option, string model, error state; report table card border and radius, caption, column and row headers, right-aligned tabular figures, empty state, axe. `tests/e2e/shell/topbar-narrow.spec.ts`: the 320 pixel top bar.

## Scripts

`.scratch/serve-shots.sh`:

```sh
#!/bin/sh
cd /workspace/omc-studio/.worktrees/d1-20g
[ -f .scratch/shots.pid ] && kill "$(cat .scratch/shots.pid)" 2>/dev/null
export APP_NAME="OMC Studio" SESSION_SECURE_COOKIE=false APP_LOCALE=nl APP_FALLBACK_LOCALE=nl
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/.scratch/shots.sqlite" DB_URL= SESSION_DRIVER=database CACHE_STORE=file APP_ENV=local
if [ "$1" = "seed" ]; then
    rm -f "$DB_DATABASE"
    touch "$DB_DATABASE"
    php artisan migrate --force > .scratch/shots-migrate.log 2>&1
    php artisan tinker --execute="require '/workspace/omc-studio/.scratch/compare-seed.php';" > .scratch/shots-seed.log 2>&1
fi
setsid php -d display_errors=0 -d error_reporting=0 -S 127.0.0.1:8324 -t public > .scratch/shots-serve.log 2>&1 &
echo $! > .scratch/shots.pid
curl --silent --output /dev/null --retry 20 --retry-connrefused --retry-delay 1 http://127.0.0.1:8324/
```

`.scratch/shots.mjs`:

```js
import { chromium } from '@playwright/test';

const base = 'http://127.0.0.1:8324';
const phase = process.argv[2];
const canvas = process.argv[3] ?? '1';
const out = `/workspace/omc-studio/.worktrees/d1-20g/.scratch/shots-${phase}`;

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'nl-NL', reducedMotion: 'reduce' });
const page = await context.newPage();
const shot = async (name) => {
    await page.waitForTimeout(600);
    await page.screenshot({ path: `${out}/${name}.png`, fullPage: true });
    const bar = page.locator('header').first();
    if (await bar.count()) await bar.screenshot({ path: `${out}/${name}-topbar.png` });
};

await page.goto(`${base}/sign-in`);
await page.getByRole('heading', { level: 1 }).waitFor();
await shot('sign-in');
await page.getByLabel('E-mailadres').fill('beheerder@voorbeeld.test');
await page.getByLabel('Wachtwoord').fill('Voorbeeld-wachtwoord-1');
await page.getByRole('button', { name: 'Inloggen' }).click();
await page.waitForURL((url) => url.pathname !== '/sign-in');
await page.getByRole('heading', { level: 1 }).waitFor();
await shot('overview');

await page.goto(`${base}/canvases/${canvas}/entry`);
await page.getByRole('navigation', { name: 'Areas' }).getByRole('button', { name: /Primaire activiteiten/ }).click();
await page.getByRole('button', { name: /Roosters maken/ }).first().click();
await shot('invoeren');

await page.goto(`${base}/canvases/${canvas}/view`);
await shot('bekijken');

await page.goto(`${base}/admin/users`);
await page.getByRole('heading', { level: 1 }).waitFor();
await page.getByText('Mila de Vries').first().click();
await page.waitForTimeout(800);
await shot('gebruikers');

await browser.close();
```

`.scratch/diff.mjs`:

```js
import { chromium } from '@playwright/test';
import { readFileSync, readdirSync } from 'node:fs';

const dir = '/workspace/omc-studio/.worktrees/d1-20g/.scratch';
const browser = await chromium.launch();
const page = await browser.newPage();
for (const file of readdirSync(`${dir}/shots-before`).sort()) {
    const a = readFileSync(`${dir}/shots-before/${file}`).toString('base64');
    const b = readFileSync(`${dir}/shots-after/${file}`).toString('base64');
    const result = await page.evaluate(async ([x, y]) => {
        const load = (data) => new Promise((resolve) => { const img = new Image(); img.onload = () => resolve(img); img.src = `data:image/png;base64,${data}`; });
        const [i, j] = await Promise.all([load(x), load(y)]);
        if (i.width !== j.width || i.height !== j.height) return `size ${i.width}x${i.height} vs ${j.width}x${j.height}`;
        const read = (img) => { const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const g = c.getContext('2d'); g.drawImage(img, 0, 0); return g.getImageData(0, 0, img.width, img.height).data; };
        const p = read(i), q = read(j);
        let count = 0;
        for (let k = 0; k < p.length; k += 4) if (p[k] !== q[k] || p[k + 1] !== q[k + 1] || p[k + 2] !== q[k + 2] || p[k + 3] !== q[k + 3]) count++;
        return `${i.width}x${i.height} diff ${count}`;
    }, [a, b]);
    console.log(file, result);
}
await browser.close();
```
