<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('attachments:prune {--hours=24 : Age after which an unsent attachment is removed}')]
#[Description('Delete uploaded attachments that were never attached to a message')]
class PruneOrphanedAttachments extends Command
{
    public function handle(): int
    {
        $count = 0;

        Attachment::query()
            ->whereNull('message_id')
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->each(function (Attachment $attachment) use (&$count) {
                Storage::disk($attachment->disk)->delete(array_filter([$attachment->path, $attachment->thumbnail_path]));
                $attachment->delete();
                $count++;
            });

        $this->info("Pruned {$count} orphaned attachment(s).");

        return self::SUCCESS;
    }
}
