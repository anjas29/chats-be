<?php

namespace Tests\Feature;

use App\Filament\Resources\Conversations\ConversationResource;
use App\Filament\Resources\Messages\MessageResource;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\MessagesPerDayChart;
use App\Filament\Widgets\StatsOverview;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        $conversation = Conversation::factory()->create();
        $message = Message::factory()->create(['conversation_id' => $conversation->id]);
        $this->actingAs($admin);

        $this->get('/admin')->assertOk();
        $this->get(UserResource::getUrl('index'))->assertOk();
        $this->get(UserResource::getUrl('view', ['record' => $admin]))->assertOk();
        $this->get(ConversationResource::getUrl('index'))->assertOk();
        $this->get(ConversationResource::getUrl('view', ['record' => $conversation]))->assertOk();
        $this->get(MessageResource::getUrl('index'))->assertOk();
        $this->get(MessageResource::getUrl('view', ['record' => $message]))->assertOk();
    }

    public function test_dashboard_widgets_render(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Message::factory()->count(2)->create();

        Livewire::test(StatsOverview::class)->assertSee('Messages today');
        Livewire::test(MessagesPerDayChart::class)->assertSee('Messages per day');
    }

    public function test_admin_can_remove_and_restore_a_message(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $message = Message::factory()->create();

        Livewire::test(ListMessages::class)->callTableAction('delete', $message);
        $this->assertSoftDeleted($message);

        Livewire::test(ListMessages::class)->filterTable('trashed', true)->callTableAction('restore', $message);
        $this->assertNotSoftDeleted($message);
    }

    public function test_admin_can_ban_and_unban_a_user_and_tokens_are_revoked(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        $user->createToken('mobile');

        Livewire::test(ListUsers::class)->callTableAction('ban', $user);

        $this->assertTrue($user->fresh()->is_banned);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        Livewire::test(ListUsers::class)->callTableAction('unban', $user);

        $this->assertFalse($user->fresh()->is_banned);
    }

    public function test_admin_cannot_ban_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(ListUsers::class)->assertTableActionHidden('ban', $admin);
    }
}
