<?php

namespace Tests\Feature\Lists;

use App\Models\Organisation;
use App\Services\Lists\BulkResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BulkResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_row_follows_its_own_rule_and_skipped_rows_name_the_reason(): void
    {
        $rows = collect(['Alpha', 'Blocked', 'Gamma'])->map(fn (string $name) => Organisation::create(['name' => $name]));

        $result = BulkResult::apply($rows, function (Organisation $row) {
            if ($row->name === 'Blocked') {
                throw ValidationException::withMessages(['rule' => 'Deze organisatie is gearchiveerd']);
            }

            $row->delete();
        });

        $this->assertSame(2, $result->doneCount());
        $this->assertSame([['name' => 'Blocked', 'reason' => 'Deze organisatie is gearchiveerd']], $result->skipped());
        $this->assertSame(['Blocked'], Organisation::pluck('name')->all());
    }

    public function test_the_array_form_carries_the_counts_and_the_ids_of_the_rows_done(): void
    {
        $rows = collect(['Alpha', 'Beta'])->map(fn (string $name) => Organisation::create(['name' => $name]));

        $array = BulkResult::apply($rows, fn (Organisation $row) => $row->delete())->toArray();

        $this->assertSame(2, $array['done']);
        $this->assertSame($rows->map->getKey()->all(), $array['done_ids']);
        $this->assertSame([], $array['skipped']);
    }

    public function test_a_reason_falls_back_to_the_first_validation_message(): void
    {
        $row = Organisation::create(['name' => 'Alpha']);

        $result = BulkResult::apply(collect([$row]), fn () => throw ValidationException::withMessages(['name' => 'Naam is bezet']));

        $this->assertSame('Naam is bezet', $result->skipped()[0]['reason']);
    }
}
