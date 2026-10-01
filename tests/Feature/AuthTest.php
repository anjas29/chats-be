<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'password123',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com', 'role' => 'user']);
    }

    public function test_registration_cannot_set_a_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'mallory@example.com', 'role' => 'user']);
    }

    public function test_a_user_can_log_in_and_use_the_token(): void
    {
        $user = User::factory()->create(['email' => 'ada@example.com']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'ada@example.com',
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'ada@example.com');
    }

    public function test_login_fails_with_wrong_credentials(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'nope'])
            ->assertUnprocessable();
    }

    public function test_a_banned_user_cannot_log_in(): void
    {
        User::factory()->banned()->create(['email' => 'ada@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ada@example.com', 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_banning_a_user_revokes_tokens_and_blocks_the_api(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $user->ban();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_a_banned_user_with_a_live_token_gets_403(): void
    {
        $user = User::factory()->banned()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guests_cannot_reach_protected_routes(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/conversations')->assertUnauthorized();
    }

    public function test_user_search_excludes_self_and_banned_users(): void
    {
        $me = User::factory()->create(['name' => 'Alice Me']);
        User::factory()->create(['name' => 'Alice Friend']);
        User::factory()->banned()->create(['name' => 'Alice Banned']);
        User::factory()->create(['name' => 'Bob']);
        Sanctum::actingAs($me);

        $this->getJson('/api/v1/users?search=alice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice Friend');
    }
}
