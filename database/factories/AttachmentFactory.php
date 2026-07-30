<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state: the document a requester attaches to
     * the description of the problem, on the private disk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_message_id' => TicketMessage::factory(),
            'disk' => Attachment::DISK,
            'path' => $this->storedPath('pdf'),
            'original_name' => fake()->slug(2).'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 5 * 1024 * 1024),
        ];
    }

    /**
     * The screenshot of the error, the most common attachment on the form.
     */
    public function immagine(): static
    {
        return $this->state(fn (): array => [
            'path' => $this->storedPath('png'),
            'original_name' => fake()->slug(2).'.png',
            'mime_type' => 'image/png',
        ]);
    }

    /**
     * The path a file is stored at: a generated name under the attachments
     * directory, never the one the sender chose.
     */
    private function storedPath(string $extension): string
    {
        return Attachment::DIRECTORY.'/'.Str::random(40).'.'.$extension;
    }
}
