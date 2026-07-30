<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The files that came in with a request. The relation is a plain one on
     * `TicketMessage` and not polimorphic (§4): the description of a ticket is
     * itself a message, so every attachment — from the form or from an inbound
     * email — always has a message to hang from.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            /*
             * An attachment is part of the message it came in with and means
             * nothing without it, so it goes where the message goes — and with
             * it where the ticket goes.
             */
            $table->foreignId('ticket_message_id')->constrained()->cascadeOnDelete();

            /*
             * The disk the file was written to, kept on the row instead of
             * being read back from configuration: the day the default disk
             * changes, the files already stored keep resolving from where they
             * actually are.
             */
            $table->string('disk');

            $table->string('path');

            /*
             * What the sender called the file: shown in the interface and used
             * as the download name, never to build the path on disk. The name
             * comes from outside, the storage pipeline generates the stored one.
             */
            $table->string('original_name');

            /*
             * Detected from the contents when the file is stored, not taken
             * from what the upload declares it to be.
             */
            $table->string('mime_type');

            /**
             * In bytes.
             */
            $table->unsignedBigInteger('size');

            $table->timestamps();

            /*
             * Two rows pointing at the same file would delete it from under
             * each other. Per disk, because the same path on another disk is
             * another file.
             */
            $table->unique(['disk', 'path']);

            /*
             * Attachments are always read one message at a time, to render the
             * thread. PostgreSQL does not index a foreign key on its own.
             */
            $table->index('ticket_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
