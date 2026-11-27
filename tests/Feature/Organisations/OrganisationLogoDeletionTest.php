<?php

namespace Tests\Feature\Organisations;

use App\Models\Organisation;
use App\Services\Canvas\OrganisationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class OrganisationLogoDeletionTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->service = app(OrganisationService::class);
    }

    public function test_the_logo_file_is_removed_after_a_plain_delete(): void
    {
        $organisation = $this->organisationWithLogo();

        $this->service->delete($organisation);

        $this->assertNull(Organisation::find($organisation->id));
        Storage::disk('local')->assertMissing((string) $organisation->logo_path);
    }

    public function test_a_failure_after_delete_in_a_wider_transaction_keeps_the_row_and_the_logo_file(): void
    {
        $organisation = $this->organisationWithLogo();
        $path = (string) $organisation->logo_path;

        try {
            DB::transaction(function () use ($organisation) {
                $this->service->delete($organisation);

                throw new RuntimeException('later failure');
            });
        } catch (RuntimeException) {
        }

        $this->assertNotNull(Organisation::find($organisation->id));
        Storage::disk('local')->assertExists($path);
    }

    public function test_the_logo_file_is_removed_once_the_wider_transaction_commits(): void
    {
        $organisation = $this->organisationWithLogo();
        $path = (string) $organisation->logo_path;

        DB::transaction(function () use ($organisation, $path) {
            $this->service->delete($organisation);

            Storage::disk('local')->assertExists($path);
        });

        Storage::disk('local')->assertMissing($path);
    }

    private function organisationWithLogo(): Organisation
    {
        $organisation = $this->service->create('Noord');
        $path = 'logos/'.str_repeat('b', 40).'.png';
        Storage::disk('local')->put($path, 'image-bytes');
        $organisation->forceFill(['logo_path' => $path])->save();

        return $organisation;
    }
}
