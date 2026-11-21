<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class DependenciesTest extends TestCase
{
    public function test_the_production_image_carries_no_tinker_and_no_pail(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        $this->assertArrayNotHasKey('laravel/tinker', $composer['require']);
        $this->assertArrayNotHasKey('laravel/pail', $composer['require']);
        $this->assertArrayNotHasKey('laravel/pail', $composer['require-dev']);
        $this->assertArrayHasKey('laravel/tinker', $composer['require-dev']);
    }

    public function test_the_env_example_holds_no_timezone_setting_that_nothing_reads(): void
    {
        $this->assertStringNotContainsString('APP_TIMEZONE', (string) file_get_contents(base_path('env.example')));
        $this->assertSame('UTC', config('app.timezone'));
    }
}
