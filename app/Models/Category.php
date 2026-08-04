<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The taxonomy of a request: it carries the team its tickets are routed to,
 * which is what keeps the routing deterministic and independent of the AI.
 *
 * @property int $id
 * @property string $name
 * @property int $team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Ticket> $tickets
 */
#[Fillable(['name', 'team_id'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * The team the tickets of this category land on. Required: a category
     * without a destination would leave the routing with nowhere to go.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The tickets filed under this category. The database refuses to delete
     * one that still has some: the taxonomy a ticket was filed under is part
     * of its history, not a label to drop from underneath it.
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
