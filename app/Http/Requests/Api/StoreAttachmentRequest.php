<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'm4a', 'aac', 'ogg', 'opus', 'mp3'])
                    ->max(config('chats.max_upload_kb')),
            ],
            'duration_ms' => ['nullable', 'integer', 'min:1', 'max:3600000'],
        ];
    }
}
