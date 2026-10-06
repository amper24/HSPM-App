<?php

namespace App\Services\Import;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Табличные сессии и недельные сетки разбираются отдельно; SQL здесь отсутствует. */
class ScheduleParser
{
    public function parse(Spreadsheet $book): array
    {
        $records = [];
        foreach ($book->getWorksheetIterator() as $sheet) {
            if (preg_match('/перенос.*занят/ui', ExcelValue::text(ExcelValue::cell($sheet, 1, 1)))) {
                throw ValidationException::withMessages(['file' => 'Журнал переносов содержит диапазоны дат и групп. Его нельзя загрузить как снимок расписания. Измените соответствующие занятия в разделе «Расписание».']);
            }
            if ($sheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                continue;
            }
            $rows = $this->tabular($sheet);
            array_push($records, ...($rows ?? app(WeeklyScheduleParser::class)->parse($sheet)));
        }

        return $records;
    }

    private function tabular(Worksheet $sheet): ?array
    {
        $columns = min(60, Coordinate::columnIndexFromString($sheet->getHighestDataColumn()));
        $map = [];
        $headerRow = null;
        for ($row = 1; $row <= min(15, $sheet->getHighestDataRow()); $row++) {
            $candidate = [];
            for ($column = 1; $column <= $columns; $column++) {
                $field = $this->header(ExcelValue::text(ExcelValue::cell($sheet, $column, $row)));
                if ($field) {
                    $candidate[$column] = $field;
                }
            }
            if (in_array('discipline', $candidate) && in_array('date', $candidate)) {
                $map = $candidate;
                $headerRow = $row;
                break;
            }
        }
        if ($headerRow === null) {
            return null;
        }
        $records = [];
        for ($row = $headerRow + 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $record = ['is_occupied' => 1, 'transfer_cancel' => 'нет'];
            foreach ($map as $column => $field) {
                $value = ExcelValue::cell($sheet, $column, $row);
                if (in_array($field, ['date', 'session_start', 'session_end'])) {
                    $record[$field] = ExcelValue::date($value);
                } elseif ($field === 'time') {
                    [$record['time_start'], $record['time_end']] = ExcelValue::time($value);
                } elseif (in_array($field, ['time_start', 'time_end'])) {
                    $record[$field] = ExcelValue::time($value)[0];
                } else {
                    $record[$field] = ExcelValue::text($value) ?: null;
                }
            }
            if (empty($record['discipline']) && empty($record['examiner']) && empty($record['group_code'])) {
                continue;
            }
            if ($this->isFooter($record['discipline'] ?? '')) {
                continue;
            }
            // Экспорт недельных занятий имеет пустую дату; день недели сохраняется.
            if (empty($record['date']) && empty($record['day_of_week'])) {
                continue;
            }
            if (! empty($record['building']) && preg_match('/^\d/', $record['classrooms_raw'] ?? '')) {
                $record['classrooms_raw'] = $record['building'].$record['classrooms_raw'];
            }
            unset($record['building']);
            $examType = mb_strtolower($record['exam_type'] ?? '');
            if (str_contains($examType, 'конс')) {
                $record['exam_type'] = 'консультация';
            } elseif (str_contains($examType, 'экз')) {
                $record['exam_type'] = 'экзамен';
            }
            $record['pair_number'] = isset($record['pair_number']) ? (int) $record['pair_number'] : $this->pair($record['time_start'] ?? null);
            $record['is_occupied'] = (int) ($record['is_occupied'] ?? 1);
            $record['is_nonstandard_time'] = (int) ($record['is_nonstandard_time'] ?? 0);
            $records[] = $record;
        }

        return $records;
    }

    private function header(string $header): ?string
    {
        $header = mb_strtolower(preg_replace('/[\s\-]+/u', '', $header));
        $exact = ['датa' => 'date', 'деньнедели' => 'day_of_week', 'числитель/знаменатель' => 'numerator_denominator', 'номерпары' => 'pair_number', 'занята' => 'is_occupied', 'нестандартноевремя' => 'is_nonstandard_time', 'времяначала' => 'time_start', 'времяокончания' => 'time_end', 'корпус' => 'building', 'должность' => 'teacher_position'];
        if (isset($exact[$header])) {
            return $exact[$header];
        }
        $patterns = [
            '/^(дата|date)/u' => 'date', '/^(время|time)/u' => 'time', '/^(группа|groupcode|шифр)/u' => 'group_code',
            '/^(дисцип|discipline)/u' => 'discipline', '/^(экзаменатор|преподаватель|examiner|фио)/u' => 'examiner',
            '/^(видзан|типзан|lessontype)/u' => 'lesson_type', '/^(экз|конс|examtype)/u' => 'exam_type',
            '/^(ауд|room|classroom)/u' => 'classrooms_raw', '/^(перенос|отмена|transfer)/u' => 'transfer_cancel',
            '/^(каф.*груп|закрепл|groupdep)/u' => 'group_department', '/^(каф.*преп|teacherdep)/u' => 'teacher_department',
            '/^(долж.*преп|teacherpos)/u' => 'teacher_position', '/^(сроки.*начало|сессияс|sessionstart)/u' => 'session_start',
            '/^(сроки.*окончание|сессияпо|sessionend)/u' => 'session_end',
        ];
        foreach ($patterns as $pattern => $field) {
            if (preg_match($pattern, $header)) {
                return $field;
            }
        }

        return null;
    }

    public function pair(?string $time): ?int
    {
        // Только известные интервалы; нестандартному времени не назначаем вымышленную пару.
        $starts = ['08:30:00', '10:05:00', '11:40:00', '13:45:00', '15:20:00', '16:55:00', '18:30:00', '20:10:00'];
        $index = array_search($time, $starts, true);

        return $index === false ? null : $index + 1;
    }

    private function isFooter(string $text): bool
    {
        return (bool) preg_match('/^(директор|начальник|ведущий документовед|согласовано|утверждаю)/ui', trim($text));
    }
}
