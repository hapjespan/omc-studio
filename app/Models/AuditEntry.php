<?php

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(timestamps: false)]
#[Fillable([
    'occurred_at', 'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id',
    'subject_label', 'organisation_id', 'canvas_id', 'changes',
])]
class AuditEntry extends Model
{
    protected $casts = [
        'occurred_at' => 'datetime',
        'actor_type' => ActorType::class,
        'changes' => 'array',
    ];
}
