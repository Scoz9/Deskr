<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Every attribute of the ticket is here from the first migration, the
     * timestamps of the metrics included: retrofitting them onto tickets that
     * already exist costs more than carrying four nullable columns now.
     */
    public function up(): void
    {
        /*
         * The sequence behind the public `reference`, separate from the primary
         * key on purpose: the reference travels in URLs and email subjects,
         * where the auto-incrementing id would expose the volumes and make the
         * tickets enumerable.
         */
        DB::statement('CREATE SEQUENCE '.Ticket::REFERENCE_SEQUENCE);

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('subject');
            $table->string('status')->default(TicketStatus::Nuovo->value);
            $table->string('priority')->default(TicketPriority::Normale->value);
            $table->string('channel');

            /*
             * The requester cannot be dropped from under a ticket: erasing a
             * person anonymizes the user and keeps tickets and events readable.
             * The company is a copy of where the request came from, so the
             * ticket survives the company being deleted.
             */
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            /*
             * Category and team stay null until the ticket is routed: inbound
             * email arrives without a taxonomy. Neither can be deleted while
             * tickets point at it, or the history would lose its meaning.
             */
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();

            /*
             * The assignee is an attribute, not a state, and unassigned is a
             * legal value: an agent who leaves puts the ticket back in the pool.
             */
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

            /*
             * A reply on a closed ticket opens a follow-up that points back at
             * it, instead of resurrecting it and skewing the metrics.
             */
            $table->foreignId('parent_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();

            $table->unsignedInteger('reopen_count')->default(0);
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });

        /*
         * Tie the sequence to the column it feeds, so that it lives and dies
         * with the table: dropping the table is enough, and `migrate:fresh`
         * does not leave an orphan sequence behind that the next run would
         * collide with.
         */
        DB::statement(sprintf(
            'ALTER SEQUENCE %s OWNED BY tickets.reference',
            Ticket::REFERENCE_SEQUENCE,
        ));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
