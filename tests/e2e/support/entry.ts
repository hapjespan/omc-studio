import type { Page } from '@playwright/test';
import { tinker } from './overview';
import { signInAs } from './shell';

export type Seed = [kind: 'domain' | 'cluster' | 'activity', name: string, parent?: string];

export function seedEntryCanvas(area: string, seed: Seed[] = [], extra = ''): number {
    const rows = seed
        .map(([kind, name, parent], index) => `$e['${name}'] = App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => '${kind}', 'name' => '${name}', 'parent_id' => ${parent ? `$e['${parent}']->id` : 'null'}, 'position' => ${index}]);`)
        .join(' ');
    const output = tinker(`app(App\\Services\\Operations\\InstallSteps::class)->run(); $c = App\\Models\\Canvas::factory()->create(); $a = App\\Models\\Area::where('key', '${area}')->firstOrFail(); $e = []; ${rows} ${extra} echo 'ID=' . $c->id;`);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

export async function openEntry(page: Page, canvas: number, area = 'Primaire activiteiten'): Promise<void> {
    await signInAs(page, 'administrator');
    await page.goto(`/canvases/${canvas}/entry`);
    await page.getByRole('navigation', { name: 'Areas' }).getByRole('button', { name: new RegExp(area) }).click();
}
