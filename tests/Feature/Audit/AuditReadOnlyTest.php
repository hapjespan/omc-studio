<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_route_about_the_audit_log_accepts_a_write_method(): void
    {
        $writes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'audit'))
            ->flatMap(fn ($route) => $route->methods())
            ->reject(fn ($method) => in_array($method, ['GET', 'HEAD'], true));

        $this->assertCount(0, $writes);
    }

    public function test_no_route_binds_an_audit_entry(): void
    {
        $bound = collect(Route::getRoutes()->getRoutes())->filter(fn ($route) => str_contains(json_encode($route->getAction()), 'AuditEntry') || in_array('AuditEntry', array_map(fn ($parameter) => class_basename((string) $parameter->getType()), $route->signatureParameters()), true));

        $this->assertCount(0, $bound);
    }

    public function test_a_direct_write_request_is_refused_and_leaves_the_entry(): void
    {
        $admin = User::factory()->administrator()->create();
        $entry = AuditEntry::query()->firstOrFail();
        $count = AuditEntry::count();
        $this->actingAs($admin);

        foreach (['put', 'patch', 'delete', 'post'] as $method) {
            foreach (['/admin/audit-log', '/admin/audit-log/'.$entry->id] as $uri) {
                $status = $this->{$method}($uri, ['subject_label' => 'x'])->getStatusCode();
                $this->assertContains($status, [404, 405], "$method $uri");
            }
        }

        $this->assertSame($entry->subject_label, $entry->fresh()->subject_label);
        $this->assertSame($count, AuditEntry::count());
    }
}
