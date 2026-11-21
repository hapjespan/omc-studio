<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Services\Allocation\CarryOver;
use App\Services\Audit\Audited;
use App\Services\Audit\AuditTrail;
use FilesystemIterator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Feature\Allocation\AllocationTestCase;

class AuditLabelsTest extends AllocationTestCase
{
    /** @var array<string, string> */
    private array $messages = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->messages = json_decode((string) file_get_contents(base_path('lang/nl.json')), true);
    }

    /** @return list<class-string<Model>> */
    private function auditedModels(): array
    {
        $models = [];

        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (in_array(Audited::class, class_uses($class) ?: [], true)) {
                $models[] = $class;
            }
        }

        return $models;
    }

    public function test_every_written_subject_type_action_and_field_has_a_label(): void
    {
        $technical = ['created_at', 'updated_at', 'structure_version', 'layout_version', 'layout_saved_at', 'name_normalised', 'scope_key', 'password', 'two_factor_secret', 'two_factor_last_time_step', 'remember_token', 'code_normalised'];
        $subjects = ['area'];
        $fields = ['lines', 'sessions', 'place', 'size', 'style'];

        foreach ($this->auditedModels() as $class) {
            $model = new $class;
            $subjects[] = app(AuditTrail::class)->kind($model);
            $fields = [...$fields, ...array_diff(Schema::getColumnListing($model->getTable()), $technical, $model->getHidden(), [$model->getKeyName()])];
        }

        $actions = ['created', 'changed', 'deleted'];
        $source = '';

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
            $source .= file_get_contents($file->getPathname());
        }

        preg_match_all("/during\\('([a-z_]+)'|trail->record\\([^;]*?, '([a-z_]+)'|const ACTION = '([a-z_]+)'/", $source, $found);
        $actions = [...$actions, ...array_filter([...$found[1], ...$found[2], ...$found[3]]), 'password_changed', 'second_factor_changed'];

        $missing = [];

        foreach (['kind' => $subjects, 'action' => $actions, 'field' => $fields] as $group => $keys) {
            foreach (array_unique($keys) as $key) {
                isset($this->messages["audit_log.$group.$key"]) || $missing[] = "audit_log.$group.$key";
            }
        }

        sort($missing);
        $this->assertSame([], $missing);
    }

    public function test_the_kind_filter_offers_every_subject_type_and_filters_on_it(): void
    {
        $this->actingAs($this->admin());
        $this->costRecord('Licentie', 2026);

        $this->get('/admin/audit-log')->assertInertia(fn (Assert $page) => $page
            ->where('options.kinds', fn (mixed $kinds) => array_diff(['cost_record', 'allocation_set', 'finalised_year', 'yearly_figure', 'snapshot', 'fiscal_year', 'owner', 'application'], $kinds instanceof Collection ? $kinds->all() : (array) $kinds) === []));

        $response = $this->get('/admin/audit-log?subject_type=cost_record');

        $response->assertInertia(fn (Assert $page) => $page->where('filterErrors', [])
            ->where('entries', fn ($entries) => $entries->count() === AuditEntry::where('subject_type', 'cost_record')->count() && $entries->count() > 0));
    }

    public function test_every_kind_label_has_a_filter_option_and_the_other_way_round(): void
    {
        $this->actingAs($this->admin());
        $labelled = collect(array_keys($this->messages))
            ->filter(fn (string $key) => str_starts_with($key, 'audit_log.kind.'))
            ->map(fn (string $key) => substr($key, strlen('audit_log.kind.')))
            ->sort()->values()->all();

        $this->get('/admin/audit-log')->assertInertia(fn (Assert $page) => $page
            ->where('options.kinds', fn (mixed $kinds) => collect($kinds instanceof Collection ? $kinds->all() : (array) $kinds)->sort()->values()->all() === $labelled));
    }

    public function test_finalise_and_carry_over_are_recorded_under_their_own_actions(): void
    {
        $admin = $this->admin();
        $record = $this->costRecord('Licentie', 2026);
        $this->allocate($record, 2026, [$this->target('Doel')->id => 10000]);
        $this->actingAs($admin);
        $before = (int) AuditEntry::max('id');

        $this->postJson("/canvases/{$this->canvas->id}/years/2026/finalise")->assertRedirect();
        app(CarryOver::class)->run($this->canvas, $this->fiscalYear(2026), $this->fiscalYear(2027), true);

        $actions = AuditEntry::where('id', '>', $before)->orderBy('id')->get(['subject_type', 'action'])->map(fn ($entry) => $entry->subject_type.':'.$entry->action)->all();

        $this->assertContains('finalised_year:finalised', $actions);
        $this->assertContains('allocation_set:carried_over', $actions);
        $this->assertContains('yearly_figure:carried_over', $actions);
        $this->assertNotContains('finalised_year:created', $actions);
        $this->assertNotContains('allocation_set:created', $actions);
    }

    public function test_redistribution_is_recorded_under_its_own_action(): void
    {
        $domain = $this->domain();
        $first = $this->activity($domain, 'A1');
        $target = $this->target('Doel');
        $this->allocate($this->costRecord('Licentie', 2025), 2025, [$first->id => 5000, $target->id => 5000]);
        $before = (int) AuditEntry::max('id');

        $this->actingAs($this->admin())
            ->postJson("/canvases/{$this->canvas->id}/elements/{$domain->id}/redistribution", ['choices' => ['2025' => ['mode' => 'move', 'target' => (string) $target->id]]])
            ->assertRedirect();

        $entries = AuditEntry::where('id', '>', $before)->where('subject_type', 'allocation_set')->pluck('action')->all();

        $this->assertSame(['redistributed'], $entries);
        $this->assertNull(AuditEntry::where('id', '>', $before)->where('subject_type', 'element')->where('action', 'redistributed')->first());
    }
}
