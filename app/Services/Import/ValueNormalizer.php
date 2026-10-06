<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Преобразования справочников из исходных форм учебного отдела. */
class ValueNormalizer
{
    public static function isDepartmentHeader(string $fullName): bool
    {
        $normalizedName = mb_strtolower(trim($fullName), 'UTF-8');
        foreach (['кафедра', 'физическая культура', 'живопись и рисунок', 'общевузовская'] as $keyword) {
            if (mb_strpos($normalizedName, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeKey(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)), 'UTF-8');
    }

    public static function parseFIO(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));

        return [$parts[0] ?? '', $parts[1] ?? '', isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : ''];
    }

    public static function normalizeEmploymentType(string $raw): string
    {
        $raw = mb_strtolower(trim($raw), 'UTF-8');
        if (strpos($raw, 'внеш') !== false && strpos($raw, 'совм') !== false) {
            return 'внешний совместитель';
        }
        if (strpos($raw, 'внут') !== false && strpos($raw, 'совм') !== false) {
            return 'внутренний совместитель';
        }
        if (strpos($raw, 'гпх') !== false) {
            return 'ГПХ';
        }
        if (strpos($raw, 'почас') !== false) {
            return 'ГПХ';
        }
        if (strpos($raw, 'штат') !== false || $raw === 'шт.') {
            return 'штатный';
        }

        return $raw !== '' ? $raw : '';
    }

    public static function normalizeDepartment(string $raw): string
    {
        $map = ['ЖиМ СМИ' => 'ЖиМ СМИ', 'ГиСЭД' => 'ГиСЭД', 'Графика' => 'Графика', 'КиКТ' => 'КиКТ', 'Реклама' => 'Реклама', 'ТПиПК' => 'ТПиПК', 'ТПП' => 'ТПП', 'ИиУС' => 'ИиУС', 'ПОиУ' => 'ПОиУ'];
        foreach ($map as $alias => $department) {
            if (mb_stripos($raw, $alias) !== false) {
                return $department;
            }
        }

        return trim($raw);
    }

    public static function colLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    public static function parseRoomHeader(string $header): ?array
    {
        if (! preg_match('/^(\d+)/', $header, $match)) {
            return null;
        }

        return ['room_number' => $match[1], 'building' => (mb_stripos($header, 'взн') !== false) ? 'В' : 'Д'];
    }
}
