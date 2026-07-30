<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The thread of a ticket: the initial description, every reply to the
     * requester and every note that stays in the team. The ticket has no `body`
     * of its own, so this is where the text of a request lives.
     */
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();

            /*
             * A message is part of the ticket aggregate and means nothing
             * without it, so it goes where the ticket goes.
             */
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            /*
             * Always a person: the intake creates or links the user from the
             * email before the message is written. The author cannot be dropped
             * from under the thread — erasing a person anonymizes the user and
             * keeps the history readable (§5, GDPR), the same rule the
             * requester of a ticket follows.
             */
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();

            $table->text('body');

            /*
             * What separates a reply to the requester from a note the team
             * keeps to itself. Public by default: a message that forgets to say
             * what it is must not end up leaking into the portal.
             */
            $table->boolean('is_internal')->default(false);

            /*
             * The id of the email this message came in from, which is what
             * threads an inbound reply onto the ticket it belongs to. Unique so
             * that a provider delivering the same webhook twice cannot append
             * the same email to the thread twice; null for everything written
             * inside the application, and PostgreSQL lets nulls repeat under a
             * unique index.
             */
            $table->string('external_message_id')->nullable()->unique();

            $table->timestamps();

            /*
             * The thread is always read one ticket at a time, in the order it
             * was written.
             */
            $table->index(['ticket_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
