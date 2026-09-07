<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signals', function (Blueprint $table): void {
            $table->string('title')->nullable()->after('type');
            $table->json('payload')->nullable()->after('body');
        });

        DB::table('signals')
            ->whereIn('type', ['quote', 'images', 'video', 'link'])
            ->update(['type' => 'drop']);

        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('signal_id')->constrained('signals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('option_id');
            $table->timestamps();

            $table->unique(['signal_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');

        Schema::table('signals', function (Blueprint $table): void {
            $table->dropColumn(['title', 'payload']);
        });
    }
};
