import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export interface ViewCanvas {
    id: number;
    name: string;
}

const run = (code: string): string => {
    for (let attempt = 1; ; attempt++) {
        try {
            return execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env, maxBuffer: 64 * 1024 * 1024 }).toString();
        } catch (error) {
            if (attempt >= 3) throw error;
        }
    }
};

const prelude = (name: string): string =>
    `$c = App\\Models\\Canvas::factory()->create(['name' => '${name}']); $a = fn ($k) => App\\Models\\Area::where('key', $k)->value('id'); $m = fn ($k) => App\\Models\\Modality::where('key', $k)->value('id'); $e = fn ($area, $kind, $name, $parent = null, $extra = []) => App\\Models\\Element::create(array_merge(['canvas_id' => $c->id, 'area_id' => $a($area), 'kind' => $kind, 'name' => $name, 'parent_id' => $parent?->id], $extra));`;

export function seedViewCanvas(): ViewCanvas {
    const name = `Model ${randomUUID().slice(0, 8)}`;
    const code = `${prelude(name)} $s = $e('steering', 'domain', 'Strategie'); $e('steering', 'activity', 'Plannen', $s, ['modality_id' => $m('adaptive'), 'description' => 'Jaarplan opstellen', 'rationale' => 'Richting geven']); $p = $e('primary_activities', 'domain', 'Onderwijs'); $k = $e('primary_activities', 'cluster', 'Lessen', $p); $e('primary_activities', 'activity', 'Les geven', $k, ['modality_id' => $m('common')]); $e('supporting_activities', 'domain', 'Beheer', null, ['position' => 1]); App\\Services\\Canvas\\UnknownAllocationDomain::ensure($c); $e('actors', 'domain', 'Leerlingen'); App\\Models\\Statement::create(['canvas_id' => $c->id, 'kind' => 'mission', 'body' => 'Leren voor het leven', 'position' => 1]); echo 'ID=' . $c->id;`;

    return { id: Number(/ID=(\d+)/.exec(run(code))?.[1]), name };
}

export function seedBulkCanvas(domains: number, activitiesPerDomain: number): ViewCanvas {
    const name = `Groot ${randomUUID().slice(0, 8)}`;
    const code = `${prelude(name)} foreach (['primary_activities', 'supporting_activities'] as $area) { for ($i = 1; $i <= ${domains}; $i++) { $d = $e($area, 'domain', "Domein $i", null, ['position' => $i]); for ($j = 1; $j <= ${activitiesPerDomain}; $j++) { $e($area, 'activity', "Activiteit $i.$j", $d, ['position' => $j]); } } } echo 'ID=' . $c->id;`;

    return { id: Number(/ID=(\d+)/.exec(run(code))?.[1]), name };
}

export function seedListCanvas(): ViewCanvas {
    const name = `Lijst ${randomUUID().slice(0, 8)}`;
    const code = `${prelude(name)} $g = $e('actors', 'domain', 'Klanten'); $e('actors', 'activity', 'Ouders', $g); $e('actors', 'activity', 'Kinderen', $g); App\\Models\\Statement::create(['canvas_id' => $c->id, 'kind' => 'goal', 'title' => 'Eén kindprofiel.', 'body' => 'Van opvang tot school', 'position' => 1]); echo 'ID=' . $c->id;`;

    return { id: Number(/ID=(\d+)/.exec(run(code))?.[1]), name };
}
