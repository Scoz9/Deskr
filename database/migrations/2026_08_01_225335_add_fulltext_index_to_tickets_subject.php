<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A GIN index on `to_tsvector('italian', subject)`, matched at query
     * time by `whereFullText(..., ['language' => 'italian'])`: the search
     * of step 33 reads it automatically, no extra wiring needed on the
     * query side.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->fullText('subject')->language('italian');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropFullText(['subject']);
        });
    }
};
