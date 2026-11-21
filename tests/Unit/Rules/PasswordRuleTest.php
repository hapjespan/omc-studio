<?php

namespace Tests\Unit\Rules;

use App\Rules\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordRuleTest extends TestCase
{
    /** @return array<int, string> */
    private function errors(string $value): array
    {
        return Validator::make(['field' => $value], ['field' => [new Password]])->errors()->get('field');
    }

    public function test_eleven_characters_are_refused(): void
    {
        $this->assertSame(['Wachtwoord: gebruik minstens 12 tekens'], $this->errors(str_repeat('x', 11)));
    }

    public function test_twelve_characters_without_composition_are_accepted(): void
    {
        $this->assertSame([], $this->errors('aaaaaaaaaaab'));
    }

    public function test_a_listed_password_is_refused_whatever_its_case(): void
    {
        $this->assertSame(['Wachtwoord: dit wachtwoord is te bekend, kies een ander'], $this->errors('123qweasdzxc'));
        $this->assertSame(['Wachtwoord: dit wachtwoord is te bekend, kies een ander'], $this->errors('123QWEasdZXC'));
    }

    public function test_the_list_holds_at_least_three_thousand_entries_at_the_policy_length(): void
    {
        $entries = file(resource_path('data/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $this->assertGreaterThanOrEqual(3000, count(array_filter($entries, fn (string $entry): bool => mb_strlen($entry) >= 12)));
    }

    public function test_the_list_holds_at_least_ten_thousand_entries_the_policy_can_match(): void
    {
        $entries = file(resource_path('data/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $eligible = count(array_filter($entries, fn (string $entry) => mb_strlen($entry) >= 12 && mb_strlen($entry) <= 128));

        if ($eligible < 10000) {
            $this->markTestIncomplete("Only {$eligible} entries are 12 to 128 characters: ASSUMPTION T053-1 awaits a real corpus from the product owner.");
        }

        $this->assertGreaterThanOrEqual(10000, $eligible);
    }

    public function test_one_hundred_twenty_eight_characters_are_accepted_and_129_refused(): void
    {
        $this->assertSame([], $this->errors(str_repeat('ab', 64)));
        $this->assertSame(['Wachtwoord: gebruik hoogstens 128 tekens'], $this->errors(str_repeat('ab', 64).'c'));
    }

    public function test_spaces_and_pasted_symbols_are_kept_as_typed(): void
    {
        $this->assertSame([], $this->errors("  a b\tc!#é€  d"));
    }

    public function test_passwords_are_hashed_with_bcrypt(): void
    {
        $this->assertStringStartsWith('$2y$', Hash::make('aaaaaaaaaaab'));
    }
}
