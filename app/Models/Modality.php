<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(timestamps: false)]
#[Fillable(['key', 'letter', 'colour', 'position'])]
class Modality extends Model
{
    protected $casts = ['position' => 'integer'];
}
