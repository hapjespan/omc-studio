<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table('two_factor_recovery_codes', timestamps: false)]
#[Fillable(['user_id', 'code_hash'])]
class RecoveryCode extends Model
{
    protected $casts = ['code_hash' => 'hashed'];
}
