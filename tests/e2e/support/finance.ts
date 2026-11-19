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
