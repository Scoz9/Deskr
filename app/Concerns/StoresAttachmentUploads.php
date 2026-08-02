<?php

namespace App\Concerns;

use App\Actions\Tickets\NewAttachment;
use App\Models\Attachment;
use Illuminate\Http\UploadedFile;

/**
 * Writes picked files to the private disk and describes them for the
 * Actions that append them to a thread — the intake of step 23 and the
 * console reply of step 36 both turn an upload into the same shape.
 *
 * The stored name is generated, never the one that came in: a file name is
 * input like any other, and one that decides where it lands is a file name
 * that can land anywhere. What the sender called it travels on the row, and
 * comes back only as the name of the download.
 */
trait StoresAttachmentUploads
{
    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     * @return list<NewAttachment>
     */
    private function storeAttachmentUploads(array|UploadedFile|null $files): array
    {
        $files = $files instanceof UploadedFile ? [$files] : ($files ?? []);

        return array_map(
            fn (UploadedFile $file): NewAttachment => new NewAttachment(
                disk: Attachment::DISK,
                path: (string) $file->store(Attachment::DIRECTORY, Attachment::DISK),
                originalName: $file->getClientOriginalName(),
                mimeType: (string) $file->getMimeType(),
                size: (int) $file->getSize(),
            ),
            array_values($files),
        );
    }
}
