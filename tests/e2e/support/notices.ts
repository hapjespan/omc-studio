import { execFileSync } from 'node:child_process';

const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: process.env.E2E_DATABASE ?? `${process.cwd()}/.scratch/e2e.sqlite`, DB_URL: '' };

export function createClosingNotice(canvasName: string, differenceCents: number): void {
    const code = `$c = App\\Models\\Canvas::factory()->create(['name' => '${canvasName}']); app(App\\Services\\Notices\\SystemNotices::class)->record('closing_check_failed', $c->id, ['year' => 2026, 'filter' => [], 'check' => 'budget', 'differenceCents' => ${differenceCents}]);`;
    execFileSync('php', ['artisan', 'tinker', `--execute=${code}`], { env });
}
