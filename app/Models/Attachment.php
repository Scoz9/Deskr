<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file that came in with a message: the screenshot on the form, the document
 * attached to an inbound email. The relation is a plain one on `TicketMessage`
 * and not polimorphic (§4) — the description of a ticket is a message too, so
 * an attachment always has one to hang from.
 *
 * The bytes never live in the web root: they are written to a private disk and
 * served only through a signed route that goes through the policy (§8).
 *
 * @property int $id
 * @property int $ticket_message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TicketMessage $message
 */
#[Fillable([
    'ticket_message_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
])]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * The disk attachments are written to, and the directory they live in.
     * Private, unlike the one holding avatars: what a requester sends to the
     * helpdesk is never reachable by guessing a URL.
     */
    public const DISK = 'attachments';

    public const DIRECTORY = 'attachments';

    /**
     * The message this file came in with.
     *
     * @return BelongsTo<TicketMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
