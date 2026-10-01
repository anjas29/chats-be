<?php

namespace App\Http\Requests\Api;

use App\Enums\ConversationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ConversationType::class)],
            'user_id' => [
                Rule::requiredIf($this->input('type') === ConversationType::Direct->value),
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_banned', false)),
                Rule::notIn([$this->user()->id]),
            ],
            'name' => [Rule::requiredIf($this->input('type') === ConversationType::Group->value), 'nullable', 'string', 'max:100'],
            'member_ids' => [Rule::requiredIf($this->input('type') === ConversationType::Group->value), 'array', 'min:1', 'max:255'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_banned', false))],
        ];
    }
}
