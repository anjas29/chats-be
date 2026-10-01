<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\MediaUrl;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_banned', 'avatar_path', 'last_seen_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_banned' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && ! $this->is_banned;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? MediaUrl::for(config('chats.media_disk'), $this->avatar_path)
            : null;
    }

    /**
     * Prevent a banned user from using the API: flags the account and revokes every token.
     */
    public function ban(): void
    {
        $this->forceFill(['is_banned' => true])->save();
        $this->tokens()->delete();
    }

    public function unban(): void
    {
        $this->forceFill(['is_banned' => false])->save();
    }

    /**
     * @return HasMany<ConversationParticipant, $this>
     */
    public function participations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')
            ->using(ConversationParticipant::class)
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'joined_at', 'left_at']);
    }

    /**
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /**
     * Route FCM notifications to every registered device.
     *
     * @return array<int, string>
     */
    public function routeNotificationForFcm(): array
    {
        return $this->deviceTokens()->pluck('token')->all();
    }
}
