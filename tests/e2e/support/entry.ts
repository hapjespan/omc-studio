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

export interface AllocatedSeed {
    year: number;
    records: [name: string, budgetCents: number, lines: Record<string, number>][];
}

export function allocationCode({ year, records }: AllocatedSeed): string {
    const body = records
        .map(([name, budget, lines]) => `$r = App\\Models\\CostRecord::factory()->create(['organisation_id' => $c->organisation_id, 'owner_id' => $w->id, 'name' => '${name}']); App\\Models\\YearlyFigure::factory()->create(['cost_record_id' => $r->id, 'fiscal_year_id' => $f${year}->id, 'budget_cents' => ${budget}]); $s = App\\Models\\AllocationSet::create(['canvas_id' => $c->id, 'cost_record_id' => $r->id, 'fiscal_year_id' => $f${year}->id]); ${Object.entries(lines).map(([element, basisPoints], position) => `App\\Models\\AllocationLine::create(['allocation_set_id' => $s->id, 'element_id' => $e['${element}']->id, 'basis_points' => ${basisPoints}, 'position' => ${position}]);`).join(' ')}`)
        .join(' ');

    return `$w = $w ?? App\\Models\\Owner::factory()->create(['organisation_id' => $c->organisation_id]); $f${year} = App\\Models\\FiscalYear::firstOrCreate(['organisation_id' => $c->organisation_id, 'year' => ${year}]); ${body}`;
}
