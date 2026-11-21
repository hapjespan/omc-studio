import type { Page } from '@playwright/test';
import { test } from './support/guards';
import { seedPhp } from './support/seed';
import { seedPerformanceCanvas } from './support/overlay';
import { signInAs } from './support/shell';
import { percentile } from './support/percentile';
import { expectWithinBudget, sample } from './support/timing';

const LIST_VOLUME = 1000;
const FIRST_CHUNK = 60;

interface Reading {
    first: number;
    full: number;
    count: number;
}

interface Seed {
    canvas: number;
    organisation: number;
    record: string;
    baseline: string;
    hidden: string;
}

const TABLES = ['audit_entries', 'users', 'owners', 'applications', 'organisations'];

const number = (output: string, key: string): number => Number(new RegExp(`${key}=(\\d+)`).exec(output)?.[1]);

const count = (code: string): number => number(seedPhp(`echo 'N=' . (${code});`), 'N');

type Condition = readonly [selector: string, expected: number];

async function reachContent(page: Page, conditions: Condition[]): Promise<Reading> {
    await page.locator(conditions[0][0]).first().waitFor({ timeout: 20000 });

    return page.evaluate(
        ([wanted, chunk]) =>
            new Promise<{ first: number; full: number; count: number }>((resolve, reject) => {
                const limit = performance.now() + 20000;
                let first = -1;
                const check = (): void => {
                    const present = wanted.map(([target]) => document.querySelectorAll(target).length);
                    if (first < 0 && wanted.every(([, expected], index) => present[index] >= Math.min(chunk, expected))) first = performance.now();
                    if (wanted.every(([, expected], index) => present[index] >= expected)) return resolve({ first, full: performance.now(), count: present[0] });
                    if (performance.now() > limit) return reject(new Error(`${JSON.stringify(wanted)}: ${present.join(',')} after 20 s`));
                    requestAnimationFrame(check);
                };
                check();
            }),
        [conditions, FIRST_CHUNK] as const,
    );
}

async function measure(page: Page, label: string, browser: string, path: string, selector: string | Condition[], expected = 1): Promise<void> {
    const conditions: Condition[] = typeof selector === 'string' ? [[selector, expected]] : selector;
    const readings: Reading[] = [];
    const samples = await sample(async () => {
        await page.goto('about:blank');
        await page.goto(path, { waitUntil: 'commit' });
        const reading = await reachContent(page, conditions);
        readings.push(reading);

        return reading.full;
    });

    const counts = [...new Set(readings.map((reading) => reading.count))].join(',');
    const firsts = readings.map((reading) => Math.round(reading.first));
    console.log(`${label}: content ${counts} of ${conditions[0][1]} at resolve; first ${Math.min(FIRST_CHUNK, conditions[0][1])} elements median ${percentile(firsts, 50)} ms, p95 ${percentile(firsts, 95)} ms`);
    expectWithinBudget(samples, label, browser);
}

async function signInByForm(page: Page, email: string, password: string): Promise<void> {
    await page.goto('/sign-in');
    await page.getByLabel('E-mailadres').fill(email);
    await page.getByLabel('Wachtwoord').fill(password);
    await page.getByRole('button', { name: 'Inloggen' }).click();
    await page.waitForURL((url) => url.pathname !== '/sign-in', { timeout: 15000 });
}

