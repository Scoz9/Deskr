<?php

namespace App\Models;

use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The central aggregate: a request that entered from one of the channels, with
 * the state it is in, who it belongs to and the timestamps the metrics are
 * measured on. The initial description is not here — it is the first
 * `TicketMessage`, so that the thread is uniform and every attachment has a
 * message to hang from.
 *
 * @property int $id
 * @property string $reference
 * @property string $subject
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property TicketChannel $channel
 * @property int $requester_id
 * @property int|null $organization_id
 * @property int|null $category_id
 * @property int|null $team_id
 * @property int|null $assignee_id
 * @property int|null $parent_ticket_id
 * @property int $reopen_count
 * @property Carbon|null $first_response_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $due_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $requester
 * @property-read Organization|null $organization
 * @property-read Category|null $category
 * @property-read Team|null $team
 * @property-read User|null $assignee
 * @property-read Ticket|null $parentTicket
 * @property-read Collection<int, Ticket> $followUps
 * @property-read Collection<int, TicketMessage> $messages
 */
#[Fillable([
    'subject',
    'status',
    'priority',
    'channel',
    'requester_id',
    'organization_id',
    'category_id',
    'team_id',
    'assignee_id',
    'parent_ticket_id',
    'reopen_count',
    'first_response_at',
    'resolved_at',
    'closed_at',
    'due_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * The PostgreSQL sequence the public reference is drawn from, and the
     * prefix it is printed with. The reference is not mass assignable: it is
     * generated once, and nothing coming from outside gets to choose it.
     */
    public const REFERENCE_SEQUENCE = 'tickets_reference_seq';

    public const REFERENCE_PREFIX = 'DSK-';

    /**
     * Give every ticket its public reference before the insert, so that the
     * column can be `not null` and no ticket ever exists without one.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            /** @var string|null $reference */
            $reference = $ticket->getAttribute('reference');

            $ticket->reference = $reference ?? static::nextReference();
        });
    }

    /**
     * Draw the next public reference, in the `DSK-000123` format.
     *
     * The sequence is a database object, so two concurrent intakes cannot get
     * the same number without a lock. The name is a class constant, never
     * input, which is why it can be interpolated.
     */
    public static function nextReference(): string
    {
        $next = DB::scalar(sprintf('select nextval(\'%s\')', self::REFERENCE_SEQUENCE));

        return sprintf('%s%06d', self::REFERENCE_PREFIX, (int) $next);
    }

    /**
     * The person who asked. Always present: a ticket without a requester has
     * nobody to answer to.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * The company the request came from, copied over from the requester so that
     * the reporting per customer holds even if the requester moves company.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The taxonomy of the request, and what the routing reads to find the team.
     * Null until the ticket is classified.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The team the ticket was routed to. Stored on the ticket and not read
     * through the category every time: re-routing a category must not rewrite
     * the history of the tickets already handled.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The agent working on it. An attribute, not a state: reassigning does not
     * move the ticket back in its lifecycle.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * The closed ticket this one was born from, when the requester replied to
     * something already closed.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id');
    }

    /**
     * The tickets opened by replying to this one after it was closed.
     *
     * @return HasMany<Ticket, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_ticket_id');
    }

    /**
     * The thread: the initial description, the replies to the requester and the
     * notes the team keeps to itself, oldest first. The order is on the
     * relation because a thread has only one reading order, and without it the
     * database is free to hand the rows back in any. The id breaks the tie
     * between two messages written in the same second.
     *
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest()->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'channel' => TicketChannel::class,
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }
}
