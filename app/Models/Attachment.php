<?php

namespace App\Models;

use App\Support\MediaUrl;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'message_id', 'disk', 'path', 'mime', 'size', 'width', 'height', 'duration_ms', 'thumbnail_path'])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory, HasUlids;

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }

    public function url(): string
    {
        return MediaUrl::for($this->disk, $this->path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? MediaUrl::for($this->disk, $this->thumbnail_path) : null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
