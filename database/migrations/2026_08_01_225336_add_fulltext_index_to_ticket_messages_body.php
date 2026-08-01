<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A GIN index on `to_tsvector('italian', body)`, matched at query time
     * by `whereFullText(..., ['language' => 'italian'])`: the search of
     * step 33 reads it automatically, no extra wiring needed on the query
     * side.
     */
    public function up(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->fullText('body')->language('italian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropFullText(['body']);
        });
    }
};
