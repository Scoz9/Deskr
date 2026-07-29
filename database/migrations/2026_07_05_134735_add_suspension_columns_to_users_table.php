<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('user-suspension.table', 'users'), function (Blueprint $table) {
            $table->timestamp(config('user-suspension.columns.suspended_at', 'suspended_at'))->nullable();
            $table->timestamp(config('user-suspension.columns.suspended_until', 'suspended_until'))->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(config('user-suspension.table', 'users'), function (Blueprint $table) {
            $table->dropColumn([
                config('user-suspension.columns.suspended_at', 'suspended_at'),
                config('user-suspension.columns.suspended_until', 'suspended_until'),
            ]);
        });
    }
};
