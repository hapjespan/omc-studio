<?php

namespace Tests\Feature\Architecture;

use Illuminate\Database\Eloquent\Model;
use Tests\Support\SchemaRules;
use Tests\TestCase;

class FillableTest extends TestCase
{
    public function test_every_model_declares_fillable_and_none_unguards_everything(): void
    {
        $this->assertNotEmpty(SchemaRules::models());

        foreach (SchemaRules::models() as $class) {
            $this->assertFalse(SchemaRules::lacksFillable(new $class), "$class needs a non-empty fillable list");
        }
    }

    public function test_a_planted_model_without_fillable_or_with_empty_guarded_is_caught(): void
    {
        $bare = new class extends Model {};
        $unguarded = new class extends Model
        {
            protected $guarded = [];
        };

        $this->assertTrue(SchemaRules::lacksFillable($bare));
        $this->assertTrue(SchemaRules::lacksFillable($unguarded));
    }
}
