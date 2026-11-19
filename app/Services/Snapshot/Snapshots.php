<?php

namespace App\Services\Snapshot;

use App\Models\Canvas;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Snapshot;
use App\Services\Notices\SystemNotices;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Snapshots
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly SnapshotContent $content,
        private readonly SystemNotices $notices,
    ) {}

    public function take(Canvas $canvas, FiscalYear $year, string $name, ?string $remark = null): Snapshot
    {
        $organisation = Organisation::findOrFail($canvas->organisation_id);

        $taken = $this->writes->run([$organisation, $canvas], function () use ($canvas, $year, $name, $remark): Snapshot|array {
            $this->assertWritable($canvas);

            $content = $this->content->build($canvas, $year);
            $check = $content['figures']['closingCheck'];

            if (($check['passed'] ?? false) !== true) {
                return $check['failures'][0];
            }

            $encoded = $this->content->encode($content);

            return Snapshot::create([
                'canvas_id' => $canvas->getKey(),
                'name' => $name,
                'fiscal_year' => $year->year,
                'remark' => $remark,
                'created_by' => Auth::id(),
                'created_at' => now(),
                'imported' => false,
                'schema_version' => SnapshotContent::SCHEMA_VERSION,
                'content' => $encoded,
                'checksum' => $this->content->checksum($encoded),
            ]);
        });

        if ($taken instanceof Snapshot) {
            return $taken;
        }

        $this->notices->record('closing_check_failed', $canvas->getKey(), [
            'year' => $year->year,
            'filter' => [],
            'check' => $taken['check'],
            'differenceCents' => $taken['differenceCents'],
        ]);

        throw ValidationException::withMessages(['rule' => (string) __('snap.closing_check_failed')]);
    }
}
