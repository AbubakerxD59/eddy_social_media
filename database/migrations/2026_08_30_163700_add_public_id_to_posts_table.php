<?php

use App\Models\Signal;
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

        if ($table === null || Schema::hasColumn($table, 'public_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->string('public_id', 12)->nullable()->after('id');
        });

        Signal::query()->each(function (Signal $signal): void {
            $signal->forceFill([
                'public_id' => Signal::generatePublicId(),
            ])->saveQuietly();
        });

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->unique('public_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table = $this->tableName();

        if ($table === null || ! Schema::hasColumn($table, 'public_id')) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['public_id']);
            $blueprint->dropColumn('public_id');
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
