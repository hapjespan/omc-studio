<?php

namespace App\Models;

use Database\Factories\SystemNoticeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kind', 'fingerprint', 'canvas_id', 'details', 'occurrences',
    'first_occurred_at', 'last_occurred_at', 'seen_at',
])]
class SystemNotice extends Model
{
    /** @use HasFactory<SystemNoticeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $attributes = ['occurrences' => 1];

    protected $casts = [
        'details' => 'array',
        'occurrences' => 'integer',
        'first_occurred_at' => 'datetime',
        'last_occurred_at' => 'datetime',
        'seen_at' => 'datetime',
    ];
}
