<?php

namespace Tests\Feature\Architecture;

use App\Services\Audit\Audited;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Finder\Finder;
use Tests\Support\SchemaRules;
use Tests\TestCase;

class NoSilentWritesTest extends TestCase
{
    private const WRITES = 'update|updateOrInsert|delete|forceDelete|increment|decrement|insert|insertOrIgnore|insertUsing|upsert|truncate';

    private const ALLOWED = ['app/Models/Canvas.php::raiseStructureVersion', 'app/Services/Audit/AuditTrail.php::recordMany', 'app/Services/Exchange/CostRecordBatch.php::flush'];

    public function test_no_source_file_writes_past_the_audit_observer(): void
    {
        $violations = [];

        foreach (Finder::create()->files()->name('*.php')->in(app_path()) as $file) {
            $violations = [...$violations, ...$this->violations($file->getContents(), 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname()))];
        }

        $this->assertSame([], $violations);
    }

    public function test_a_planted_mass_write_on_an_audited_table_is_caught(): void
    {
        $planted = fn (string $write) => '<?php class Planted { public function run() { '.$write.'; } }';

        $this->assertSame(['app/Planted.php::run update'], $this->violations($planted('Element::where("kind", "domain")->update(["name" => "Nieuw"])'), 'app/Planted.php'));
        $this->assertSame(['app/Planted.php::run delete'], $this->violations($planted('DB::table(\'elements\')->where(\'canvas_id\', $id)->delete()'), 'app/Planted.php'));
        $this->assertSame(['app/Planted.php::run update'], $this->violations($planted('Snapshot::where("canvas_id", $id)->update(["name" => "Nieuw"])'), 'app/Planted.php'));
        $this->assertSame(['app/Planted.php::run delete'], $this->violations($planted('$element->children()->delete()'), 'app/Planted.php'));
        $this->assertSame([], $this->violations($planted('$element->delete()'), 'app/Planted.php'));
        $this->assertSame([], $this->violations($planted('UndoRecord::where(\'expires_at\', \'<\', now())->delete()'), 'app/Planted.php'));
    }

    public function test_the_structure_version_increment_is_allowed_in_its_own_method_only(): void
    {
        $increment = '<?php class Canvas { public function raiseStructureVersion(): void { static::whereKey($this->getKey())->increment(\'structure_version\'); } }';

        $this->assertSame([], $this->violations($increment, 'app/Models/Canvas.php'));
        $this->assertSame(['app/Models/Canvas.php::rename increment'], $this->violations(str_replace('raiseStructureVersion', 'rename', $increment), 'app/Models/Canvas.php'));
        $this->assertSame(['app/Models/Element.php::raiseStructureVersion increment'], $this->violations($increment, 'app/Models/Element.php'));
    }

    /** @return list<string> */
    private function violations(string $source, string $path): array
    {
        $roots = $this->roots($path);
        $method = '';
        $found = [];

        foreach (explode(';', $source) as $statement) {
            if (preg_match_all('/function\s+(\w+)\s*\(/', $statement, $names) > 0) {
                $method = (string) end($names[1]);
            }

            if (preg_match('/->('.self::WRITES.')\(/', $statement, $write) === 0 || preg_match($roots, $statement) === 0) {
                continue;
            }

            if (! in_array($path.'::'.$method, self::ALLOWED, true)) {
                $found[] = $path.'::'.$method.' '.$write[1];
            }
        }

        return $found;
    }

    private function roots(string $path): string
    {
        $audited = array_values(array_filter(SchemaRules::models(), fn (string $class) => in_array(Audited::class, class_uses_recursive($class), true)));
        $patterns = [];

        foreach ($audited as $class) {
            $patterns[] = class_basename($class).'::';
            $patterns[] = 'DB::table\(\s*[\'"]'.(new $class)->getTable().'[\'"]';

            if ($path === 'app/Models/'.class_basename($class).'.php') {
                $patterns[] = 'static::';
                $patterns[] = 'self::';
            }
        }

        foreach ($this->auditedRelations($audited) as $relation) {
            $patterns[] = '->'.$relation.'\(';
        }

        return '/'.implode('|', $patterns).'/';
    }

    /**
     * @param  list<class-string<Model>>  $audited
     * @return list<string>
     */
    private function auditedRelations(array $audited): array
    {
        $relations = [];

        foreach (SchemaRules::models() as $class) {
            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();

                if ($method->getNumberOfParameters() > 0 || ! $type instanceof ReflectionNamedType || ! is_subclass_of($type->getName(), Relation::class)) {
                    continue;
                }

                $related = $method->invoke(new $class);

                if ($related instanceof Relation && in_array($related->getRelated()::class, $audited, true)) {
                    $relations[] = $method->getName();
                }
            }
        }

        return array_values(array_unique($relations));
    }
}
