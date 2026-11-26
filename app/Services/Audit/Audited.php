<?php

namespace App\Services\Audit;

use App\Observers\AuditObserver;

trait Audited
{
    public static function bootAudited(): void
    {
        static::whenBooted(fn () => static::observe(AuditObserver::class));
    }
}
