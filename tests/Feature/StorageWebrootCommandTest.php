<?php

use Illuminate\Support\Facades\Artisan;

test('the storage webroot command is registered', function () {
    expect(array_key_exists('storage:webroot', Artisan::all()))->toBeTrue();
});
