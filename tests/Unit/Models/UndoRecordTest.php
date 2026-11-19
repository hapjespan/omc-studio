<?php

namespace Tests\Unit\Models;

use App\Models\RecoveryCode;
use App\Models\UndoRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UndoRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_undo_record_holds_token_user_expiry_and_payload(): void
    {
        $user = User::factory()->create();

        UndoRecord::create([
            'token' => 'abc123',
            'user_id' => $user->id,
            'kind' => 'element_deletion',
            'payload' => ['elements' => [['id' => 'x', 'name' => 'Intake']]],
            'expires_at' => now()->addMinute(),
        ]);

        $record = UndoRecord::find('abc123');

        $this->assertSame('Intake', $record->payload['elements'][0]['name']);
        $this->assertTrue($record->expires_at->isFuture());
        $this->assertSame($user->id, $record->user_id);
    }

    public function test_the_table_has_no_user_columns_besides_the_owner(): void
    {
        $this->assertEqualsCanonicalizing(
            ['token', 'user_id', 'kind', 'payload', 'expires_at'],
            Schema::getColumnListing('undo_records'),
        );
    }

    public function test_deleting_the_user_removes_the_undo_records_and_recovery_codes(): void
    {
        $user = User::factory()->create();
        UndoRecord::create(['token' => 't', 'user_id' => $user->id, 'kind' => 'k', 'payload' => [], 'expires_at' => now()]);
        RecoveryCode::create(['user_id' => $user->id, 'code_hash' => 'abcd-efgh']);

        $user->delete();

        $this->assertSame(0, DB::table('undo_records')->count());
        $this->assertSame(0, DB::table('two_factor_recovery_codes')->count());
    }

    public function test_a_recovery_code_is_stored_only_as_a_hash(): void
    {
        $user = User::factory()->create();
        $code = RecoveryCode::create(['user_id' => $user->id, 'code_hash' => 'abcd-efgh']);

        $stored = DB::table('two_factor_recovery_codes')->where('id', $code->id)->value('code_hash');

        $this->assertNotSame('abcd-efgh', $stored);
        $this->assertTrue(Hash::check('abcd-efgh', $stored));
    }
}
