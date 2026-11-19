import { execFileSync } from 'node:child_process';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString();
}

export function seedOrganisation(name: string, years: number[] = [2026]): number {
    const create = years.map((year) => `App\\Models\\FiscalYear::factory()->create(['organisation_id' => $o->id, 'year' => ${year}]);`).join(' ');
    const output = tinker(`$o = App\\Models\\Organisation::factory()->create(['name' => '${name}']); ${create} echo 'ID=' . $o->id;`);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}

export function seedCostRecord(organisation: number, name: string, options: { code?: string; budgetCents?: number; year?: number; sets?: number } = {}): void {
    const code = options.code ? `, 'code' => '${options.code}'` : '';
    const figure = options.budgetCents === undefined ? '' : `$f = App\\Models\\FiscalYear::where('organisation_id', ${organisation})->where('year', ${options.year ?? 2026})->first(); App\\Models\\YearlyFigure::factory()->create(['cost_record_id' => $r->id, 'fiscal_year_id' => $f->id, 'budget_cents' => ${options.budgetCents}]);`;
    const sets = Array.from({ length: options.sets ?? 0 }, () => `App\\Models\\AllocationSet::factory()->create(['cost_record_id' => $r->id, 'fiscal_year_id' => $f->id]);`).join(' ');
    tinker(`$r = App\\Models\\CostRecord::factory()->create(['organisation_id' => ${organisation}, 'name' => '${name}'${code}]); ${figure} ${sets}`);
}

export interface AllocationSeed {
    organisation: number;
    canvas: number;
    records: Record<string, string>;
}

export function seedAllocation(name: string, records: Record<string, number>, structure: { domain: string; activities: string[] }[] = [{ domain: 'Inkoop', activities: ['Offreren', 'Bestellen', 'Betalen'] }]): AllocationSeed {
    const recordCode = Object.entries(records).map(([label, cents]) => `$r = App\\Models\\CostRecord::factory()->create(['organisation_id' => $o->id, 'owner_id' => $w->id, 'name' => '${label}']); App\\Models\\YearlyFigure::factory()->create(['cost_record_id' => $r->id, 'fiscal_year_id' => $f->id, 'budget_cents' => ${cents}]); $ids['${label}'] = $r->id;`).join(' ');
    const elementCode = structure.map(({ domain, activities }) => `$d = App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'domain', 'name' => '${domain}']); ${activities.map((activity) => `App\\Models\\Element::factory()->create(['canvas_id' => $c->id, 'area_id' => $a->id, 'kind' => 'activity', 'parent_id' => $d->id, 'name' => '${activity}']);`).join(' ')}`).join(' ');
    const output = tinker(`app(App\\Services\\Operations\\Steps\\ReferenceDataStep::class)(); $o = App\\Models\\Organisation::factory()->create(['name' => '${name}']); $f = App\\Models\\FiscalYear::factory()->create(['organisation_id' => $o->id, 'year' => 2026]); $w = App\\Models\\Owner::factory()->create(['organisation_id' => $o->id]); $c = App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id, 'name' => '${name} canvas']); $a = App\\Models\\Area::where('key', 'primary_activities')->first(); $ids = []; ${recordCode} ${elementCode} echo 'OUT=' . json_encode(['organisation' => $o->id, 'canvas' => $c->id, 'records' => $ids]);`);

    return JSON.parse(/OUT=(.*)/.exec(output)?.[1] ?? '{}') as AllocationSeed;
}
