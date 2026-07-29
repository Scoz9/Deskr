<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * With the inverted hierarchy (rank 0 is the top) a default of 0 would
     * silently create top-tier roles: drop it so the rank must be explicit.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->integer('hierarchy_rank')->change();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->integer('hierarchy_rank')->default(0)->change();
        });
    }
};
