<?php

namespace Tests\Unit\Rules;

use App\Http\Requests\AppFormRequest;
use App\Rules\ElementName;
use App\Rules\TrimmedText;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RulesTest extends TestCase
{
    /** @param array<string, mixed> $rules
     * @param array<string, mixed> $data
     * @return array<int, string> */
    private function errors(array $rules, array $data): array
    {
        return Validator::make($data, $rules)->errors()->get('field');
    }

    public function test_a_greater_than_sign_in_a_name_is_refused_with_the_field_and_the_fix(): void
    {
        $this->assertSame(['Naam: het teken > is niet toegestaan'], $this->errors(['field' => [new ElementName]], ['field' => 'Kosten > Inkoop']));
    }

    public function test_a_name_over_150_characters_is_refused(): void
    {
        $this->assertSame(['Naam: gebruik hoogstens 150 tekens'], $this->errors(['field' => [new ElementName]], ['field' => str_repeat('a', 151)]));
        $this->assertSame([], $this->errors(['field' => [new ElementName]], ['field' => str_repeat('a', 150)]));
    }

    public function test_length_is_counted_after_trimming(): void
    {
        $this->assertSame([], $this->errors(['field' => [new ElementName]], ['field' => ' '.str_repeat('a', 150).' ']));
    }

    public function test_an_empty_trimmed_value_is_empty(): void
    {
        $this->assertSame([], $this->errors(['field' => ['nullable', new ElementName]], ['field' => '   ']));
        $this->assertNotSame([], $this->errors(['field' => ['required', new ElementName]], ['field' => '   ']));
    }

    public function test_a_trimmed_text_applies_its_own_label_and_limit(): void
    {
        $rule = new TrimmedText('Omschrijving', 2000);

        $this->assertSame(['Omschrijving: gebruik hoogstens 2000 tekens'], $this->errors(['field' => [$rule]], ['field' => str_repeat('a', 2001)]));
        $this->assertSame([], $this->errors(['field' => [$rule]], ['field' => str_repeat('a', 2000)]));
    }

    public function test_the_form_request_base_trims_every_string_input(): void
    {
        $request = new class extends AppFormRequest
        {
            /** @return array<string, mixed> */
            public function rules(): array
            {
                return [];
            }

            /** @return array<string, mixed> */
            public function trimmed(): array
            {
                $this->prepareForValidation();

                return $this->all();
            }
        };
        $request->merge(['name' => '  Koers ', 'nested' => ['a' => ' b '], 'count' => 3]);

        $this->assertSame(['name' => 'Koers', 'nested' => ['a' => 'b'], 'count' => 3], $request->trimmed());
    }
}
