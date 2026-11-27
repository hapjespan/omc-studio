<?php

namespace App\Services\Snapshot;

use App\Models\Canvas;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Snapshot;
use App\Services\Audit\AuditContext;
use App\Services\Notices\SystemNotices;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Snapshots
{
    use WritableGuard;

    public const DAMAGED_MESSAGE = 'snap.damaged';

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly SnapshotContent $content,
        private readonly SystemNotices $notices,
        private readonly AuditContext $context,
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

        $this->notices->recordClosingCheckFailed($canvas->getKey(), $year->year, [], $taken);

        throw ValidationException::withMessages(['rule' => (string) __('snap.closing_check_failed')]);
    }

    /** @return array<string, mixed>|null */
    public function read(Snapshot $snapshot): ?array
    {
        $content = $this->inspect($snapshot);

        $content === null && $this->damaged($snapshot);

        return $content;
    }

    /** @return array<string, mixed>|null */
    public function inspect(Snapshot $snapshot): ?array
    {
        $encoded = (string) $snapshot->content;

        if (! hash_equals((string) $snapshot->checksum, $this->content->checksum($encoded))) {
            return null;
        }

        return $this->content->upgraded(json_decode($encoded, true, 512, JSON_THROW_ON_ERROR));
    }

    public function damaged(Snapshot $snapshot): void
    {
        $this->notices->record('snapshot_damaged', $snapshot->canvas_id, ['snapshotId' => $snapshot->getKey(), 'snapshot' => $snapshot->name]);
    }

    /** @param array{name?: string, remark?: string|null} $changes */
    public function update(Snapshot $snapshot, array $changes): Snapshot
    {
        return $this->locked($snapshot, function () use ($snapshot, $changes) {
            $snapshot->update(array_intersect_key($changes, array_flip(['name', 'remark'])));

            return $snapshot;
        });
    }

    public function archive(Snapshot $snapshot): Snapshot
    {
        return $this->locked($snapshot, function () use ($snapshot) {
            if ($snapshot->archived_at === null) {
                $this->context->during('archived', fn () => $snapshot->update(['archived_at' => now()]));
            }

            return $snapshot;
        });
    }

    public function restore(Snapshot $snapshot): Snapshot
    {
        return $this->locked($snapshot, function () use ($snapshot) {
            if ($snapshot->archived_at !== null) {
                $this->context->during('restored', fn () => $snapshot->update(['archived_at' => null]));
            }

            return $snapshot;
        });
    }

    public function delete(Snapshot $snapshot): void
    {
        $this->locked($snapshot, fn () => $snapshot->delete());
    }

    private function locked(Snapshot $snapshot, callable $work): mixed
    {
        $canvas = Canvas::findOrFail($snapshot->canvas_id);

        return $this->writes->run([Organisation::findOrFail($canvas->organisation_id), $canvas], function () use ($snapshot, $work, $canvas) {
            $this->assertWritable($canvas);
            $snapshot->refresh();

            return $work();
        });
    }
}
