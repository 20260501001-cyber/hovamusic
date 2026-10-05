<?php

namespace App\Domain\Finance;

use App\Models\ReportMapping;
use DateTimeInterface;
use Generator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Believe raporunu (CSV ya da XLSX) satır satır okur; tüm dosyayı belleğe almaz.
 * Başlık satırındaki sütunlar eşleştirme profiline göre alan adlarına çevrilir.
 */
class ReportReader
{
    /**
     * @return Generator<int, array{row: int, values: array<string, mixed>, raw: array<string, mixed>}>
     *
     * @throws ReportUnreadable
     */
    public function rows(string $path, string $extension, ReportMapping $mapping): Generator
    {
        $reader = $this->reader($path, $extension, $mapping);
        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $index = [];

                foreach ($sheet->getRowIterator() as $number => $row) {
                    $cells = array_map(fn ($value) => $this->scalar($value), $row->toArray());

                    if ($number < $mapping->header_row) {
                        continue;
                    }

                    if ($headers === null) {
                        $headers = array_map(fn ($value): string => trim((string) $value), $cells);
                        $index = $this->index($headers, $mapping);

                        continue;
                    }

                    if (count(array_filter($cells, fn ($value): bool => $value !== null && $value !== '')) === 0) {
                        continue;
                    }

                    $raw = [];
                    foreach ($headers as $position => $header) {
                        if ($header !== '') {
                            $raw[$header] = $cells[$position] ?? null;
                        }
                    }

                    $values = [];
                    foreach ($index as $field => $position) {
                        $values[$field] = $cells[$position] ?? null;
                    }

                    yield ['row' => $number, 'values' => $values, 'raw' => $raw];
                }

                // Yalnızca ilk sayfa okunur.
                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     *
     * @throws ReportUnreadable
     */
    private function index(array $headers, ReportMapping $mapping): array
    {
        $normalized = array_map(fn (string $header): string => $this->normalize($header), $headers);
        $index = [];

        foreach ((array) $mapping->columns as $field => $column) {
            if (! is_string($column) || trim($column) === '') {
                continue;
            }

            $position = array_search($this->normalize($column), $normalized, true);

            if ($position !== false) {
                $index[$field] = (int) $position;
            }
        }

        if (! isset($index['net_amount']) || (! isset($index['isrc']) && ! isset($index['upc']))) {
            throw new ReportUnreadable(__('finance.import.errors.columns', ['columns' => implode(', ', array_filter($headers))]));
        }

        return $index;
    }

    private function reader(string $path, string $extension, ReportMapping $mapping): CsvReader|XlsxReader
    {
        if (in_array(strtolower($extension), ['xlsx', 'xlsm'], true)) {
            return new XlsxReader;
        }

        $options = new CsvOptions;
        $options->FIELD_DELIMITER = $mapping->delimiter === 'auto' ? $this->detectDelimiter($path) : ($mapping->delimiter === 'tab' ? "\t" : $mapping->delimiter);

        return new CsvReader($options);
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        $sample = $handle ? (string) fread($handle, 8192) : '';

        if ($handle) {
            fclose($handle);
        }

        $line = strtok($sample, "\n") ?: '';
        $counts = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t"), '|' => substr_count($line, '|')];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function scalar(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value)) {
            // Excel sayıları float gelir; hesaplar ondalık metinle devam eder.
            return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
        }

        return is_string($value) ? trim($value) : $value;
    }
}
