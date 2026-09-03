<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FlattenPublicStorageCommand extends Command
{
    protected $signature = 'storage:webroot';

    protected $description = 'Replace the public/storage symlink with a real directory so Apache can serve uploads on shared hosting.';

    public function handle(): int
    {
        $web = public_path('storage');
        $source = storage_path('app/public');

        File::ensureDirectoryExists($source, 0755);

        if (is_link($web)) {
            File::delete($web);
            $this->info('Removed the public/storage symlink.');
        }

        File::ensureDirectoryExists($web, 0755);
        File::copyDirectory($source, $web);

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($web, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        chmod($web, 0755);

        foreach ($items as $item) {
            chmod($item->getPathname(), $item->isDir() ? 0755 : 0644);
        }

        $this->info('Uploads are in public/storage and Apache can serve them without a symlink.');

        return self::SUCCESS;
    }
}
