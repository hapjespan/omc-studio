<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(key: 'token', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['token', 'user_id', 'kind', 'payload', 'expires_at'])]
class UndoRecord extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'expires_at' => 'datetime'];
    }
}
