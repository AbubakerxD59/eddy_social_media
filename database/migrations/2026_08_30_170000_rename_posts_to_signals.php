<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('posts')) {
            return;
        }

        if (Schema::hasTable('posts') && ! Schema::hasTable('signals')) {
            $this->dropForeigns('posts', 'parent_id');
            $this->dropForeigns('post_media', 'post_id');
            $this->dropForeigns('post_likes', 'post_id');
            Schema::rename('posts', 'signals');
        }

        if (Schema::hasTable('signals') && Schema::hasColumn('signals', 'parent_id') && ! $this->hasForeign('signals', 'parent_id')) {
            Schema::table('signals', function (Blueprint $table): void {
                $table->foreign('parent_id')->references('id')->on('signals')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('post_media') && ! Schema::hasTable('signal_media')) {
            $this->dropForeigns('post_media', 'post_id');
            Schema::rename('post_media', 'signal_media');
        }

        if (Schema::hasTable('signal_media') && Schema::hasColumn('signal_media', 'post_id')) {
            $this->dropForeigns('signal_media', 'post_id');
            Schema::table('signal_media', function (Blueprint $table): void {
                $table->renameColumn('post_id', 'signal_id');
            });
        }

        if (Schema::hasTable('signal_media') && Schema::hasColumn('signal_media', 'signal_id') && ! $this->hasForeign('signal_media', 'signal_id')) {
            Schema::table('signal_media', function (Blueprint $table): void {
                $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('post_likes') && ! Schema::hasTable('signal_likes')) {
            $this->dropForeigns('post_likes', 'post_id');
            Schema::rename('post_likes', 'signal_likes');
        }

        if (Schema::hasTable('signal_likes') && Schema::hasColumn('signal_likes', 'post_id')) {
            $this->dropForeigns('signal_likes', 'post_id');
            Schema::table('signal_likes', function (Blueprint $table): void {
                $table->renameColumn('post_id', 'signal_id');
            });
        }

        if (Schema::hasTable('signal_likes') && Schema::hasColumn('signal_likes', 'signal_id') && ! $this->hasForeign('signal_likes', 'signal_id')) {
            Schema::table('signal_likes', function (Blueprint $table): void {
                $table->foreign('signal_id')->references('id')->on('signals')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('signal_media')) {
            DB::table('signal_media')
                ->where('path', 'like', 'posts/%')
                ->orderBy('id')
                ->each(function (object $media): void {
                    DB::table('signal_media')
                        ->where('id', $media->id)
                        ->update([
                            'path' => preg_replace('/^posts\//', 'signals/', (string) $media->path),
                        ]);
                });
        }

        $from = Storage::disk('public')->path('posts');
        $to = Storage::disk('public')->path('signals');

        if (is_dir($from) && ! is_dir($to)) {
            rename($from, $to);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('signals') && ! Schema::hasTable('posts')) {
            $this->dropForeigns('signal_media', 'signal_id');
            $this->dropForeigns('signal_likes', 'signal_id');
            $this->dropForeigns('signals', 'parent_id');
            Schema::rename('signals', 'posts');
        }

        if (Schema::hasTable('signal_media') && ! Schema::hasTable('post_media')) {
            Schema::rename('signal_media', 'post_media');
        }

        if (Schema::hasTable('post_media') && Schema::hasColumn('post_media', 'signal_id')) {
            $this->dropForeigns('post_media', 'signal_id');
            Schema::table('post_media', function (Blueprint $table): void {
                $table->renameColumn('signal_id', 'post_id');
            });
        }

        if (Schema::hasTable('post_media') && Schema::hasColumn('post_media', 'post_id') && ! $this->hasForeign('post_media', 'post_id')) {
            Schema::table('post_media', function (Blueprint $table): void {
                $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('signal_likes') && ! Schema::hasTable('post_likes')) {
            Schema::rename('signal_likes', 'post_likes');
        }

        if (Schema::hasTable('post_likes') && Schema::hasColumn('post_likes', 'signal_id')) {
            $this->dropForeigns('post_likes', 'signal_id');
            Schema::table('post_likes', function (Blueprint $table): void {
                $table->renameColumn('signal_id', 'post_id');
            });
        }

        if (Schema::hasTable('post_likes') && Schema::hasColumn('post_likes', 'post_id') && ! $this->hasForeign('post_likes', 'post_id')) {
            Schema::table('post_likes', function (Blueprint $table): void {
                $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('posts') && Schema::hasColumn('posts', 'parent_id') && ! $this->hasForeign('posts', 'parent_id')) {
            Schema::table('posts', function (Blueprint $table): void {
                $table->foreign('parent_id')->references('id')->on('posts')->cascadeOnDelete();
            });
        }
    }

    private function dropForeigns(string $table, string $column): void
    {
        foreach ($this->foreignNames($table, $column) as $name) {
            DB::statement("alter table `{$table}` drop foreign key `{$name}`");
        }
    }

    private function hasForeign(string $table, string $column): bool
    {
        return $this->foreignNames($table, $column) !== [];
    }

    /**
     * @return list<string>
     */
    private function foreignNames(string $table, string $column): array
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return collect(DB::select(
            'select constraint_name as name from information_schema.key_column_usage
             where table_schema = database()
             and table_name = ?
             and column_name = ?
             and referenced_table_name is not null',
            [$table, $column],
        ))->pluck('name')->filter()->values()->all();
    }
};
