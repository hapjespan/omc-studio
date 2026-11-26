<?php

namespace Tests\Unit\Setup;

use Tests\TestCase as ApplicationTestCase;

class MessagesFileTest extends ApplicationTestCase
{
    public function test_the_messages_file_is_one_flat_object_of_strings(): void
    {
        $messages = json_decode((string) file_get_contents(base_path('lang/nl.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertNotEmpty($messages);
        foreach ($messages as $key => $message) {
            $this->assertIsString($key);
            $this->assertIsString($message);
        }
    }

    public function test_php_reads_the_same_file_with_the_translation_helper(): void
    {
        $this->assertSame('OMC Studio', __('app.title'));
        $this->assertSame('Hallo Sam, je hebt 3 berichten', __('setup.greeting', ['name' => 'Sam', 'count' => 3]));
    }

    public function test_no_dutch_string_literal_exists_outside_the_messages_file(): void
    {
        $dutch = '/[\'"`][^\'"`\n]*\b(niet|een|het|wachtwoord|gebruiker|opslaan|annuleren|verwijderen|ongeldig|bestand|toevoegen|wijzigen|nieuwe?)\b[^\'"`\n]*[\'"`]/i';
        $offenders = [];

        foreach (['app', 'routes', 'resources/js', 'resources/views', 'database'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($directory), \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! preg_match('/\.(php|ts|vue)$/', $file->getFilename())) {
                    continue;
                }
                if (preg_match($dutch, (string) file_get_contents($file->getPathname()))) {
                    $offenders[] = substr($file->getPathname(), strlen(base_path()) + 1);
                }
            }
        }

        $this->assertSame([], $offenders);
    }
}
