<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Чтение значений ячеек Excel без создания лишних объектов.
 *
 * Использование cellExists() перед getCell() не порождает миллионы пустых ячеек в
 * файлах с оформлением (границы, заливки) за пределами данных. Формульные ячейки
 * читаются по последнему вычисленному значению.
 */
class ExcelValue
{
    /**
     * Возвращает значение ячейки по координатам (1-индексация), null для пустой.
     *
     * @param  Worksheet  $sheet  Лист книги.
     * @param  int        $column Номер колонки (начиная с 1).
     * @param  int        $row    Номер строки (начиная с 1).
     * @return mixed Сырое значение ячейки.
     */
    public static function cell(Worksheet $sheet, int $column, int $row): mixed
    {
        if (! $sheet->cellExists([$column, $row])) {
            return null;
        }
        $cell = $sheet->getCell([$column, $row]);

        return $cell->getDataType() === 'f' ? $cell->getOldCalculatedValue() : $cell->getValue();
    }

    /**
     * Преобразует значение в обрезанную строку (раскрывает RichText).
     *
     * @param  mixed  $value Значение ячейки.
     * @return string Текстовое представление без ведущих/замыкающих пробелов.
     */
    public static function text(mixed $value): string
    {
        return trim($value instanceof RichText ? $value->getPlainText() : (string) ($value ?? ''));
    }

    /**
     * Преобразует значение в дату Y-m-d.
     *
     * Поддерживает DateTimeInterface, Excel-серийный номер даты и текстовые форматы
     * «YYYY-MM-DD» и «DD.MM.YYYY». Некорректные даты (например 31.02.2026) дают null.
     *
     * @param  mixed  $value Значение ячейки.
     * @return string|null Дата в формате Y-m-d или null.
     */
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

    /**
     * Преобразует значение в пару [время_начала, время_окончания].
     *
     * Числовой Excel-формат (доля суток) переводится в HH:MM:SS. Текст разбирается
     * регулярным выражением, извлекающим до двух значений времени.
     *
     * @param  mixed  $value Значение ячейки.
     * @return array{0: string|null, 1: string|null} Два времени или null-элементы.
     */
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
