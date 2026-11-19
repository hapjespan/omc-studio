<?php

namespace App\Services\Finance;

use App\Models\AllocationSet;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\YearlyFigure;
use App\Rules\AmountCents;
use App\Rules\BasisPoints;
use App\Services\Allocation\FinalisedYears;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class YearlyFigures
{
    use WritableGuard;

    private const FTE_MAX = 999_999;

    private const REMARK_MAX = 2000;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly FinalisedYears $finalisedYears,
    ) {}

    /** @param array<string, mixed> $data */
    public function save(CostRecord $record, FiscalYear $year, array $data): YearlyFigure
    {
        return $this->writes->run([$record->organisation], function () use ($record, $year, $data) {
            $this->assertWritable($record->organisation);
            $this->finalisedYears->assertOpenInOrganisation($year);

            return YearlyFigure::updateOrCreate(
                ['cost_record_id' => $record->getKey(), 'fiscal_year_id' => $year->getKey()],
                $this->values($data),
            );
        });
    }

    public function delete(YearlyFigure $figure): void
    {
        $organisation = $figure->costRecord->organisation;

        $this->writes->run([$organisation], function () use ($figure, $organisation) {
            $this->assertWritable($organisation);
            $this->finalisedYears->assertOpenInOrganisation($figure->fiscalYear);

            foreach ($this->sets($figure)->with('lines')->get() as $set) {
                foreach ($set->lines as $line) {
                    $line->delete();
                }

                $set->delete();
            }

            $figure->delete();
        });
    }

    public function setCount(YearlyFigure $figure): int
    {
        return $this->sets($figure)->count();
    }

    public function undeterminedShare(YearlyFigure $figure): int
    {
        return BasisPoints::MAX - (int) $figure->run_bp - (int) $figure->change_bp;
    }

    /** @return Builder<AllocationSet> */
    private function sets(YearlyFigure $figure): Builder
    {
        return AllocationSet::where('cost_record_id', $figure->cost_record_id)->where('fiscal_year_id', $figure->fiscal_year_id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function values(array $data): array
    {
        $errors = [];
        $values = [
            'budget_cents' => $this->amount($data, 'budget', true, $errors),
            'forecast_cents' => $this->amount($data, 'forecast', false, $errors),
            'run_bp' => $this->share($data, 'run', $errors),
            'change_bp' => $this->share($data, 'change', $errors),
            'fte_hundredths' => $this->fte($data, $errors),
            'reference_date' => $this->date($data, $errors),
            'remark' => $this->remark($data, $errors),
        ];

        if ((int) $values['run_bp'] + (int) $values['change_bp'] > BasisPoints::MAX) {
            $errors['rule'] = __('yearly_figures.run_change_sum');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private function amount(array $data, string $key, bool $required, array &$errors): ?int
    {
        $text = $this->text($data, $key);
        $cents = $text === '' ? null : AmountCents::parse($text);

        if ($cents === null && ($required || $text !== '')) {
            $errors[$key] = __('validation.amount_cents', ['label' => __('fields.'.$key)]);
        }

        return $cents;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private function share(array $data, string $key, array &$errors): ?int
    {
        $text = $this->text($data, $key);
        $points = $text === '' ? null : BasisPoints::parse($text);

        if ($points === null && $text !== '') {
            $errors[$key] = __('validation.basis_points', ['label' => __('fields.'.$key)]);
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private function fte(array $data, array &$errors): ?int
    {
        $text = $this->text($data, 'fte');
        $hundredths = $text === '' ? null : AmountCents::parse($text);

        if ($text !== '' && ($hundredths === null || $hundredths > self::FTE_MAX)) {
            $errors['fte'] = __('yearly_figures.fte_invalid');

            return null;
        }

        return $hundredths;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private function date(array $data, array &$errors): ?string
    {
        $text = $this->text($data, 'reference_date');

        if ($text === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        if ($date === false || $date->format('Y-m-d') !== $text) {
            $errors['reference_date'] = __('yearly_figures.reference_date_invalid');

            return null;
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $errors
     */
    private function remark(array $data, array &$errors): ?string
    {
        $text = $this->text($data, 'remark');

        if (mb_strlen($text) > self::REMARK_MAX) {
            $errors['remark'] = __('validation.text_max', ['label' => __('fields.remark'), 'max' => self::REMARK_MAX]);
        }

        return $text === '' ? null : $text;
    }

    /** @param array<string, mixed> $data */
    private function text(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
