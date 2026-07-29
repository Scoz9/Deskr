<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table('outbox_messages'), function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('template_id')->nullable()->constrained($this->table('templates'))->restrictOnDelete();
            $table->string('template_key', 150)->index();
            $table->string('mailable_class');
            $table->string('rendered_subject');
            $table->mediumText('rendered_body_html');
            $table->json('recipients');
            $table->json('envelope')->nullable();
            $table->string('status', 20);
            // Explicit index names: the generated ones would exceed the 64
            // character limit MySQL and MariaDB enforce.
            $table->nullableMorphs('requested_by', 'nk_outbox_requested_by_index');
            $table->nullableMorphs('decided_by', 'nk_outbox_decided_by_index');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'nk_outbox_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('outbox_messages'));
    }

    private function table(string $suffix): string
    {
        $prefix = config('notification-kit.database.table_prefix', 'notification_kit_');

        return (is_string($prefix) ? $prefix : 'notification_kit_').$suffix;
    }
};
