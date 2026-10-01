<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'disk' => config('chats.media_disk'),
            'path' => 'attachments/'.fake()->unique()->uuid(),
            'mime' => 'image/jpeg',
            'size' => fake()->numberBetween(1_000, 500_000),
            'width' => 640,
            'height' => 480,
        ];
    }

    public function audio(): static
    {
        return $this->state(fn () => [
            'mime' => 'audio/mpeg',
            'width' => null,
            'height' => null,
            'duration_ms' => fake()->numberBetween(1_000, 60_000),
        ]);
    }
}
