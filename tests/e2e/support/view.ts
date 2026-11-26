import { randomUUID } from 'node:crypto';
import { marked, seedPhp } from './seed';

export interface ViewCanvas {
    id: number;
    name: string;
}

const prelude = (name: string): string =>
    `$c = App\\Models\\Canvas::factory()->create(['name' => '${name}']); $a = fn ($k) => App\\Models\\Area::where('key', $k)->value('id'); $m = fn ($k) => App\\Models\\Modality::where('key', $k)->value('id'); $e = fn ($area, $kind, $name, $parent = null, $extra = []) => App\\Models\\Element::create(array_merge(['canvas_id' => $c->id, 'area_id' => $a($area), 'kind' => $kind, 'name' => $name, 'parent_id' => $parent?->id], $extra));`;

export function seedViewCanvas(): ViewCanvas {
    const name = `Model ${randomUUID().slice(0, 8)}`;
    const code = `${prelude(name)} $s = $e('steering', 'domain', 'Strategie'); $e('steering', 'activity', 'Plannen', $s, ['modality_id' => $m('adaptive'), 'description' => 'Jaarplan opstellen', 'rationale' => 'Richting geven']); $p = $e('primary_activities', 'domain', 'Onderwijs'); $k = $e('primary_activities', 'cluster', 'Lessen', $p); $e('primary_activities', 'activity', 'Les geven', $k, ['modality_id' => $m('common')]); $e('supporting_activities', 'domain', 'Beheer', null, ['position' => 1]); App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c); $e('actors', 'domain', 'Leerlingen'); App\\Models\\Statement::create(['canvas_id' => $c->id, 'kind' => 'mission', 'body' => 'Leren voor het leven', 'position' => 1]); echo 'ID=' . $c->id;`;

    return { id: Number(marked(seedPhp(code), 'ID')), name };
}

export function seedBulkCanvas(domains: number, activitiesPerDomain: number): ViewCanvas {
    const name = `Groot ${randomUUID().slice(0, 8)}`;
    const code = `${prelude(name)} foreach (['primary_activities', 'supporting_activities'] as $area) { for ($i = 1; $i <= ${domains}; $i++) { $d = $e($area, 'domain', "Domein $i", null, ['position' => $i]); for ($j = 1; $j <= ${activitiesPerDomain}; $j++) { $e($area, 'activity', "Activiteit $i.$j", $d, ['position' => $j]); } } } echo 'ID=' . $c->id;`;

    return { id: Number(marked(seedPhp(code), 'ID')), name };
}

export function seedListCanvas(): ViewCanvas {
    const name = `Lijst ${randomUUID().slice(0, 8)}`;
    const code = `${prelude(name)} $g = $e('actors', 'domain', 'Klanten'); $e('actors', 'activity', 'Ouders', $g); $e('actors', 'activity', 'Kinderen', $g); App\\Models\\Statement::create(['canvas_id' => $c->id, 'kind' => 'goal', 'title' => 'Eén kindprofiel.', 'body' => 'Van opvang tot school', 'position' => 1]); echo 'ID=' . $c->id;`;

    return { id: Number(marked(seedPhp(code), 'ID')), name };
}
