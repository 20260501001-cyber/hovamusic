<?php

use App\Filament\Resources\AdminAllowedIps\AdminAllowedIpResource;

it('validates IP addresses and CIDR ranges', function (string $value, bool $valid) {
    expect(AdminAllowedIpResource::isValidRange($value))->toBe($valid);
})->with([
    ['203.0.113.10', true],
    ['203.0.113.0/24', true],
    ['2001:db8::/32', true],
    ['203.0.113.0/33', false],
    ['300.1.1.1', false],
    ['ofis', false],
]);
