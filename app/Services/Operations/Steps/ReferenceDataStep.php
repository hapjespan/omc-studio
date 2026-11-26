<?php

namespace App\Services\Operations\Steps;

use App\Models\Area;
use App\Models\Modality;

class ReferenceDataStep
{
    private const AREAS = [
        'direction' => 'text',
        'steering' => 'activities',
        'governing' => 'activities',
        'actors' => 'list',
        'channels' => 'list',
        'value_propositions' => 'list',
        'primary_activities' => 'activities',
        'supporting_activities' => 'activities',
    ];

    private const MODALITIES = [
        'common' => ['C', '#1F7A4D'],
        'adaptive' => ['A', '#1E5FBF'],
        'specialized' => ['S', '#B4540A'],
        'distinct' => ['D', '#6B3FB5'],
    ];

    public function __invoke(): void
    {
        $position = 0;

        foreach (self::AREAS as $key => $kind) {
            Area::updateOrCreate(['key' => $key], ['kind' => $kind, 'position' => ++$position, 'carries_costs' => $kind === 'activities']);
        }

        $position = 0;

        foreach (self::MODALITIES as $key => [$letter, $colour]) {
            Modality::updateOrCreate(['key' => $key], ['letter' => $letter, 'colour' => $colour, 'position' => ++$position]);
        }
    }
}
