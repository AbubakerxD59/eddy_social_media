<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = $this->tableName();

        if ($table === null || Schema::hasColumn($table, 'parent_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            $blueprint->foreignId('parent_id')->nullable()->after('public_id')->constrained($table)->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = $this->tableName();

        if ($table === null || ! Schema::hasColumn($table, 'parent_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropConstrainedForeignId('parent_id');
        });
    }

    private function tableName(): ?string
    {
        return match (true) {
            Schema::hasTable('signals') => 'signals',
            Schema::hasTable('posts') => 'posts',
            default => null,
        };
    }
};
