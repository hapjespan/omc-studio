<?php

namespace Tests\Feature\Shell;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_opens_the_admin_index(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Index'));
    }

    public function test_a_reader_gets_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->get('/admin')->assertRedirect('/sign-in');
    }
}
