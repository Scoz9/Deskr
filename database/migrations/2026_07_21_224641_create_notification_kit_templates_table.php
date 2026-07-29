<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('templates'), function (Blueprint $table): void {
            $table->id();
            $table->string('key', 150)->unique();
            $table->string('type', 20)->index();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('default_subject')->nullable();
            $table->text('default_body');
            $table->json('placeholders');
            $table->json('sample_data');
            $table->json('metadata')->nullable();
            $table->boolean('requires_confirmation')->default(false);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('templates'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('notification-kit.database.table_prefix', 'notification_kit_');

        return (is_string($prefix) ? $prefix : 'notification_kit_').$suffix;
    }
};
