<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttachmentController extends Controller
{
    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $isAudio = str_starts_with($mime, 'audio/') || $mime === 'application/ogg';
        $isImage = str_starts_with($mime, 'image/');

        if (! $isAudio && ! $isImage) {
            throw ValidationException::withMessages(['file' => 'Only images and audio are supported.']);
        }

        if ($isAudio && ! $request->filled('duration_ms')) {
            throw ValidationException::withMessages(['duration_ms' => 'The duration_ms field is required for audio.']);
        }

        $disk = config('chats.media_disk');
        $path = $file->storeAs('attachments', Str::ulid().'.'.$file->extension(), $disk);

        $attachment = Attachment::create([
            'user_id' => $request->user()->id,
            'disk' => $disk,
            'path' => $path,
            'mime' => $mime,
            'size' => $file->getSize(),
            'duration_ms' => $isAudio ? $request->integer('duration_ms') : null,
        ]);

        if ($isImage) {
            GenerateThumbnail::dispatch($attachment);
        }

        return AttachmentResource::make($attachment)->response()->setStatusCode(201);
    }
}
