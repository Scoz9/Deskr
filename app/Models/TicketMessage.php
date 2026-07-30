<?php

namespace App\Models;

use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One entry of a ticket thread: the initial description, a reply to the
 * requester or a note the team keeps to itself. The ticket carries no `body`,
 * so every piece of text of a request is a message — which is what makes the
 * thread uniform to render and gives every attachment something to hang from.
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $author_id
 * @property string $body
 * @property bool $is_internal
 * @property string|null $external_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Ticket $ticket
 * @property-read User $author
 */
#[Fillable([
    'ticket_id',
    'author_id',
    'body',
    'is_internal',
    'external_message_id',
])]
class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use HasFactory;

    /**
     * The ticket this message is part of.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Who wrote it — the requester or an agent. Always a person: the intake
     * creates or links the user from the email before the message exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }
}
