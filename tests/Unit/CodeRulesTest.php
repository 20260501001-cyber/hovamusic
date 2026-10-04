<?php

use App\Rules\Isrc;
use App\Rules\Upc;
use Illuminate\Support\Facades\Validator;

it('accepts UPC-A and EAN-13 codes with a valid check digit', function (string $code) {
    expect(Upc::hasValidCheckDigit($code))->toBeTrue()
        ->and(Validator::make(['upc' => $code], ['upc' => [new Upc]])->passes())->toBeTrue();
})->with(['036000291452', '4006381333931']);

it('explains why a UPC is rejected', function (string $code, string $message) {
    $validator = Validator::make(['upc' => $code], ['upc' => [new Upc]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('upc'))->toBe($message);
})->with([
    ['036000291453', 'UPC\'nin kontrol hanesi tutmuyor; kodu kontrol et.'],
    ['12345', 'UPC 5 hane; 12 ya da 13 haneli olmalı.'],
]);

it('normalizes ISRC codes', function () {
    expect(Isrc::normalize('tr-a1b-26-00001'))->toBe('TRA1B2600001')
        ->and(Isrc::normalize('TR A1B 26 00001'))->toBe('TRA1B2600001')
        ->and(Isrc::normalize('TRA1B260001'))->toBeNull()
        ->and(Isrc::normalize('1RA1B2600001'))->toBeNull();
});
