<?php

namespace Tests\Unit\Lists;

use App\Models\Organisation;
use App\Services\Lists\ListQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ListQueryTest extends TestCase
{
    use RefreshDatabase;

    private function list(): ListQuery
    {
        return new ListQuery(['name', 'slug'], ['name', 'created_at'], ['slug' => 'slug'], 'archived_at');
    }

    private function seedOrganisations(): void
    {
        Organisation::create(['name' => 'Alpha Zorg']);
        Organisation::create(['name' => 'beta zorg']);
        Organisation::create(['name' => 'Gamma']);
        Organisation::create(['name' => 'Oud', 'archived_at' => now()]);
    }

    public function test_search_ignores_case_and_returns_the_count(): void
    {
        $this->seedOrganisations();

        $result = $this->list()->run(Organisation::query(), ['search' => 'ZORG']);

        $this->assertSame(2, $result['count']);
        $this->assertSame(['Alpha Zorg', 'beta zorg'], array_column($result['rows'], 'name'));
    }

    public function test_search_treats_wildcards_literally(): void
    {
        $this->seedOrganisations();

        $this->assertSame(0, $this->list()->run(Organisation::query(), ['search' => '%'])['count']);
    }

    public function test_sorts_ascending_and_descending_ignoring_case(): void
    {
        $this->seedOrganisations();

        $asc = $this->list()->run(Organisation::query(), ['sort' => 'name']);
        $desc = $this->list()->run(Organisation::query(), ['sort' => 'name', 'direction' => 'desc']);

        $this->assertSame(['Alpha Zorg', 'beta zorg', 'Gamma'], array_column($asc['rows'], 'name'));
        $this->assertSame(['Gamma', 'beta zorg', 'Alpha Zorg'], array_column($desc['rows'], 'name'));
    }

    public function test_non_text_columns_sort_by_their_own_order(): void
    {
        $this->seedOrganisations();
        Organisation::query()->where('name', 'Gamma')->update(['created_at' => now()->subYear()]);

        $asc = $this->list()->run(Organisation::query(), ['sort' => 'created_at']);

        $this->assertSame('Gamma', $asc['rows'][0]['name']);
    }

    public function test_list_input_is_refused_not_a_server_error(): void
    {
        $this->expectException(ValidationException::class);

        try {
            $this->list()->run(Organisation::query(), ['search' => ['a']]);
        } catch (ValidationException $refusal) {
            $this->assertArrayHasKey('query', $refusal->errors());
            throw $refusal;
        }
    }

    public function test_unknown_sort_columns_are_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->list()->run(Organisation::query(), ['sort' => 'password']);
    }

    public function test_filters_narrow_the_rows_and_unknown_filters_are_refused(): void
    {
        $this->seedOrganisations();

        $result = $this->list()->run(Organisation::query(), ['filters' => ['slug' => 'gamma']]);

        $this->assertSame(['Gamma'], array_column($result['rows'], 'name'));

        $this->expectException(ValidationException::class);
        $this->list()->run(Organisation::query(), ['filters' => ['id' => '1']]);
    }

    public function test_archived_rows_are_hidden_unless_asked_for(): void
    {
        $this->seedOrganisations();

        $this->assertSame(3, $this->list()->run(Organisation::query(), [])['count']);
        $this->assertSame(4, $this->list()->run(Organisation::query(), ['archived' => '1'])['count']);
    }

    public function test_the_response_echoes_the_applied_query(): void
    {
        $result = $this->list()->run(Organisation::query(), ['search' => 'x', 'sort' => 'name', 'direction' => 'desc', 'filters' => ['slug' => 'a'], 'archived' => '1']);

        $this->assertSame(['search' => 'x', 'sort' => 'name', 'direction' => 'desc', 'filters' => ['slug' => 'a'], 'archived' => true], $result['query']);
    }
}
