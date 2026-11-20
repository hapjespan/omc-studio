import { tinker } from './finance';

export interface OverlaySeed {
    organisation: number;
    canvas: number;
    domain: string;
    records: Record<string, string>;
}

const set = (record: string, element: string): string =>
    `$s = App\\Models\\AllocationSet::factory()->create(['canvas_id' => $c->id, 'cost_record_id' => $r['${record}']->id, 'fiscal_year_id' => $f->id]); App\\Models\\AllocationLine::factory()->create(['allocation_set_id' => $s->id, 'element_id' => ${element}->id, 'basis_points' => 10000]);`;

export function seedOverlay(name: string): OverlaySeed {
    const record = (label: string, budget: number, forecast: string, extra: string): string =>
        `$r['${label}'] = App\\Models\\CostRecord::factory()->create(['organisation_id' => $o->id, 'owner_id' => $w->id, 'name' => '${label}', ${extra}]); App\\Models\\YearlyFigure::factory()->create(['cost_record_id' => $r['${label}']->id, 'fiscal_year_id' => $f->id, 'budget_cents' => ${budget}, 'forecast_cents' => ${forecast}, 'fte_hundredths' => 150]);`;
    const element = (variable: string, area: string, kind: string, label: string, parent: string, modality: string): string =>
        `${variable} = App\\Models\\Element::create(['canvas_id' => $c->id, 'area_id' => $a('${area}'), 'kind' => '${kind}', 'name' => '${label}', 'parent_id' => ${parent}, 'modality_id' => ${modality}]);`;
    const code = [
        'app(App\\Services\\Operations\\Steps\\ReferenceDataStep::class)();',
        `$o = App\\Models\\Organisation::factory()->create(['name' => '${name}']);`,
        "$f = App\\Models\\FiscalYear::factory()->create(['organisation_id' => $o->id, 'year' => 2026]);",
        "$w = App\\Models\\Owner::factory()->create(['organisation_id' => $o->id, 'name' => 'Afdeling ICT']);",
        "$x = App\\Models\\Owner::factory()->create(['organisation_id' => $o->id, 'name' => 'Afdeling HR']);",
        `$c = App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id, 'name' => '${name} canvas']);`,
        'App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c);',
        "$a = fn ($k) => App\\Models\\Area::where('key', $k)->value('id'); $m = fn ($k) => App\\Models\\Modality::where('key', $k)->value('id'); $r = [];",
        element('$d', 'primary_activities', 'domain', 'Onderwijs', 'null', 'null'),
        element('$k', 'primary_activities', 'cluster', 'Lessen', '$d->id', "$m('common')"),
        element('$l', 'primary_activities', 'activity', 'Les geven', '$k->id', "$m('common')"),
        element('$n', 'primary_activities', 'activity', 'Toetsen', '$d->id', "$m('adaptive')"),
        element('$p', 'primary_activities', 'activity', 'Begeleiden', '$d->id', 'null'),
        element('$q', 'primary_activities', 'activity', 'Plannen', '$d->id', 'null'),
        element('$e', 'steering', 'domain', 'Strategie', 'null', 'null'),
        element('$u', 'steering', 'activity', 'Bijsturen', '$e->id', 'null'),
        record('Salarissen', 100000, '90000', `'cost_kind' => 'personnel'`),
        record('Licentie', 300000, 'null', `'cost_kind' => 'supplier_contract', 'owner_id' => $x->id`),
        set('Salarissen', '$d'),
        "echo 'OUT=' . json_encode(['organisation' => $o->id, 'canvas' => $c->id, 'domain' => 'Onderwijs', 'records' => ['Salarissen' => $r['Salarissen']->id, 'Licentie' => $r['Licentie']->id]]);",
    ].join(' ');

    return JSON.parse(/OUT=(.*)/.exec(tinker(code))?.[1] ?? '{}') as OverlaySeed;
}

export function seedPerformanceCanvas(): number {
    const output = tinker("if (! App\\Models\\Modality::exists()) { app(App\\Services\\Operations\\Steps\\ReferenceDataStep::class)(); } $d = (new Database\\Factories\\PerformanceDatasetFactory(500, 1000, 2026, random_int(1, 2000000000)))->create(); echo 'ID=' . $d['canvas']->id;");

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}
