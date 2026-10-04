<?php

namespace App\Support\Locale;

use Collator;
use Locale;

/**
 * Yayın ve parça dili seçenekleri: ISO 639-1 kodları, adları PHP intl ile Türkçe.
 * "zxx" sözsüz (enstrümantal) içerik içindir.
 */
class Languages
{
    public const INSTRUMENTAL = 'zxx';

    private const CODES = [
        'aa', 'ab', 'ae', 'af', 'ak', 'am', 'an', 'ar', 'as', 'av', 'ay', 'az', 'ba', 'be', 'bg', 'bi',
        'bm', 'bn', 'bo', 'br', 'bs', 'ca', 'ce', 'ch', 'co', 'cr', 'cs', 'cu', 'cv', 'cy', 'da', 'de',
        'dv', 'dz', 'ee', 'el', 'en', 'eo', 'es', 'et', 'eu', 'fa', 'ff', 'fi', 'fj', 'fo', 'fr', 'fy',
        'ga', 'gd', 'gl', 'gn', 'gu', 'gv', 'ha', 'he', 'hi', 'ho', 'hr', 'ht', 'hu', 'hy', 'hz', 'ia',
        'id', 'ie', 'ig', 'ii', 'ik', 'io', 'is', 'it', 'iu', 'ja', 'jv', 'ka', 'kg', 'ki', 'kj', 'kk',
        'kl', 'km', 'kn', 'ko', 'kr', 'ks', 'ku', 'kv', 'kw', 'ky', 'la', 'lb', 'lg', 'li', 'ln', 'lo',
        'lt', 'lu', 'lv', 'mg', 'mh', 'mi', 'mk', 'ml', 'mn', 'mr', 'ms', 'mt', 'my', 'na', 'nb', 'nd',
        'ne', 'ng', 'nl', 'nn', 'no', 'nr', 'nv', 'ny', 'oc', 'oj', 'om', 'or', 'os', 'pa', 'pi', 'pl',
        'ps', 'pt', 'qu', 'rm', 'rn', 'ro', 'ru', 'rw', 'sa', 'sc', 'sd', 'se', 'sg', 'si', 'sk', 'sl',
        'sm', 'sn', 'so', 'sq', 'sr', 'ss', 'st', 'su', 'sv', 'sw', 'ta', 'te', 'tg', 'th', 'ti', 'tk',
        'tl', 'tn', 'to', 'tr', 'ts', 'tt', 'tw', 'ty', 'ug', 'uk', 'ur', 'uz', 've', 'vi', 'vo', 'wa',
        'wo', 'xh', 'yi', 'yo', 'za', 'zh', 'zu',
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        static $options = null;

        if ($options !== null) {
            return $options;
        }

        $names = [];

        foreach (self::CODES as $code) {
            $names[$code] = mb_convert_case(Locale::getDisplayLanguage($code, 'tr') ?: $code, MB_CASE_TITLE, 'UTF-8');
        }

        (new Collator('tr_TR'))->asort($names);

        return $options = [self::INSTRUMENTAL => __('release.languages.instrumental')] + $names;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return [self::INSTRUMENTAL, ...self::CODES];
    }

    public static function name(?string $code): ?string
    {
        return $code === null ? null : (self::options()[$code] ?? $code);
    }
}
