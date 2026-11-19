import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env }).toString();
}

export const unique = (prefix: string): string => `${prefix} ${randomUUID().slice(0, 8)}`;

export function seedOrganisation(name: string, canvases: string[] = [], options: { archivedCanvases?: string[] } = {}): number {
    const make = (list: string[], extra: string): string => list.map((canvas) => `App\\Models\\Canvas::factory()->create(['organisation_id' => $o->id, 'name' => '${canvas}'${extra}]);`).join(' ');
    const output = tinker(`$o = App\\Models\\Organisation::factory()->create(['name' => '${name}']); ${make(canvases, '')} ${make(options.archivedCanvases ?? [], ", 'archived_at' => now()")} echo 'ID=' . $o->id;`);

    return Number(/ID=(\d+)/.exec(output)?.[1]);
}
