import { seedOverlay, type OverlaySeed } from './overlay';
import { marked, seedPhp } from './seed';

export interface SnapshotSeed extends OverlaySeed {
    snapshot: number;
}

export function seedSnapshot(name: string): SnapshotSeed {
    const seed = seedOverlay(name);
    const output = seedPhp(`auth()->login(App\\Models\\User::factory()->create(['role' => 'administrator'])); $c = App\\Models\\Canvas::find(${seed.canvas}); $f = App\\Models\\FiscalYear::where('organisation_id', $c->organisation_id)->where('year', 2026)->first(); $s = app(App\\Services\\Snapshot\\Snapshots::class)->take($c, $f, 'Stand voor de stuurgroep'); echo 'ID=' . $s->id;`);

    return { ...seed, snapshot: Number(marked(output, 'ID')) };
}

export function changeLiveCanvas(canvas: number): void {
    seedPhp(`$d = App\\Models\\Element::where('canvas_id', ${canvas})->where('name', 'Onderwijs')->first(); $d->update(['name' => 'Hernoemd']); App\\Models\\Element::where('canvas_id', ${canvas})->where('name', 'Toetsen')->update(['archived_at' => now()]); App\\Models\\AllocationLine::whereIn('allocation_set_id', App\\Models\\AllocationSet::where('canvas_id', ${canvas})->pluck('id'))->delete();`);
}

export function damageSnapshot(snapshot: number): void {
    seedPhp(`$q = Illuminate\\Support\\Facades\\DB::table('snapshots')->where('id', ${snapshot}); $q->update(['content' => str_replace('"budget":100000', '"budget":999999', $q->value('content'))]);`);
}
