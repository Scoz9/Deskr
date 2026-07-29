<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('template_versions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained($this->table('templates'))->restrictOnDelete();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('requires_confirmation');
            // Explicit index names: the generated ones would exceed the 64
            // character limit MySQL and MariaDB enforce.
            $table->nullableMorphs('edited_by', 'nk_versions_edited_by_index');
            $table->timestamp('created_at');
            $table->index(['template_id', 'created_at'], 'nk_versions_template_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('template_versions'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('notification-kit.database.table_prefix', 'notification_kit_');

        return (is_string($prefix) ? $prefix : 'notification_kit_').$suffix;
    }
};
