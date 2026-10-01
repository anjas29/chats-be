<?php

namespace Tests\Feature;

use App\Events\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_direct_conversation_is_find_or_create(): void
    {
        Event::fake([ConversationUpdated::class]);
        [$alice, $bob] = User::factory()->count(2)->create();

        Sanctum::actingAs($alice);
        $first = $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $bob->id]);
        $first->assertCreated()->assertJsonPath('data.type', 'direct');

        $second = $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $bob->id]);
        $second->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        Sanctum::actingAs($bob);
        $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $alice->id])
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_participants', 2);
        Event::assertDispatchedTimes(ConversationUpdated::class, 1);
    }

    public function test_a_direct_conversation_with_yourself_or_a_banned_user_is_rejected(): void
    {
        $alice = User::factory()->create();
        $banned = User::factory()->banned()->create();
        Sanctum::actingAs($alice);

        $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $alice->id])->assertUnprocessable();
        $this->postJson('/api/v1/conversations', ['type' => 'direct', 'user_id' => $banned->id])->assertUnprocessable();
    }

    public function test_creating_a_group_makes_the_creator_the_owner(): void
    {
        [$alice, $bob, $carol] = User::factory()->count(3)->create();
        Sanctum::actingAs($alice);

        $response = $this->postJson('/api/v1/conversations', [
            'type' => 'group',
            'name' => 'Weekend plans',
            'member_ids' => [$bob->id, $carol->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Weekend plans')
            ->assertJsonPath('data.my_role', 'owner')
            ->assertJsonCount(3, 'data.participants');
    }

    public function test_a_group_requires_a_name_and_members(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/conversations', ['type' => 'group'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'member_ids']);
    }

    public function test_the_list_only_contains_my_conversations_with_unread_counts(): void
    {
        [$alice, $bob, $carol] = User::factory()->count(3)->create();
        $mine = $this->directConversation($alice, $bob);
        $this->directConversation($bob, $carol);

        Message::factory()->count(3)->create(['conversation_id' => $mine->id, 'sender_id' => $bob->id]);
        Message::factory()->create(['conversation_id' => $mine->id, 'sender_id' => $alice->id]);

        Sanctum::actingAs($alice);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id)
            ->assertJsonPath('data.0.unread_count', 3);
    }

    public function test_non_participants_cannot_view_a_conversation(): void
    {
        [$alice, $bob, $mallory] = User::factory()->count(3)->create();
        $conversation = $this->directConversation($alice, $bob);
        Sanctum::actingAs($mallory);

        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertForbidden();
    }

    public function test_group_admins_can_add_and_remove_members_but_members_cannot(): void
    {
        Event::fake([ConversationUpdated::class]);
        [$owner, $member, $newcomer] = User::factory()->count(3)->create();
        $group = $this->group($owner, [$member]);

        Sanctum::actingAs($member);
        $this->postJson("/api/v1/conversations/{$group->id}/participants", ['user_ids' => [$newcomer->id]])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/conversations/{$group->id}/participants", ['user_ids' => [$newcomer->id]])
            ->assertOk()
            ->assertJsonCount(3, 'data.participants');

        $this->deleteJson("/api/v1/conversations/{$group->id}/participants/{$member->id}")->assertNoContent();
        $this->assertNotNull($group->participants()->where('user_id', $member->id)->value('left_at'));

        $this->deleteJson("/api/v1/conversations/{$group->id}/participants/{$owner->id}")->assertNoContent();
    }

    public function test_the_owner_cannot_be_removed_by_another_admin(): void
    {
        [$owner, $admin] = User::factory()->count(2)->create();
        $group = $this->group($owner, [$admin]);
        $group->participants()->where('user_id', $admin->id)->update(['role' => 'admin']);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/conversations/{$group->id}/participants/{$owner->id}")->assertForbidden();
    }

    public function test_leaving_as_owner_transfers_ownership(): void
    {
        [$owner, $member] = User::factory()->count(2)->create();
        $group = $this->group($owner, [$member]);
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/conversations/{$group->id}")->assertNoContent();

        $this->assertSame('owner', $group->participants()->where('user_id', $member->id)->first()->role->value);
        $this->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');
    }

    public function test_only_group_admins_can_rename_a_group(): void
    {
        [$owner, $member] = User::factory()->count(2)->create();
        $group = $this->group($owner, [$member]);

        Sanctum::actingAs($member);
        $this->patchJson("/api/v1/conversations/{$group->id}", ['name' => 'Hijacked'])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/v1/conversations/{$group->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');
    }

    public function test_a_conversation_can_be_muted_and_unmuted(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $conversation = $this->directConversation($alice, $bob);
        Sanctum::actingAs($alice);

        $until = now()->addHour()->toIso8601String();
        $this->postJson("/api/v1/conversations/{$conversation->id}/mute", ['muted_until' => $until])->assertOk();
        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertJsonPath('data.muted_until', fn ($value) => $value !== null);

        $this->postJson("/api/v1/conversations/{$conversation->id}/mute", ['muted_until' => null])->assertOk();
        $this->getJson("/api/v1/conversations/{$conversation->id}")->assertJsonPath('data.muted_until', null);
    }

    private function directConversation(User $first, User $second): Conversation
    {
        $conversation = Conversation::factory()->create([
            'created_by' => $first->id,
            'direct_key' => Conversation::directKeyFor($first->id, $second->id),
        ]);

        foreach ([$first, $second] as $user) {
            $conversation->participants()->create(['user_id' => $user->id, 'joined_at' => now()]);
        }

        return $conversation;
    }

    /**
     * @param  array<int, User>  $members
     */
    private function group(User $owner, array $members): Conversation
    {
        $group = Conversation::factory()->group()->create(['created_by' => $owner->id]);
        $group->participants()->create(['user_id' => $owner->id, 'role' => 'owner', 'joined_at' => now()]);

        foreach ($members as $member) {
            $group->participants()->create(['user_id' => $member->id, 'joined_at' => now()]);
        }

        return $group;
    }
}
