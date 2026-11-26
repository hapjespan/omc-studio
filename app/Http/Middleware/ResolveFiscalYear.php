<?php

namespace App\Http\Middleware;

use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\Organisation;
use App\Support\Format;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ResolveFiscalYear
{
    public function handle(Request $request, Closure $next): Response
    {
        $canvas = $request->route('canvas');
        $organisation = $request->route('organisation');
        $organisation = $canvas instanceof Canvas ? $canvas->organisation : $organisation;

        if ($organisation instanceof Organisation) {
            $resolved = $this->resolve($request, $organisation, $canvas instanceof Canvas ? $canvas : null);
            $request->attributes->set('fiscal_year', $resolved['selected']);
            Inertia::share('fiscalYears', $resolved);
        }

        return $next($request);
    }

    /** @return array{selected: int|null, options: list<array{year: int, state: string|null}>} */
    private function resolve(Request $request, Organisation $organisation, ?Canvas $canvas): array
    {
        $years = $organisation->fiscalYears()->orderBy('year')->get(['id', 'year']);
        $final = $canvas === null ? collect() : FinalisedYear::where('canvas_id', $canvas->getKey())->pluck('fiscal_year_id');
        $requested = $request->query('year');
        $numbers = $years->pluck('year');
        $current = (int) now(Format::TIMEZONE)->year;
        $selected = is_string($requested) && ctype_digit($requested) && $numbers->contains((int) $requested) ? (int) $requested : ($numbers->contains($current) ? $current : $numbers->last());

        return [
            'selected' => $selected,
            'options' => $years->map(fn ($year) => ['year' => $year->year, 'state' => $canvas === null ? null : ($final->contains($year->id) ? 'final' : 'open')])->all(),
        ];
    }
}
