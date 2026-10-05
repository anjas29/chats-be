<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAsReadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => [
                'required',
                'integer',
                Rule::exists('messages', 'id')->where('conversation_id', $this->route('conversation')?->id),
            ],
        ];
    }
}
