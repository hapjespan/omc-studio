import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { expect, test } from './support/guards';
import { signInAs } from './support/shell';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

interface Seeded {
    canvas: number;
    domain: string;
    activity: string;
}

function seedManualCanvas(): Seeded {
    const name = `Opmaak ${randomUUID().slice(0, 8)}`;
    const code = [
        `$c = App\\Models\\Canvas::factory()->create(['name' => '${name}']);`,
        `$a = App\\Models\\Area::where('key', 'primary_activities')->value('id');`,
        `$d = App\\Models\\Element::create(['canvas_id' => $c->id, 'area_id' => $a, 'kind' => 'domain', 'name' => 'Onderwijs']);`,
        `$k = App\\Models\\Element::create(['canvas_id' => $c->id, 'area_id' => $a, 'kind' => 'activity', 'name' => 'Les geven', 'parent_id' => $d->id]);`,
        `App\\Models\\Layout::create(['canvas_id' => $c->id, 'area_id' => $a, 'x' => 560, 'y' => 400, 'width' => 1100, 'height' => 380, 'z' => 0, 'style' => []]);`,
        `App\\Models\\Layout::create(['canvas_id' => $c->id, 'element_id' => $d->id, 'x' => 20, 'y' => 60, 'width' => 400, 'height' => 150, 'z' => 1, 'style' => []]);`,
        `App\\Models\\Layout::create(['canvas_id' => $c->id, 'element_id' => $k->id, 'x' => 16, 'y' => 40, 'width' => 200, 'height' => 50, 'z' => 2, 'style' => []]);`,
        `echo 'SEED=' . $c->id . ',' . $d->id . ',' . $k->id;`,
    ].join(' ');
    const output = execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString();
    const [canvas, domain, activity] = /SEED=(\d+),(\S+),(\S+)/.exec(output)!.slice(1);

    return { canvas: Number(canvas), domain, activity };
}

for (const role of ['administrator', 'reader'] as const) {
    test(`a ${role} sees every block at its stored place within 1 pixel at 100% zoom`, async ({ page }) => {
        const seeded = seedManualCanvas();
        await signInAs(page, role);
        await page.goto(`/canvases/${seeded.canvas}/view`);

        const origin = await page.locator('[data-artboard]').boundingBox();
        const boxOf = async (id: string) => {
            const box = await page.locator(`[data-block="${id}"]`).boundingBox();
            return { x: box!.x - origin!.x, y: box!.y - origin!.y, width: box!.width };
        };
        const near = (actual: number, expected: number) => expect(Math.abs(actual - expected)).toBeLessThanOrEqual(1);

        const area = await boxOf('primary_activities');
        const domain = await boxOf(seeded.domain);
        const activity = await boxOf(seeded.activity);

        near(area.x, 560);
        near(area.y, 400);
        near(area.width, 1100);
        near(domain.x, 580);
        near(domain.y, 460);
        near(domain.width, 400);
        near(activity.x, 596);
        near(activity.y, 500);
        near(activity.width, 200);
        expect(await page.locator('[data-artboard]').evaluate((node) => getComputedStyle(node).transform)).toBe('matrix(1, 0, 0, 1, 0, 0)');
    });
}

for (const role of ['administrator', 'reader'] as const) {
    test(`the overflow hint for Tekenen shows to Administrators only (${role})`, async ({ page }) => {
        const seeded = seedManualCanvas();
        execFileSync('php', ['artisan', 'tinker', `--execute=App\\Models\\Layout::where('canvas_id', ${seeded.canvas})->where('element_id', '${seeded.domain}')->update(['y' => 1100, 'height' => 400]);`], { env });

        await signInAs(page, role);
        await page.goto(`/canvases/${seeded.canvas}/view`);
        await expect(page.locator('[data-overflow-notice]')).toHaveText(/^Niet alles past op het A3-tekenvlak: \d+ blokken vallen erbuiten$/);
        await expect(page.locator('[data-overflow-hint]')).toHaveCount(role === 'administrator' ? 1 : 0);
        if (role === 'administrator') await expect(page.locator('[data-overflow-hint]')).toHaveText('Maak blokken kleiner of verplaats ze in Tekenen');
    });
}
