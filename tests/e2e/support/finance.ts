import type { Page } from '@playwright/test';
import { marked, seedPhp } from './seed';

export function seedOrganisation(name: string, years: number[] = [2026]): number {
    const create = years.map((year) => `App\\Models\\FiscalYear::factory()->create(['organisation_id' => $o->id, 'year' => ${year}]);`).join(' ');
    const output = seedPhp(`$o = App\\Models\\Organisation::factory()->create(['name' => '${name}']); ${create} echo 'ID=' . $o->id;`);

    return Number(marked(output, 'ID'));
}

export function seedCostRecord(organisation: number, name: string, options: { code?: string; budgetCents?: number; year?: number; sets?: number } = {}): void {
    const code = options.code ? `, 'code' => '${options.code}'` : '';
    const figure = options.budgetCents === undefined ? '' : `$f = App\\Models\\FiscalYear::where('organisation_id', ${organisation})->where('year', ${options.year ?? 2026})->first(); App\\Models\\YearlyFigure::factory()->create(['cost_record_id' => $r->id, 'fiscal_year_id' => $f->id, 'budget_cents' => ${options.budgetCents}]);`;
    const sets = Array.from({ length: options.sets ?? 0 }, () => `App\\Models\\AllocationSet::factory()->create(['cost_record_id' => $r->id, 'fiscal_year_id' => $f->id]);`).join(' ');
    seedPhp(`$r = App\\Models\\CostRecord::factory()->create(['organisation_id' => ${organisation}, 'name' => '${name}'${code}]); ${figure} ${sets}`);
}

export interface AllocationSeed {
    organisation: number;
    canvas: number;
    records: Record<string, string>;
}

export function seedAllocation(name: string, records: Record<string, number>, structure: { domain: string; activities: string[] }[] = [{ domain: 'Inkoop', activities: ['Offreren', 'Bestellen', 'Betalen'] }]): AllocationSeed {
    const recordCode = Object.entries(records).map(([label, cents]) => `$r = App\\Models\\CostRecord::factory()->create(['organisation_id' => $o->id, 'owner_id' => $w->id, 'name' => '${label}']); App\\Models\\YearlyFigure::factory()->create(['cost_record_id' => $r->id, 'fiscal_year_id' => $f->id, 'budget_cents' => ${cents}]); $ids['${label}'] = $r->id;`).join(' ');
    const elementCode = structure.map(({ domain, activities }) => `$d = App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'domain', 'name' => '${domain}']); ${activities.map((activity, index) => `App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'activity', 'parent_id' => $d->id, 'position' => ${index}, 'name' => '${activity}']);`).join(' ')}`).join(' ');
    const output = seedPhp(`app(App\\Services\\Operations\\Steps\\ReferenceDataStep::class)(); $o = App\\Models\\Organisation::factory()->create(['name' => '${name}']); $f = App\\Models\\FiscalYear::factory()->create(['organisation_id' => $o->id, 'year' => 2026]); $w = App\\Models\\Owner::factory()->create(['organisation_id' => $o->id]); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id, 'name' => '${name} canvas']); App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c); $a = App\\Models\\Area::where('key', 'primary_activities')->first(); $ids = []; ${recordCode} ${elementCode} echo 'OUT=' . json_encode(['organisation' => $o->id, 'canvas' => $c->id, 'records' => $ids]);`);

    return JSON.parse(marked(output, 'OUT')) as AllocationSeed;
}

export async function expandTarget(page: Page, name: string): Promise<void> {
    await page.getByRole('region', { name: 'Doelen op het canvas' }).waitFor();
    const button = page.getByRole('button', { name: `Uitklappen: ${name}` });
    if ((await button.count()) > 0) await button.click();
}