test.describe('SC-020: every remaining screen answers within one second at 500 Elements and 1000 Cost records @performance', { annotation: { type: 'note', description: 'Run alone with one worker: npx playwright test tests/e2e/sc020-screens.spec.ts --workers=1' } }, () => {
    test.describe.configure({ mode: 'serial' });
    test.beforeEach(() => test.setTimeout(240_000));

    const seed: Seed = { canvas: 0, organisation: 0, record: '', baseline: '{}', hidden: '{}' };

    test.beforeAll(() => {
        seed.canvas = seedPerformanceCanvas();
        seed.organisation = count(`App\\Models\\Canvas::find(${seed.canvas})->organisation_id`);
        seed.hidden = seedPhp(`$db = Illuminate\\Support\\Facades\\DB::class; $canvases = $db::table('canvases')->where('id', '!=', ${seed.canvas})->whereNull('archived_at')->pluck('id')->all(); $organisations = $db::table('organisations')->where('id', '!=', ${seed.organisation})->whereNull('archived_at')->pluck('id')->all(); $db::table('canvases')->whereIn('id', $canvases)->update(['archived_at' => now()]); $db::table('organisations')->whereIn('id', $organisations)->update(['archived_at' => now()]); echo 'H=' . json_encode(['canvases' => $canvases, 'organisations' => $organisations]);`).replace(/^.*H=/s, '').trim();
        seed.record = seedPhp(`echo 'R=' . App\\Models\\CostRecord::where('organisation_id', ${seed.organisation})->orderBy('name')->value('id');`).replace(/^.*R=/s, '').trim();
        seed.baseline = seedPhp(`echo 'B=' . json_encode([${TABLES.map((table) => `'${table}' => (int) Illuminate\\Support\\Facades\\DB::table('${table}')->max('id')`).join(', ')}]);`).replace(/^.*B=/s, '').trim();
    });

    test.afterAll(() => {
        seedPhp(`$h = json_decode('${seed.hidden}', true); Illuminate\\Support\\Facades\\DB::table('canvases')->whereIn('id', $h['canvases'])->update(['archived_at' => null]); Illuminate\\Support\\Facades\\DB::table('organisations')->whereIn('id', $h['organisations'])->update(['archived_at' => null]);`);
        seedPhp(`$b = json_decode('${seed.baseline}', true); foreach (${JSON.stringify(TABLES)} as $table) { Illuminate\\Support\\Facades\\DB::table($table)->where('id', '>', $b[$table])->delete(); }`);
    });

    test('Overview shows every canvas card with its drawn miniature', async ({ page, browserName }) => {
        await signInAs(page, 'administrator');
        await measure(page, 'Overview', browserName, '/overview', `main article a[href="/canvases/${seed.canvas}"] svg rect`, 19);
    });

    test('Beheer start page', async ({ page, browserName }) => {
        await signInAs(page, 'administrator');
        await measure(page, 'Beheer', browserName, '/admin', 'main h1', 1);
    });

    test('Account page', async ({ page, browserName }) => {
        await signInAs(page, 'administrator');
        await measure(page, 'Account', browserName, '/account', 'main h1', 1);
    });

    test('Kostenposten importeren page', async ({ page, browserName }) => {
        await signInAs(page, 'administrator');
        await measure(page, 'Kostenposten importeren', browserName, `/organisations/${seed.organisation}/cost-records/import`, 'main input[type="file"]', 1);
    });

    test('Toewijzen with a Cost record selected over 500 targets', async ({ page, browserName }) => {
        await signInAs(page, 'administrator');
        await measure(page, 'Toewijzen with a selected record', browserName, `/canvases/${seed.canvas}/allocation?year=2026&cost_record=${seed.record}`, [['[data-testid="remaining"]', 1], ['[role="treeitem"]', 10]]);
    });

    test('Logboek with 1000 audit entries shows its first page', async ({ page, browserName }) => {
        seedPhp(`$now = now(); $rows = []; for ($i = 0; $i < ${LIST_VOLUME}; $i++) { $rows[] = ['occurred_at' => $now->copy()->subSeconds($i), 'actor_type' => 'user', 'actor_id' => null, 'action' => 'changed', 'subject_type' => 'element', 'subject_id' => (string) $i, 'subject_label' => 'Element ' . $i, 'organisation_id' => ${seed.organisation}, 'canvas_id' => ${seed.canvas}, 'changes' => json_encode(['name' => ['a', 'b']])]; } foreach (array_chunk($rows, 200) as $chunk) { DB::table('audit_entries')->insert($chunk); }`);
        await signInAs(page, 'administrator');
        await measure(page, 'Logboek', browserName, '/admin/audit-log', 'li.rounded-panel', 50);
    });

    test('Gebruikers with 1000 users', async ({ page, browserName }) => {
        seedPhp(`App\\Models\\User::factory()->count(${LIST_VOLUME})->create();`);
        await signInAs(page, 'administrator');
        await measure(page, 'Gebruikers', browserName, '/admin/users', 'tbody tr', count('App\\Models\\User::count()'));
    });

    test('Eigenaren with 1000 owners', async ({ page, browserName }) => {
        seedPhp(`App\\Models\\Owner::factory()->count(${LIST_VOLUME})->create(['organisation_id' => ${seed.organisation}]);`);
        await signInAs(page, 'administrator');
        await measure(page, 'Eigenaren', browserName, `/admin/organisations/${seed.organisation}/owners`, 'tbody tr', count(`App\\Models\\Owner::where('organisation_id', ${seed.organisation})->count()`));
    });

    test('Applicaties with 1000 applications', async ({ page, browserName }) => {
        seedPhp(`App\\Models\\Application::factory()->count(${LIST_VOLUME})->create(['organisation_id' => ${seed.organisation}]);`);
        await signInAs(page, 'administrator');
        await measure(page, 'Applicaties', browserName, `/admin/organisations/${seed.organisation}/applications`, 'tbody tr', count(`App\\Models\\Application::where('organisation_id', ${seed.organisation})->count()`));
    });

    test('Organisaties with 1000 organisations', async ({ page, browserName }) => {
        seedPhp(`App\\Models\\Organisation::factory()->count(${LIST_VOLUME})->create();`);
        await signInAs(page, 'administrator');
        await measure(page, 'Organisaties', browserName, '/admin/organisations', 'tbody tr', count('App\\Models\\Organisation::count()'));
    });

    test('Inloggen page', async ({ page, browserName }) => {
        await measure(page, 'Inloggen', browserName, '/sign-in', 'input[autocomplete="username"]');
    });

    test('Nieuw wachtwoord page', async ({ page, browserName }) => {
        const email = `new-${Date.now()}@example.test`;
        seedPhp(`App\\Models\\User::factory()->administrator()->create(['email' => '${email}', 'password' => 'one-time-pass-123', 'must_change_password' => true]);`);
        await signInByForm(page, email, 'one-time-pass-123');
        await measure(page, 'Nieuw wachtwoord', browserName, '/password/new', 'input[autocomplete="new-password"]');
    });

    test('Tweede factor page', async ({ page, browserName }) => {
        const email = `two-${Date.now()}@example.test`;
        seedPhp(`App\\Models\\User::factory()->administrator()->create(['email' => '${email}', 'password' => 'second-factor-pass-1'])->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();`);
        await signInByForm(page, email, 'second-factor-pass-1');
        await measure(page, 'Tweede factor', browserName, '/sign-in/second-factor', 'input[autocomplete="one-time-code"]');
    });

    test('Volledige export en import page and Voorbeeldgegevens page', async ({ page, browserName }) => {
        await signInAs(page, 'administrator');
        await measure(page, 'Volledige export en import', browserName, '/admin/exchange', 'main h1', 1);
        await measure(page, 'Voorbeeldgegevens', browserName, '/admin/sample-data', 'main h1', 1);
    });
});
