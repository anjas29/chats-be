<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateConversationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'avatar' => ['sometimes', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ];
    }
}
