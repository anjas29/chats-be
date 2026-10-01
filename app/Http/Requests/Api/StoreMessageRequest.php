<?php

namespace App\Http\Requests\Api;

use App\Enums\MessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MessageType::class)->only([MessageType::Text, MessageType::Image, MessageType::Audio])],
            'body' => [Rule::requiredIf($this->input('type') === MessageType::Text->value), 'nullable', 'string', 'max:5000'],
            'attachment_ids' => [
                Rule::requiredIf($this->input('type') !== MessageType::Text->value),
                'array',
                'max:10',
            ],
            'attachment_ids.*' => ['string', 'distinct', 'size:26'],
            'reply_to_id' => [
                'nullable',
                'integer',
                Rule::exists('messages', 'id')->where('conversation_id', $this->route('conversation')->id),
            ],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
