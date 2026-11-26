import { seedPhp } from './seed';

export function createClosingNotice(canvasName: string, differenceCents: number): void {
    const code = `$c = App\\Models\\Canvas::factory()->create(['name' => '${canvasName}']); app(App\\Services\\Notices\\SystemNotices::class)->record('closing_check_failed', $c->id, ['year' => 2026, 'filter' => [], 'check' => 'budget', 'differenceCents' => ${differenceCents}]);`;
    seedPhp(code);
}
