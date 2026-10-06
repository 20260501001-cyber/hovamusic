<?php

use App\Domain\Finance\ReportValueParser;
use App\Rules\Iban;
use App\Rules\SwiftBic;
use Illuminate\Support\Facades\Validator;

it('validates IBAN length per country and the mod-97 check digits', function (string $iban, bool $valid) {
    expect(Iban::isValid(Iban::normalize($iban)))->toBe($valid);
})->with([
    'turkish' => ['TR33 0006 1005 1978 6457 8413 26', true],
    'german' => ['de89-3704-0044-0532-0130-00', true],
    'british' => ['GB82WEST12345698765432', true],
    'wrong check digit' => ['TR330006100519786457841327', false],
    'wrong length for country' => ['TR33000610051978645784132', false],
    'not an iban' => ['12345678', false],
]);

it('matches the IBAN to the selected bank country', function () {
    $validator = Validator::make(['iban' => 'DE89370400440532013000'], ['iban' => [new Iban('TR')]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('iban'))->toBe(__('finance.payout_page.errors.iban_country'));
});

it('validates SWIFT/BIC codes', function (string $code, bool $valid) {
    expect(Validator::make(['swift' => $code], ['swift' => [new SwiftBic]])->passes())->toBe($valid);
})->with([
    ['TGBATRIS', true],
    ['tgbatrisxxx', true],
    ['TGBATRI', false],
    ['TGBA1RIS', false],
]);

it('reads amounts with either decimal separator', function (string|int $raw, ?string $expected) {
    $amount = (new ReportValueParser)->amount($raw);

    expect($amount === null ? null : (string) $amount)->toBe($expected);
})->with([
    ['1.234,56', '1234.56'],
    ['1,234.56', '1234.56'],
    ['12,5', '12.5'],
    ['1,234,567', '1234567'],
    ['−3,25 €', '-3.25'],
    ['0.000123456789', '0.000123456789'],
    [42, '42'],
    ['', null],
    ['-', null],
]);

it('honours a fixed decimal separator from the mapping', function () {
    expect((string) (new ReportValueParser(','))->amount('1.234'))->toBe('1234')
        ->and((string) (new ReportValueParser('.'))->amount('1,234'))->toBe('1234');
});

it('reads sales months in the formats distributors use', function (string|int $raw, ?string $expected) {
    expect((new ReportValueParser)->month($raw)?->format('Y-m'))->toBe($expected);
})->with([
    ['2026-07', '2026-07'],
    ['2026-07-31', '2026-07'],
    ['07/2026', '2026-07'],
    ['31.07.2026', '2026-07'],
    ['Jul 2026', '2026-07'],
    ['46234', '2026-07'],
    ['ay değil', null],
]);

it('normalises ISRC and UPC codes', function () {
    $parser = new ReportValueParser;

    expect($parser->isrc('tr-abc-26-00001'))->toBe('TRABC2600001')
        ->and($parser->isrc('geçersiz'))->toBeNull()
        ->and($parser->upc('8721234567894'))->toBe('8721234567894')
        ->and($parser->upc(17))->toBe('000000000017')
        ->and($parser->upc(''))->toBeNull()
        ->and($parser->quantity('2.9'))->toBe(2);
});
