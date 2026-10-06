<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Log kayıtlarının bağlamındaki (context/extra) hassas alanları maskeler: şifre,
 * IBAN, hesap ve vergi numarası, token ve sırlar. Mesaj metnindeki IBAN benzeri
 * diziler de kısaltılır.
 */
class MaskSensitiveData
{
    private const KEYS = [
        'password', 'password_confirmation', 'current_password', 'iban', 'account_number', 'routing_number',
        'tax_id', 'foreign_tin', 'us_tin', 'payout_snapshot', 'token', 'secret', 'api_key', 'access_token',
        'authorization', 'cookie', 'two_factor_secret', 'two_factor_recovery_codes', 'cf-turnstile-response',
    ];

    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if (method_exists($handler, 'pushProcessor')) {
                $handler->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
                    message: self::maskText($record->message),
                    context: self::mask($record->context),
                    extra: self::mask($record->extra),
                ));
            }
        }
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    public static function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::KEYS, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = self::mask($value);
            } elseif (is_string($value)) {
                $data[$key] = self::maskText($value);
            }
        }

        return $data;
    }

    public static function maskText(string $text): string
    {
        return (string) preg_replace_callback('/\b([A-Z]{2}\d{2})(?: ?[A-Z0-9]){11,30}\b/', fn (array $m): string => $m[1].'****', $text);
    }
}
