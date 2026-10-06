<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Недельная сетка: отдельная запись для каждой группы и ячейки занятия. */
class WeeklyScheduleParser
{
    public function parse(Worksheet $sheet): array
    {
        $maxColumn = min(150, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        $groups = [];
        $headerRow = 0;
        $dayColumn = 1;
        $timeColumn = 2;
        for ($row = 1; $row <= min(12, $sheet->getHighestDataRow()); $row++) {
            for ($column = 1; $column <= $maxColumn; $column++) {
                $value = ExcelValue::text(ExcelValue::cell($sheet, $column, $row));
                if (preg_match('/дни.*нед/ui', $value)) {
                    $dayColumn = $column;
                }
                if (preg_match('/^(часы|время)$/ui', $value)) {
                    $timeColumn = $column;
                }
                if (preg_match('/^\d-[А-ЯЁ]+-\d+/ui', $value)) {
                    $groups[$column] = ['code' => $value, 'day' => $dayColumn, 'time' => $timeColumn];
                    $headerRow = $row;
                }
            }
            if ($groups) {
                break;
            }
        }
        if (! $groups) {
            return [];
        }
        $records = [];
        $days = [];
        $times = [];
        $mergedCells = $this->mergedCells($sheet, $maxColumn);
        for ($row = $headerRow + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $newGroups = $this->header($sheet, $row, $maxColumn);
            if ($newGroups) {
                // Курсы могут быть размещены блоками друг под другом на одном листе.
                $groups = $newGroups;
                $days = [];
                $times = [];

                continue;
            }
            foreach ($groups as $column => $group) {
                $day = ExcelValue::text($this->value($sheet, $group['day'], $row, $mergedCells));
                $time = ExcelValue::time($this->value($sheet, $group['time'], $row, $mergedCells));
                if (preg_match('/^(понедельник|вторник|среда|четверг|пятница|суббота|воскресенье)$/ui', $day)) {
                    $days[$group['day']] = mb_strtolower($day);
                }
                if ($time[0]) {
                    $times[$group['time']] = $time;
                }
                $text = ExcelValue::text($this->value($sheet, $column, $row, $mergedCells));
                if ($text === '' || empty($times[$group['time']]) || empty($days[$group['day']])) {
                    continue;
                }
                if (preg_match('/^(начальник|директор|ведущий документовед|примечание|согласовано|утверждаю)/ui', $text)) {
                    continue;
                }
                $roomColumn = ($mergedCells[$row][$column]['endColumn'] ?? $column) + 1;
                $rooms = ExcelValue::text($this->value($sheet, $roomColumn, $row, $mergedCells));
                preg_match('/[А-ЯЁ][а-яё-]+\s+[А-ЯЁ]\.\s*[А-ЯЁ]\./u', $text, $teacher);
                $lessonType = str_contains($text, 'лекц') ? 'лекция' : (str_contains($text, 'прак') ? 'практика' : (str_contains($text, 'лаб') ? 'лабораторная' : null));
                [$start, $end] = $times[$group['time']];
                // В сетке нет календарных дат. Не подставляем сегодняшнюю дату.
                $records[] = ['date' => null, 'day_of_week' => $days[$group['day']] ?? null, 'group_code' => $group['code'],
                    'time_start' => $start, 'time_end' => $end, 'pair_number' => app(ScheduleParser::class)->pair($start),
                    'discipline' => $text, 'examiner' => $teacher[0] ?? null, 'classrooms_raw' => $rooms ?: null,
                    'lesson_type' => $lessonType, 'is_occupied' => 1, 'transfer_cancel' => 'нет'];
            }
        }

        return $records;
    }

    private function header(Worksheet $sheet, int $row, int $maxColumn): array
    {
        $groups = [];
        $dayColumn = 1;
        $timeColumn = 2;
        $hasTimeLabel = false;
        for ($column = 1; $column <= $maxColumn; $column++) {
            $value = ExcelValue::text(ExcelValue::cell($sheet, $column, $row));
            if (preg_match('/дни.*нед/ui', $value)) {
                $dayColumn = $column;
            }
            if (preg_match('/^(часы|время)$/ui', $value)) {
                $timeColumn = $column;
                $hasTimeLabel = true;
            }
            if (preg_match('/^\d-[А-ЯЁ]+-\d+/ui', $value)) {
                $groups[$column] = ['code' => $value, 'day' => $dayColumn, 'time' => $timeColumn];
            }
        }

        return $hasTimeLabel ? $groups : [];
    }

    private function value(Worksheet $sheet, int $column, int $row, array $mergedCells): mixed
    {
        $origin = $mergedCells[$row][$column] ?? ['column' => $column, 'row' => $row];

        return ExcelValue::cell($sheet, $origin['column'], $origin['row']);
    }

    private function mergedCells(Worksheet $sheet, int $maxColumn): array
    {
        $cells = []; // Координаты объединённой ячейки => её верхний левый угол.
        foreach ($sheet->getMergeCells() as $range) {
            [$start, $end] = Coordinate::rangeBoundaries($range);
            if (($end[0] - $start[0] + 1) * ($end[1] - $start[1] + 1) > 10000) {
                continue;
            }
            for ($row = $start[1]; $row <= $end[1]; $row++) {
                for ($column = $start[0]; $column <= min($end[0], $maxColumn); $column++) {
                    $cells[$row][$column] = ['column' => $start[0], 'row' => $start[1], 'endColumn' => $end[0]];
                }
            }
        }

        return $cells;
    }
}
