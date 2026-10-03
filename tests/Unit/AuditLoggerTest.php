<?php

use App\Support\Audit\AuditLogger;

it('masks sensitive keys at any depth', function () {
    $masked = (new AuditLogger)->mask([
        'name' => 'Deniz',
        'password' => 'gizli',
        'payout' => ['IBAN' => 'TR00', 'bank' => 'Örnek'],
    ]);

    expect($masked)->toBe([
        'name' => 'Deniz',
        'password' => '[gizli]',
        'payout' => ['IBAN' => '[gizli]', 'bank' => 'Örnek'],
    ]);
});
