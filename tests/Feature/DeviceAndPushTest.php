<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceAndPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_device_token_can_be_registered_and_removed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/devices', ['token' => 'fcm-abc', 'platform' => 'android'])->assertCreated();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => 'fcm-abc']);

        $this->deleteJson('/api/v1/me/devices/fcm-abc')->assertNoContent();
        $this->assertDatabaseMissing('device_tokens', ['token' => 'fcm-abc']);
    }

    public function test_registering_an_existing_token_moves_it_to_the_current_user(): void
    {
        $previous = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $previous->id, 'token' => 'shared-device']);
        $current = User::factory()->create();
        Sanctum::actingAs($current);

        $this->postJson('/api/v1/me/devices', ['token' => 'shared-device', 'platform' => 'ios'])->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $current->id, 'token' => 'shared-device', 'platform' => 'ios']);
    }

    public function test_users_cannot_delete_another_users_token(): void
    {
        $owner = User::factory()->create();
        DeviceToken::factory()->create(['user_id' => $owner->id, 'token' => 'owners']);
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/v1/me/devices/owners')->assertNoContent();

        $this->assertDatabaseHas('device_tokens', ['token' => 'owners']);
    }

    public function test_a_new_message_notifies_other_participants_except_muted_ones_and_the_sender(): void
    {
        Notification::fake();
        [$sender, $listening, $muted, $noDevice] = User::factory()->count(4)->create();
        $conversation = Conversation::factory()->group()->create(['created_by' => $sender->id]);

        foreach ([$sender, $listening, $muted, $noDevice] as $user) {
            $conversation->participants()->create([
                'user_id' => $user->id,
                'joined_at' => now(),
                'muted_until' => $user->is($muted) ? now()->addHour() : null,
            ]);
        }

        foreach ([$sender, $listening, $muted] as $user) {
            DeviceToken::factory()->create(['user_id' => $user->id]);
        }

        Sanctum::actingAs($sender);
        $this->postJson("/api/v1/conversations/{$conversation->id}/messages", ['type' => 'text', 'body' => 'Hi all'])->assertCreated();

        Notification::assertSentTo($listening, NewMessageNotification::class);
        Notification::assertNotSentTo($sender, NewMessageNotification::class);
        Notification::assertNotSentTo($muted, NewMessageNotification::class);
        Notification::assertNotSentTo($noDevice, NewMessageNotification::class);
    }

    public function test_the_push_payload_describes_the_message(): void
    {
        $sender = User::factory()->create(['name' => 'Ada']);
        $recipient = User::factory()->create();
        $conversation = Conversation::factory()->create(['created_by' => $sender->id]);
        $message = $conversation->messages()->create(['sender_id' => $sender->id, 'type' => 'image']);

        $payload = (new NewMessageNotification($message))->toFcm($recipient)->toArray();

        $this->assertSame('Ada', $payload['notification']['title']);
        $this->assertSame('📷 Photo', $payload['notification']['body']);
        $this->assertSame((string) $conversation->id, $payload['data']['conversation_id']);
        $this->assertSame((string) $message->id, $payload['data']['message_id']);
    }
}
