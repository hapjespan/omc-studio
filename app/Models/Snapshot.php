<?php

namespace App\Models;

use App\Services\Audit\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Table(timestamps: false)]
#[Fillable(['canvas_id', 'name', 'fiscal_year', 'remark', 'archived_at', 'created_by', 'created_at', 'imported', 'schema_version', 'content', 'checksum'])]
class Snapshot extends Model
{
    use Audited;

    protected $hidden = ['content'];

    protected $attributes = ['imported' => false];

    protected $casts = ['archived_at' => 'datetime', 'created_at' => 'datetime', 'imported' => 'boolean', 'fiscal_year' => 'integer', 'schema_version' => 'integer'];

    private const EDITABLE = ['name', 'remark', 'archived_at'];

    protected static function booted(): void
    {
        static::updating(function (self $snapshot) {
            $changed = array_diff(array_keys($snapshot->getDirty()), self::EDITABLE);

            if ($changed !== []) {
                throw new InvalidArgumentException('A stored snapshot only allows '.implode(', ', self::EDITABLE).' to change, not '.implode(', ', $changed));
            }
        });
    }

    /** @return BelongsTo<Canvas, $this> */
    public function canvas(): BelongsTo
    {
        return $this->belongsTo(Canvas::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
