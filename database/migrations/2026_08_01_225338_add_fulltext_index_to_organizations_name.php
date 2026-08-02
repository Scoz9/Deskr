<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A GIN index on `to_tsvector('italian', name)`, matched at query time
     * by `whereFullText(..., ['language' => 'italian'])`: the console
     * search of step 33 reads it when it looks a ticket up by the
     * requester's company — no extra wiring needed on the query side.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->fullText('name')->language('italian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropFullText(['name']);
        });
    }
};
