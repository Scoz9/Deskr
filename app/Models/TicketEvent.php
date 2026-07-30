<?php

namespace App\Models;

use App\Enums\TicketActorType;
use App\Enums\TicketEventType;
use Database\Factories\TicketEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of the audit trail: a transition, an assignment, whatever else the
 * domain events decide to record. Written once and never updated — the trail is
 * what the ticket did, and what happened does not change.
 *
 * The actor is polimorphic and nullable because it is not always a person
 * (§4): the automatic closing job acts as the system, the triage acts as the
 * AI, and neither has a record to point at. `actor_kind` says which of the
 * three it was in every case.
 *
 * @property int $id
 * @property int $ticket_id
 * @property TicketEventType $type
 * @property string|null $actor_type
 * @property int|null $actor_id
 * @property TicketActorType $actor_kind
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 * @property-read Ticket $ticket
 * @property-read Model|null $actor
 */
#[Fillable([
    'ticket_id',
    'type',
    'actor_type',
    'actor_id',
    'actor_kind',
    'payload',
])]
class TicketEvent extends Model
{
    /** @use HasFactory<TicketEventFactory> */
    use HasFactory;

    /**
     * An audit row is written once: there is no moment at which it is updated,
     * so there is no column saying when it last was.
     */
    public const UPDATED_AT = null;

    /**
     * The ticket this happened on.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Who acted, when there is somebody to point at. Null for the system and
     * for the AI — read `actor_kind` to tell those two apart.
     *
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TicketEventType::class,
            'actor_kind' => TicketActorType::class,
            'payload' => 'array',
        ];
    }
}
