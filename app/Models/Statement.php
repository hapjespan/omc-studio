<?php

namespace App\Models;

use App\Enums\StatementKind;
use App\Services\Audit\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(timestamps: false)]
#[Fillable(['canvas_id', 'kind', 'title', 'body', 'position'])]
class Statement extends Model
{
    use Audited;

    protected $casts = ['kind' => StatementKind::class, 'position' => 'integer'];

    /** @return BelongsTo<Canvas, $this> */
    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }
}
