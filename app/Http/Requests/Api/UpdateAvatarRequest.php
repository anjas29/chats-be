<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateAvatarRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)],
        ];
    }
}
