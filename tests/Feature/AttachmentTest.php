<?php

namespace Tests\Feature;

use App\Jobs\GenerateThumbnail;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_image_upload_is_stored_and_queues_a_thumbnail(): void
    {
        Storage::fake('s3');
        Bus::fake([GenerateThumbnail::class]);
        Sanctum::actingAs($user = User::factory()->create());

        $response = $this->postJson('/api/v1/attachments', ['file' => UploadedFile::fake()->image('photo.jpg', 800, 600)])
            ->assertCreated()
            ->assertJsonPath('data.mime', 'image/jpeg')
            ->assertJsonStructure(['data' => ['id', 'url', 'thumbnail_url']]);

        $attachment = Attachment::findOrFail($response->json('data.id'));
        $this->assertSame($user->id, $attachment->user_id);
        Storage::disk('s3')->assertExists($attachment->path);
        Bus::assertDispatched(GenerateThumbnail::class);
    }

    public function test_the_thumbnail_job_records_dimensions_and_stores_a_thumbnail(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/v1/attachments', ['file' => UploadedFile::fake()->image('photo.png', 1200, 800)])
            ->assertCreated()
            ->json('data.id');

        $attachment = Attachment::findOrFail($id);
        $this->assertSame(1200, $attachment->width);
        $this->assertSame(800, $attachment->height);
        $this->assertNotNull($attachment->thumbnail_path);
        Storage::disk('s3')->assertExists($attachment->thumbnail_path);
    }

    public function test_audio_requires_a_duration(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create());
        $file = UploadedFile::fake()->create('voice.mp3', 100, 'audio/mpeg');

        $this->postJson('/api/v1/attachments', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_ms');

        $this->postJson('/api/v1/attachments', ['file' => $file, 'duration_ms' => 4200])
            ->assertCreated()
            ->assertJsonPath('data.duration_ms', 4200);
    }

    public function test_unsupported_and_oversized_files_are_rejected(): void
    {
        Storage::fake('s3');
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/attachments', ['file' => UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')])
            ->assertUnprocessable();
        $this->postJson('/api/v1/attachments', ['file' => UploadedFile::fake()->image('huge.jpg')->size(11 * 1024)])
            ->assertUnprocessable();
    }

    public function test_orphaned_attachments_are_pruned_after_a_day(): void
    {
        Storage::fake('s3');
        $stale = Attachment::factory()->create(['created_at' => now()->subDays(2)]);
        $fresh = Attachment::factory()->create();
        Storage::disk('s3')->put($stale->path, 'x');

        $this->artisan('attachments:prune')->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($fresh);
        Storage::disk('s3')->assertMissing($stale->path);
    }
}
