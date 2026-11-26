<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(timestamps: false)]
#[Fillable(['key', 'kind', 'position', 'carries_costs'])]
class Area extends Model
{
    public const KEYS = [
        'direction', 'steering', 'governing', 'actors', 'channels',
        'value_propositions', 'primary_activities', 'supporting_activities',
    ];

    protected $casts = ['position' => 'integer', 'carries_costs' => 'boolean'];
}
