<?php

namespace Tests\Unit\Rules;

use App\Rules\AmountCents;
use App\Rules\BasisPoints;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmountCentsTest extends TestCase
{
    /** @return array<string, array{string, ?int}> */
    public static function amounts(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/amounts.json'), true)['amount'];

        return array_combine(array_column($cases, 'in'), array_map(fn ($c) => [$c['in'], $c['cents']], $cases));
    }

    /** @return array<string, array{string, ?int}> */
    public static function percentages(): array
    {
        $cases = json_decode((string) file_get_contents(__DIR__.'/../../fixtures/amounts.json'), true)['percentage'];

        return array_combine(array_column($cases, 'in'), array_map(fn ($c) => [$c['in'], $c['bp']], $cases));
    }

    /** @return list<string> */
    private function errors(object $rule, mixed $value): array
    {
        return Validator::make(['field' => $value], ['field' => [$rule]])->errors()->get('field');
    }

    #[DataProvider('amounts')]
    public function test_the_amount_rule_and_parser_agree_with_the_shared_fixture(string $input, ?int $cents): void
    {
        $this->assertSame($cents, AmountCents::parse($input));
        $this->assertSame($cents === null ? 1 : 0, count($this->errors(new AmountCents('Budget'), $input)));
    }

    public function test_the_refusal_names_the_field_and_the_format(): void
    {
        $this->assertSame(
            ['Budget: vul een bedrag in van € 0,00 tot € 999.999.999,99, met een komma voor de centen'],
            $this->errors(new AmountCents('Budget'), '12.50'),
        );
    }

    public function test_an_empty_value_is_left_to_the_required_rule(): void
    {
        $this->assertSame([], $this->errors(new AmountCents('Budget'), ''));
        $this->assertSame([], $this->errors(new AmountCents('Budget'), null));
    }

    #[DataProvider('percentages')]
    public function test_the_percentage_rule_and_parser_agree_with_the_shared_fixture(string $input, ?int $bp): void
    {
        $this->assertSame($bp, BasisPoints::parse($input));
        $this->assertSame($bp === null ? 1 : 0, count($this->errors(new BasisPoints('Run'), $input)));
    }

    public function test_the_percentage_refusal_names_the_field_and_the_range(): void
    {
        $this->assertSame(
            ['Run: vul een percentage in van 0 tot 100, met een komma voor de decimalen'],
            $this->errors(new BasisPoints('Run'), '101'),
        );
    }
}
