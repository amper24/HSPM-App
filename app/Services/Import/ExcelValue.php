<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Чтение пустых координат не создаёт миллионы пустых ячеек в оформленных файлах. */
class ExcelValue
{
    public static function cell(Worksheet $sheet, int $column, int $row): mixed
    {
        if (! $sheet->cellExists([$column, $row])) {
            return null;
        }
        $cell = $sheet->getCell([$column, $row]);

        return $cell->getDataType() === 'f' ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    public static function text(mixed $value): string
    {
        return trim($value instanceof RichText ? $value->getPlainText() : (string) ($value ?? ''));
    }

    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_numeric($value) && $value > 1) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $text = self::text($value);
        foreach (['/\d{4}-\d{2}-\d{2}/' => 'Y-m-d', '/\d{2}\.\d{2}\.\d{4}/' => 'd.m.Y'] as $pattern => $format) {
            if (preg_match($pattern, $text, $match)) {
                $date = \DateTimeImmutable::createFromFormat('!'.$format, $match[0]);
                if ($date && $date->format($format) === $match[0]) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return null;
    }

    public static function time(mixed $value): array
    {
        if (is_numeric($value) && $value >= 0 && $value < 1) {
            $minutes = (int) round($value * 1440) % 1440;

            return [sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60), null];
        }
        preg_match_all('/(?<!\d)(\d{1,2})[:.](\d{2})(?!\d)/', self::text($value), $matches, PREG_SET_ORDER);
        $times = [];
        foreach ($matches as $match) {
            if ((int) $match[1] < 24 && (int) $match[2] < 60) {
                $times[] = sprintf('%02d:%02d:00', $match[1], $match[2]);
            }
        }

        return [$times[0] ?? null, $times[1] ?? null];
    }
}
