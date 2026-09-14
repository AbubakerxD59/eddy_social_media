<?php

use App\Support\Usd;

test('usd display prefixes and converts amounts to dollars', function () {
    expect(Usd::display('5000'))->toBe('$5000')
        ->and(Usd::display('$500 - $1,000 / project'))->toBe('$500 - $1,000 / project')
        ->and(Usd::display('€2,500'))->toBe('$2,500')
        ->and(Usd::display('£250K'))->toBe('$250K')
        ->and(Usd::display('USD 90'))->toBe('$90')
        ->and(Usd::display('Budget on request'))->toBe('Budget on request')
        ->and(Usd::display(''))->toBeNull()
        ->and(Usd::fromCents(20000))->toBe('$200')
        ->and(Usd::fromCents(15050))->toBe('$150.50');
});
