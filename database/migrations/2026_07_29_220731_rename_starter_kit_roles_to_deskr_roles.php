<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the two roles the starter kit seeds to the names Deskr uses, so the
 * assignments already in the database survive the change. Seeding alone would
 * leave the old roles behind, with the users still attached to them.
 */
return new class extends Migration
{
    /**
     * Old starter kit name => Deskr name.
     *
     * @var array<string, string>
     */
    private const RENAMES = [
        'amministratore' => UserRole::Admin->value,
        'operatore' => UserRole::Agent->value,
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $from => $to) {
            $this->rename($from, $to);
        }
    }

    public function down(): void
    {
        foreach (array_flip(self::RENAMES) as $from => $to) {
            $this->rename($from, $to);
        }
    }

    private function rename(string $from, string $to): void
    {
        DB::table(config('permission.table_names.roles', 'roles'))
            ->where('name', $from)
            ->update(['name' => $to]);
    }
};
