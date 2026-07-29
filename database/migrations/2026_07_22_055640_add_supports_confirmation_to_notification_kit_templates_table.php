<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->table('templates'), function (Blueprint $table): void {
            // Code-owned: only a ManagedMailable sent through the kit can be
            // held for approval, so only those may require confirmation.
            $table->boolean('supports_confirmation')->default(false)->after('requires_confirmation');
        });
    }

    public function down(): void
    {
        Schema::table($this->table('templates'), function (Blueprint $table): void {
            $table->dropColumn('supports_confirmation');
        });
    }

    private function table(string $suffix): string
    {
        $prefix = config('notification-kit.database.table_prefix', 'notification_kit_');

        return (is_string($prefix) ? $prefix : 'notification_kit_').$suffix;
    }
};
