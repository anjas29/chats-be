<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_a_regular_user_is_blocked_from_the_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_an_admin_can_open_the_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    public function test_a_banned_admin_is_blocked(): void
    {
        $this->actingAs(User::factory()->admin()->banned()->create())->get('/admin')->assertForbidden();
    }
}
