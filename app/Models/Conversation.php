<?php

namespace App\Models;

use App\Enums\ConversationType;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'avatar_path', 'direct_key', 'created_by', 'last_message_id'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'viewer_muted_until' => 'datetime',
        ];
    }

    /**
     * Conversations the user is an active participant of, with the viewer's
     * unread count, read marker and mute state attached.
     *
     * @param  Builder<Conversation>  $query
     */
    public function scopeForUser(Builder $query, User $user): void
    {
        $unread = Message::query()
            ->selectRaw('count(*)')
            ->whereColumn('messages.conversation_id', 'conversations.id')
            ->where('messages.sender_id', '!=', $user->id)
            ->whereRaw('messages.id > coalesce(cp.last_read_message_id, 0)');

        $query
            ->select('conversations.*')
            ->join('conversation_participants as cp', function ($join) use ($user) {
                $join->on('cp.conversation_id', '=', 'conversations.id')
                    ->where('cp.user_id', $user->id)
                    ->whereNull('cp.left_at');
            })
            ->addSelect([
                'cp.role as viewer_role',
                'cp.last_read_message_id as viewer_last_read_message_id',
                'cp.muted_until as viewer_muted_until',
            ])
            ->selectSub($unread, 'unread_count')
            ->with([
                'participants' => fn ($participants) => $participants->whereNull('left_at'),
                'participants.user',
                'lastMessage.sender',
                'lastMessage.attachments',
            ]);
    }

    public function isGroup(): bool
    {
        return $this->type === ConversationType::Group;
    }

    public static function directKeyFor(int $firstUserId, int $secondUserId): string
    {
        return min($firstUserId, $secondUserId).':'.max($firstUserId, $secondUserId);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'joined_at', 'left_at']);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }
}
