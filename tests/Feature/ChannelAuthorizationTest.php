<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite broadcasts through the null driver, which cannot sign channel
        // auth responses, so re-register the channels on a configured Reverb driver.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
    }

    public function test_a_participant_can_subscribe_to_the_conversation_channel(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->participants()->create(['user_id' => $user->id, 'joined_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => "private-conversation.{$conversation->id}",
            'socket_id' => '1234.5678',
        ])->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_a_non_participant_cannot_subscribe_to_the_conversation_channel(): void
    {
        $conversation = Conversation::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => "private-conversation.{$conversation->id}",
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }

    public function test_a_user_who_left_cannot_subscribe(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create();
        $conversation->participants()->create(['user_id' => $user->id, 'joined_at' => now(), 'left_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'channel_name' => "private-conversation.{$conversation->id}",
            'socket_id' => '1234.5678',
        ])->assertForbidden();
    }

    public function test_users_can_only_subscribe_to_their_own_feed(): void
    {
        [$me, $other] = User::factory()->count(2)->create();
        Sanctum::actingAs($me);

        $this->postJson('/api/broadcasting/auth', ['channel_name' => "private-user.{$me->id}", 'socket_id' => '1.2'])->assertOk();
        $this->postJson('/api/broadcasting/auth', ['channel_name' => "private-user.{$other->id}", 'socket_id' => '1.2'])->assertForbidden();
    }

    public function test_banned_users_cannot_authenticate_channels(): void
    {
        $user = User::factory()->banned()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', ['channel_name' => "private-user.{$user->id}", 'socket_id' => '1.2'])->assertForbidden();
    }
}
