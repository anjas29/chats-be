<?php

namespace Tests\Feature;

use App\Events\ConversationUpdated;
use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('s3');

        [$this->alice, $this->bob] = User::factory()->count(2)->create();

        $this->conversation = Conversation::factory()->create([
            'created_by' => $this->alice->id,
            'direct_key' => Conversation::directKeyFor($this->alice->id, $this->bob->id),
        ]);

        foreach ([$this->alice, $this->bob] as $user) {
            $this->conversation->participants()->create(['user_id' => $user->id, 'joined_at' => now()]);
        }
    }

    public function test_a_participant_can_send_a_text_message(): void
    {
        Event::fake([MessageSent::class, ConversationUpdated::class]);
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'type' => 'text',
            'body' => 'Hello Bob',
            'client_uuid' => '6f0c5e0e-5c3a-4b1e-9c53-1f4f3d2a9b10',
        ])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Hello Bob')
            ->assertJsonPath('data.sender_id', $this->alice->id)
            ->assertJsonPath('data.client_uuid', '6f0c5e0e-5c3a-4b1e-9c53-1f4f3d2a9b10');

        $message = Message::firstOrFail();
        $this->assertSame($message->id, $this->conversation->fresh()->last_message_id);
        $this->assertSame($message->id, $this->conversation->participants()->where('user_id', $this->alice->id)->value('last_read_message_id'));
        $this->assertNull($this->conversation->participants()->where('user_id', $this->bob->id)->value('last_read_message_id'));

        Event::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->is($message));
        Event::assertDispatched(ConversationUpdated::class, fn (ConversationUpdated $event) => $event->userId === $this->bob->id);
        Event::assertNotDispatched(ConversationUpdated::class, fn (ConversationUpdated $event) => $event->userId === $this->alice->id);
    }

    public function test_sending_with_the_same_client_uuid_is_idempotent(): void
    {
        Event::fake([MessageSent::class]);
        Sanctum::actingAs($this->alice);
        $payload = ['type' => 'text', 'body' => 'Once', 'client_uuid' => '6f0c5e0e-5c3a-4b1e-9c53-1f4f3d2a9b10'];

        $first = $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", $payload)->assertCreated();
        $second = $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('messages', 1);
        Event::assertDispatchedTimes(MessageSent::class, 1);
    }

    public function test_non_participants_cannot_read_or_send_messages(): void
    {
        $mallory = User::factory()->create();
        Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);
        Sanctum::actingAs($mallory);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'text', 'body' => 'hi'])->assertForbidden();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/read", ['message_id' => 1])->assertForbidden();
    }

    public function test_a_text_message_requires_a_body(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'text'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_system_messages_cannot_be_sent_by_clients(): void
    {
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'system', 'body' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_messages_are_paginated_newest_first_with_before(): void
    {
        $messages = Message::factory()->count(5)->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        Sanctum::actingAs($this->alice);

        $page = $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages?limit=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $messages[4]->id)
            ->assertJsonPath('meta.next_before', $messages[3]->id);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages?limit=2&before={$page->json('meta.next_before')}")
            ->assertJsonPath('data.0.id', $messages[2]->id);
    }

    public function test_deleted_messages_appear_as_tombstones(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id, 'body' => 'secret']);
        $message->delete();
        Sanctum::actingAs($this->alice);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")
            ->assertJsonPath('data.0.is_deleted', true)
            ->assertJsonPath('data.0.body', null);
    }

    public function test_replies_must_reference_a_message_in_the_same_conversation(): void
    {
        $other = Message::factory()->create();
        $parent = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'text', 'body' => 'x', 'reply_to_id' => $other->id])
            ->assertUnprocessable();

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'text', 'body' => 'x', 'reply_to_id' => $parent->id])
            ->assertCreated()
            ->assertJsonPath('data.reply_to.id', $parent->id);
    }

    public function test_only_the_sender_can_edit_a_message(): void
    {
        Event::fake([MessageUpdated::class]);
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);

        Sanctum::actingAs($this->bob);
        $this->patchJson("/api/v1/messages/{$message->id}", ['body' => 'hacked'])->assertForbidden();

        Sanctum::actingAs($this->alice);
        $this->patchJson("/api/v1/messages/{$message->id}", ['body' => 'fixed'])
            ->assertOk()
            ->assertJsonPath('data.body', 'fixed');

        $this->assertNotNull($message->fresh()->edited_at);
        Event::assertDispatched(MessageUpdated::class);
    }

    public function test_only_the_sender_can_delete_a_message(): void
    {
        Event::fake([MessageDeleted::class]);
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);

        Sanctum::actingAs($this->bob);
        $this->deleteJson("/api/v1/messages/{$message->id}")->assertForbidden();

        Sanctum::actingAs($this->alice);
        $this->deleteJson("/api/v1/messages/{$message->id}")->assertNoContent();

        $this->assertSoftDeleted($message);
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_marking_as_read_moves_forward_only_and_broadcasts(): void
    {
        Event::fake([MessageRead::class]);
        $messages = Message::factory()->count(3)->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/read", ['message_id' => $messages[2]->id])->assertNoContent();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/read", ['message_id' => $messages[0]->id])->assertNoContent();

        $this->assertSame($messages[2]->id, $this->conversation->participants()->where('user_id', $this->alice->id)->value('last_read_message_id'));
        Event::assertDispatchedTimes(MessageRead::class, 1);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}")->assertJsonPath('data.unread_count', 0);
    }

    public function test_cannot_mark_a_message_from_another_conversation_as_read(): void
    {
        $foreign = Message::factory()->create();
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/read", ['message_id' => $foreign->id])->assertUnprocessable();
    }

    public function test_sending_to_a_direct_conversation_brings_back_a_user_who_left(): void
    {
        $this->conversation->participants()->where('user_id', $this->bob->id)->update(['left_at' => now()]);
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'text', 'body' => 'you there?'])->assertCreated();

        $this->assertNull($this->conversation->participants()->where('user_id', $this->bob->id)->value('left_at'));
    }

    public function test_attachments_must_belong_to_the_sender_and_be_unused(): void
    {
        $mine = Attachment::factory()->create(['user_id' => $this->alice->id]);
        $theirs = Attachment::factory()->create(['user_id' => $this->bob->id]);
        Sanctum::actingAs($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'image', 'attachment_ids' => [$theirs->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment_ids');

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'image', 'attachment_ids' => [$mine->id]])
            ->assertCreated()
            ->assertJsonCount(1, 'data.attachments');

        $this->assertNotNull($mine->fresh()->message_id);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['type' => 'image', 'attachment_ids' => [$mine->id]])
            ->assertUnprocessable();
    }
}
