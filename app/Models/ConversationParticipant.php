<?php

namespace App\Models;

use App\Enums\ParticipantRole;
use Database\Factories\ConversationParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['conversation_id', 'user_id', 'role', 'last_read_message_id', 'muted_until', 'joined_at', 'left_at'])]
class ConversationParticipant extends Pivot
{
    /** @use HasFactory<ConversationParticipantFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'conversation_participants';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'muted_until' => 'datetime',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ConversationParticipant>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('left_at');
    }

    public function isMuted(): bool
    {
        return $this->muted_until !== null && $this->muted_until->isFuture();
    }

    public function canManage(): bool
    {
        return in_array($this->role, [ParticipantRole::Owner, ParticipantRole::Admin], true);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
