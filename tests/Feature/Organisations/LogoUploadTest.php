<?php

namespace Tests\Feature\Organisations;

use App\Models\AuditEntry;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Canvas\LogoStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LogoUploadTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGE = 'Logo: kies een PNG- of JPEG-afbeelding van maximaal 1 MB en 4000 bij 4000 pixels';

    private LogoStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->store = app(LogoStore::class);
    }

    public function test_a_file_over_one_megabyte_is_refused(): void
    {
        $file = UploadedFile::fake()->createWithContent('logo.png', $this->png(10, 10).str_repeat('x', 1048577));

        $this->assertRefused($file);
    }

    public function test_a_gif_is_refused(): void
    {
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        $this->assertRefused(UploadedFile::fake()->createWithContent('logo.gif', $gif));
        $this->assertRefused(UploadedFile::fake()->createWithContent('logo.png', $gif));
    }

    public function test_a_non_image_named_logo_png_is_refused(): void
    {
        $this->assertRefused(UploadedFile::fake()->createWithContent('logo.png', '<?php echo "not an image";'));
    }

    public function test_an_image_wider_or_taller_than_four_thousand_pixels_is_refused(): void
    {
        $this->assertRefused(UploadedFile::fake()->createWithContent('logo.png', $this->png(4001, 10)));
        $this->assertRefused(UploadedFile::fake()->createWithContent('logo.png', $this->png(10, 4001)));
    }

    public function test_an_image_of_exactly_four_thousand_pixels_passes_the_content_checks(): void
    {
        $this->requiresImageSupport();

        $organisation = Organisation::factory()->create();

        $this->store->store($organisation, $this->upload(4000, 4000));

        $this->assertNotNull($organisation->fresh()?->logo_path);
    }

    public function test_an_archived_organisation_refuses_a_logo(): void
    {
        $organisation = Organisation::factory()->create(['archived_at' => now()]);

        try {
            $this->store->store($organisation, UploadedFile::fake()->createWithContent('logo.png', $this->png(10, 10)));
            $this->fail('expected refusal');
        } catch (ValidationException $exception) {
            $this->assertSame('Deze organisatie is gearchiveerd', $exception->errors()['rule'][0]);
        }

        $this->assertNull($organisation->fresh()?->logo_path);
    }

    public function test_a_png_is_re_encoded_and_stored_under_a_random_private_name(): void
    {
        $this->requiresImageSupport();

        $first = Organisation::factory()->create();
        $second = Organisation::factory()->create();

        $this->store->store($first, $this->upload(12, 8));
        $this->store->store($second, $this->upload(12, 8));

        $one = (string) $first->fresh()?->logo_path;
        $other = (string) $second->fresh()?->logo_path;

        $this->assertMatchesRegularExpression('#^logos/[A-Za-z0-9]{40}\.png$#', $one);
        $this->assertNotSame($one, $other);
        $this->assertStringNotContainsString('logo', basename($one, '.png'));

        Storage::disk('local')->assertExists($one);
        Storage::disk('public')->assertMissing($one);

        $stored = (string) Storage::disk('local')->get($one);
        $this->assertSame([12, 8, IMAGETYPE_PNG], array_slice((array) getimagesizefromstring($stored), 0, 3));
        $this->assertStringNotContainsString('omc-studio-secret', $stored);

        $this->assertSame(1, AuditEntry::where('subject_type', 'organisation')->where('subject_id', $first->id)->where('action', 'changed')->count());
    }

    public function test_a_jpeg_is_stored_as_a_jpeg_and_replaces_the_previous_file(): void
    {
        $this->requiresImageSupport();

        $organisation = Organisation::factory()->create();

        $this->store->store($organisation, $this->upload(12, 8));
        $previous = (string) $organisation->fresh()?->logo_path;

        $this->store->store($organisation, $this->upload(12, 8, IMAGETYPE_JPEG));
        $current = (string) $organisation->fresh()?->logo_path;

        $this->assertMatchesRegularExpression('#^logos/[A-Za-z0-9]{40}\.jpg$#', $current);
        Storage::disk('local')->assertMissing($previous);
        Storage::disk('local')->assertExists($current);
    }

    public function test_the_logo_route_serves_the_stored_image_with_a_fixed_content_type(): void
    {
        $organisation = $this->organisationWithStoredLogo('png');

        $this->actingAs(User::factory()->create())
            ->get('/organisations/'.$organisation->id.'/logo')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $jpeg = $this->organisationWithStoredLogo('jpg');

        $this->actingAs(User::factory()->create())
            ->get('/organisations/'.$jpeg->id.'/logo')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_the_logo_route_answers_404_without_a_logo_or_without_the_file(): void
    {
        $without = Organisation::factory()->create();
        $missing = Organisation::factory()->create(['logo_path' => 'logos/gone.png']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/organisations/'.$without->id.'/logo')->assertNotFound();
        $this->actingAs($user)->get('/organisations/'.$missing->id.'/logo')->assertNotFound();
    }

    public function test_the_logo_route_refuses_a_visitor_who_is_not_signed_in(): void
    {
        $organisation = $this->organisationWithStoredLogo('png');

        $this->get('/organisations/'.$organisation->id.'/logo')->assertRedirect('/sign-in');
    }

    private function assertRefused(UploadedFile $file): void
    {
        $organisation = Organisation::factory()->create();

        try {
            $this->store->store($organisation, $file);
            $this->fail('expected refusal');
        } catch (ValidationException $exception) {
            $this->assertSame(self::MESSAGE, $exception->errors()['logo'][0]);
        }

        $this->assertNull($organisation->fresh()?->logo_path);
        $this->assertSame([], Storage::disk('local')->allFiles('logos'));
    }

    private function organisationWithStoredLogo(string $extension): Organisation
    {
        $path = 'logos/'.str_repeat('a', 40).$extension.'.'.$extension;

        Storage::disk('local')->put($path, $this->png(2, 2));

        return Organisation::factory()->create(['logo_path' => $path]);
    }

    private function upload(int $width, int $height, int $type = IMAGETYPE_PNG): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 20, 30));

        ob_start();
        $type === IMAGETYPE_PNG ? imagepng($image) : imagejpeg($image);
        $bytes = (string) ob_get_clean();

        return UploadedFile::fake()->createWithContent('logo.'.($type === IMAGETYPE_PNG ? 'png' : 'jpg'), $bytes);
    }

    private function png(int $width, int $height): string
    {
        $header = pack('N2C5', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$header.pack('N', crc32('IHDR'.$header));
    }

    private function requiresImageSupport(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('the GD extension is absent in this environment');
        }
    }
}
