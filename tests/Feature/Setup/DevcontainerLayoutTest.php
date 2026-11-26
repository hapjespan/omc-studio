<?php

namespace Tests\Feature\Setup;

use Tests\TestCase;

class DevcontainerLayoutTest extends TestCase
{
    private function read(string $relativePath): string
    {
        $path = base_path($relativePath);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function test_the_development_container_lives_in_the_devcontainer_directory(): void
    {
        $this->assertFileExists(base_path('.devcontainer/docker-compose.yml'));
        $this->assertFileExists(base_path('.devcontainer/Dockerfile'));
        $this->assertFileExists(base_path('.devcontainer/README.md'));
        $this->assertFileExists(base_path('.dockerignore'));
    }

    public function test_the_repository_root_no_longer_defines_the_development_service(): void
    {
        $rootCompose = base_path('docker-compose.yml');

        if (file_exists($rootCompose)) {
            $this->assertStringNotContainsString('omc-studio-dev', (string) file_get_contents($rootCompose));
        }

        $rootDockerfile = base_path('Dockerfile');

        if (file_exists($rootDockerfile)) {
            $this->assertStringNotContainsString('specify-cli', (string) file_get_contents($rootDockerfile));
        }

        $this->addToAssertionCount(1);
    }

    public function test_the_framework_env_template_is_replaced_by_the_single_template(): void
    {
        $this->assertFileDoesNotExist(base_path('.env.example'));
        $this->assertFileExists(base_path('env.example'));
    }

    public function test_the_compose_file_builds_and_mounts_from_the_repository_root(): void
    {
        $compose = $this->read('.devcontainer/docker-compose.yml');

        $this->assertStringContainsString('context: ..', $compose);
        $this->assertStringContainsString('dockerfile: .devcontainer/Dockerfile', $compose);
        $this->assertStringContainsString('- ..:/workspace/omc-studio', $compose);
        $this->assertStringContainsString('container_name: "omc-studio-dev"', $compose);
        $this->assertStringNotContainsString('build: .', $compose);
        $this->assertStringNotContainsString('- .:/workspace/omc-studio', $compose);
    }

    public function test_the_devcontainer_files_carry_no_comments_and_no_stale_frontend_text(): void
    {
        foreach (['.devcontainer/docker-compose.yml', '.devcontainer/Dockerfile'] as $file) {
            $content = $this->read($file);

            $this->assertDoesNotMatchRegularExpression('/^\s*#/m', $content, $file);
            $this->assertStringNotContainsString('React', $content, $file);
        }
    }

    public function test_the_devcontainer_readme_lists_every_local_env_line(): void
    {
        $readme = $this->read('.devcontainer/README.md');

        $this->assertStringContainsString('COMPOSE_FILE=.devcontainer/docker-compose.yml', $readme);
        $this->assertStringContainsString('COMPOSE_PROJECT_NAME=omc-studio', $readme);

        preg_match_all('/\$\{([A-Z_]+)/', $this->read('.devcontainer/docker-compose.yml'), $matches);
        $variables = array_unique($matches[1]);

        $this->assertGreaterThanOrEqual(9, count($variables));

        foreach ($variables as $variable) {
            $this->assertStringContainsString($variable, $readme);
        }
    }

    public function test_the_image_installs_the_browsers_at_the_playwright_version_the_lock_file_pins(): void
    {
        $lock = json_decode($this->read('package-lock.json'), true);
        $version = $lock['packages']['node_modules/@playwright/test']['version'];
        $dockerfile = $this->read('.devcontainer/Dockerfile');

        $this->assertStringContainsString('ARG PLAYWRIGHT_VERSION='.$version, $dockerfile);
        $this->assertStringContainsString('playwright@${PLAYWRIGHT_VERSION} install --with-deps chromium firefox webkit', $dockerfile);
        $this->assertMatchesRegularExpression('/\bxvfb\b/', $dockerfile);
    }
}
