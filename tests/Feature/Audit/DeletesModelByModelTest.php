<?php

namespace Tests\Feature\Audit;

use App\Enums\ElementKind;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Audit\DeletesModelByModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DeletesModelByModelTest extends TestCase
{
    use RefreshDatabase;

    private Element $domain;

    private Element $cluster;

    private Element $activity;

    protected function setUp(): void
    {
        parent::setUp();

        $canvas = Canvas::factory()->create();
        $this->domain = Element::factory()->create(['canvas_id' => $canvas->id, 'kind' => ElementKind::Domain]);
        $this->cluster = Element::factory()->create(['canvas_id' => $canvas->id, 'parent_id' => $this->domain->id, 'kind' => ElementKind::Cluster]);
        $this->activity = Element::factory()->create(['canvas_id' => $canvas->id, 'parent_id' => $this->cluster->id, 'kind' => ElementKind::Activity]);
        Element::factory()->create(['canvas_id' => $canvas->id, 'kind' => ElementKind::Domain]);

        DB::table('audit_entries')->delete();
    }

    public function test_a_subtree_is_deleted_children_first_with_one_entry_per_row(): void
    {
        DB::transaction(fn () => app(DeletesModelByModel::class)->delete($this->domain));

        $entries = AuditEntry::query()->orderBy('id')->get();

        $this->assertSame([$this->activity->id, $this->cluster->id, $this->domain->id], $entries->pluck('subject_id')->all());
        $this->assertSame(['deleted'], $entries->pluck('action')->unique()->all());
        $this->assertSame(1, Element::query()->count());
    }

    public function test_the_deletion_rolls_back_with_the_caller_transaction(): void
    {
        try {
            DB::transaction(function () {
                app(DeletesModelByModel::class)->delete($this->domain);

                throw new RuntimeException('stop');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(4, Element::query()->count());
        $this->assertSame(0, AuditEntry::query()->count());
    }
}
