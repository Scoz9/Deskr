<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The audit trail of a ticket: every transition and every assignment, with
     * who did it. Append-only — a row is written when something happens and is
     * never touched again.
     */
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();

            /*
             * The trail answers questions about one ticket and has nothing left
             * to answer once that ticket is gone, so it follows it.
             */
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            /*
             * What happened. A plain string on purpose: which events exist is
             * decided with the domain events at step 16, and writing that
             * vocabulary into the schema now would freeze it two steps early.
             */
            $table->string('type');

            /*
             * Who acted. Polimorphic and nullable because the system and the AI
             * have no record to point at (§4) — and next to it the kind, since
             * a null actor with the reason lost is not enough to reconstruct
             * who did what. A string like the other enums of the schema.
             */
            $table->nullableMorphs('actor');
            $table->string('actor_kind');

            /*
             * What the event carries beyond its name: the statuses a transition
             * went between, the agent an assignment landed on. Free-form for
             * the same reason `type` is a string.
             */
            $table->json('payload')->nullable();

            /*
             * No `updated_at`: an audit row is written once, so a column for
             * when it was last modified would only ever be a lie waiting to
             * happen.
             */
            $table->timestamp('created_at')->nullable();

            /*
             * The trail is always read one ticket at a time, in the order
             * things happened.
             */
            $table->index(['ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};
