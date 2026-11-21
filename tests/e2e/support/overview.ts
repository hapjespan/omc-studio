import { randomUUID } from 'node:crypto';
import { seedPhp } from './seed';

export const unique = (prefix: string): string => `${prefix} ${randomUUID().slice(0, 8)}`;

export function seedOrganisation(name: string, canvases: string[] = [], options: { archivedCanvases?: string[] } = {}): number {
    const make = (list: string[], extra: string): string => list.map((canvas) => `App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id, 'name' => '${canvas}'${extra}]);`).join(' ');
    const output = seedPhp(`$o = App\\Models\\Organisation::factory()->create(['name' => '${name}']); ${make(canvases, '')} ${make(options.archivedCanvases ?? [], ", 'archived_at' => now()")} echo 'ID=' . $o->id;`);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}
