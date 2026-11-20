<?php

namespace Tests\Feature\Architecture;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class StructureVersionRaisedTest extends TestCase
{
    private const WRITES = 'create|forceCreate|update|save|delete|forceDelete|insert|upsert|increment|decrement';

    private const ROOTS = '/Element::|new Element|->children\(|->elements\(|\$element->|\$child->|\$sibling->|\$copy->/';

    private const ALLOWED = ['app/Services/Canvas/CanvasLifecycle.php'];

    public function test_every_class_that_writes_elements_raises_the_structure_version(): void
    {
        $missing = [];

        foreach (Finder::create()->files()->name('*.php')->in(app_path()) as $file) {
            $path = 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

            if ($this->forgetsTheRaise($file->getContents(), $path)) {
                $missing[] = $path;
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_a_planted_element_write_without_the_raise_is_caught(): void
    {
        $planted = fn (string $body) => '<?php class Planted { public function run() { '.$body.'; } }';

        $this->assertTrue($this->forgetsTheRaise($planted('Element::create(["name" => "Nieuw"])'), 'app/Planted.php'));
        $this->assertTrue($this->forgetsTheRaise($planted('(new Element)->forceFill($row)->save()'), 'app/Planted.php'));
        $this->assertTrue($this->forgetsTheRaise($planted('$element->update(["parent_id" => $target->id])'), 'app/Planted.php'));
        $this->assertTrue($this->forgetsTheRaise($planted('$element->children()->delete()'), 'app/Planted.php'));
    }

    public function test_the_scan_catches_every_covered_path_once_its_raise_is_taken_out(): void
    {
        $paths = [
            'app/Services/Structure/ElementTree.php',
            'app/Services/Structure/ElementDeletion.php',
            'app/Services/Structure/ElementArchive.php',
            'app/Services/Undo/UndoStore.php',
        ];

        foreach ($paths as $path) {
            $source = str_replace('raiseStructureVersion', 'doNothing', (string) file_get_contents(base_path($path)));

            $this->assertTrue($this->forgetsTheRaise($source, $path), $path);
        }
    }

    public function test_the_raise_and_the_allowlist_clear_a_file(): void
    {
        $planted = '<?php class Planted { public function run() { Element::create(["name" => "Nieuw"]); $canvas->raiseStructureVersion(); } }';

        $this->assertFalse($this->forgetsTheRaise($planted, 'app/Planted.php'));
        $this->assertFalse($this->forgetsTheRaise(str_replace('$canvas->raiseStructureVersion();', '', $planted), self::ALLOWED[0]));
        $this->assertFalse($this->forgetsTheRaise('<?php class Planted { public function run() { Element::query()->where("canvas_id", $id)->get(); } }', 'app/Planted.php'));
    }

    private function forgetsTheRaise(string $source, string $path): bool
    {
        if (in_array($path, self::ALLOWED, true) || str_contains($source, 'raiseStructureVersion')) {
            return false;
        }

        foreach (explode(';', $source) as $statement) {
            if (preg_match('/(->|::)('.self::WRITES.')\(/', $statement) === 1 && preg_match(self::ROOTS, $statement) === 1) {
                return true;
            }
        }

        return false;
    }
}
