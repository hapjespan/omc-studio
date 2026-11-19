import { expect } from '@playwright/test';
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

export function stubEntry(page: import('@playwright/test').Page): { next(): Promise<void> } {
    const targets: string[] = [];
    let consumed = 0;
    void page.route(
        (url) => url.pathname === '/canvases',
        async (route) => {
            if (route.request().method() !== 'POST') return route.continue();
            const response = await route.fetch();
            if (!/\/canvases\/\d+\/entry$/.test(response.url())) return route.fulfill({ response });
            targets.push(response.url());
            const overview = await route.fetch({ url: new URL('/overview', response.url()).toString(), method: 'GET', postData: null });
            return route.fulfill({ response: overview });
        },
    );

    return {
        async next() {
            consumed += 1;
            await expect.poll(() => targets.length).toBeGreaterThanOrEqual(consumed);
        },
    };
}
