<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->rolesTable(), function (Blueprint $table) {
            $table->integer($this->column())->default(0);
        });
    }

    public function down(): void
    {
        Schema::table($this->rolesTable(), function (Blueprint $table) {
            $table->dropColumn($this->column());
        });
    }

    private function rolesTable(): string
    {
        return config('permission.table_names.roles', 'roles');
    }

    private function column(): string
    {
        return config('permission-hierarchy.column', 'hierarchy_rank');
    }
};
