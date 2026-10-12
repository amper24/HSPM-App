<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Нормализация значений из исходных форм учебного отдела.
 *
 * Набор чистых функций без состояния и без обращения к БД: приводят названия
 * кафедр, форм занятости, ФИО и заголовки к каноническому виду.
 */
class ValueNormalizer
{
    /**
     * Определяет, является ли строка заголовком кафедры в списке контактов.
     *
     * @param  string  $fullName Имя из ячейки.
     * @return bool true, если строка — заголовок кафедры (не преподаватель).
     */
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

    /**
     * Канонизирует строку для сравнения ключей (нижний регистр, одиночные пробелы).
     *
     * @param  string  $text Исходная строка.
     * @return string Нормализованная строка.
     */
    public static function normalizeKey(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $text)), 'UTF-8');
    }

    /**
     * Разбивает ФИО на [фамилия, имя, отчество].
     *
     * Всё, что после второго пробела, считается отчеством (включая составные части).
     *
     * @param  string  $fullName Полное имя.
     * @return array{0: string, 1: string, 2: string}
     */
    public static function parseFIO(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName));

        return [$parts[0] ?? '', $parts[1] ?? '', isset($parts[2]) ? implode(' ', array_slice($parts, 2)) : ''];
    }

    /**
     * Приводит произвольную формулировку занятости к каноническому значению.
     *
     * @param  string  $raw Исходный текст (например «внешний совместитель», «почасовая»).
     * @return string Каноническая форма занятости.
     */
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

    /**
     * Приводит сокращения кафедр к полному имени.
     *
     * @param  string  $raw Исходное название/аббревиатура кафедры.
     * @return string Каноническое название кафедры.
     */
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

    /**
     * Преобразует индекс колонки в её буквенное имя (1 => A).
     *
     * @param  int  $index Индекс колонки, начиная с 1.
     * @return string Буквенное имя колонки.
     */
    public static function colLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    /**
     * Разбирает заголовок колонки ПО: номер аудитории и корпус.
     *
     * @param  string  $header Заголовок колонки, например «101 взн» или «205».
     * @return array{room_number: string, building: string}|null Аудитория или null,
     *         если заголовок не начинается с числа.
     */
    public static function parseRoomHeader(string $header): ?array
    {
        if (! preg_match('/^(\d+)/', $header, $match)) {
            return null;
        }

        return ['room_number' => $match[1], 'building' => (mb_stripos($header, 'взн') !== false) ? 'В' : 'Д'];
    }
}
