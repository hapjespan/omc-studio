<?php

namespace App\Services\Layout;

use App\Models\Canvas;
use Illuminate\Http\Exceptions\HttpResponseException;

class LayoutVersions
{
    /** @param array<string, mixed> $payload */
    public function assertCurrent(Canvas $canvas, array $payload): void
    {
        if ((int) $payload['structure_version'] !== (int) $canvas->structure_version) {
            $this->conflict('structure_changed');
        }

        if ((int) $payload['layout_version'] !== (int) $canvas->layout_version) {
            $this->conflict('layout_changed');
        }
    }

    private function conflict(string $code): never
    {
        throw new HttpResponseException(response()->json(['message' => (string) __('layout.conflict.'.$code), 'conflict' => $code], 409));
    }
}
