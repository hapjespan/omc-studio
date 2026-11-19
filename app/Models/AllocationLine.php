<?php

namespace App\Models;

use Database\Factories\AllocationLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['allocation_set_id', 'element_id', 'basis_points', 'position'])]
class AllocationLine extends Model
{
    /** @use HasFactory<AllocationLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $casts = ['basis_points' => 'integer', 'position' => 'integer'];

    /** @return BelongsTo<AllocationSet, $this> */
    public function allocationSet(): BelongsTo
    {
        return $this->belongsTo(AllocationSet::class);
    }

    /** @return BelongsTo<Element, $this> */
    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }
}
