<?php

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('public_id', 8)->nullable()->after('id');
        });

        Conversation::query()->whereNull('public_id')->each(function (Conversation $conversation): void {
            $conversation->forceFill(['public_id' => Conversation::generatePublicId()])->save();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unique('public_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->string('public_id', 8)->nullable()->after('id');
        });

        Message::withTrashed()->whereNull('public_id')->each(function (Message $message): void {
            $message->forceFill(['public_id' => Message::generatePublicId()])->save();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
