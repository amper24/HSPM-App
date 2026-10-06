<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Формирует Excel для Schedule. Получает готовые данные, не обращается к БД. */
class ScheduleWorkbook
{
    public function build(array $items): Spreadsheet
    {

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new StringValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Расписание');

        $headers = [
            'Дата', 'Время', 'Группа', 'Дисциплина', 'Экзаменатор', 'Вид занятия',
            'Экзамен/консультация', 'Аудитория', 'Перенос/отмена',
            'Кафедра группы', 'Кафедра преподавателя', 'Должность преподавателя',
            'Сессия с', 'Сессия по', 'День недели', 'Числитель/знаменатель',
            'Номер пары', 'Занята', 'Нестандартное время',
        ];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        WorkbookStyle::header($sheet, 1, count($headers));

        $row = 2;
        foreach ($items as $lesson) {
            $time = '';
            if ($lesson['time_start']) {
                $time = substr($lesson['time_start'], 0, 5);
                if ($lesson['time_end']) {
                    $time .= '-'.substr($lesson['time_end'], 0, 5);
                }
            }
            $roomText = $lesson['classrooms'] ?: $lesson['classrooms_raw'] ?: ($lesson['room_number'] ?: '');

            $sheet->setCellValue([1, $row], $lesson['date']);
            $sheet->setCellValue([2, $row], $time);
            $sheet->setCellValue([3, $row], $lesson['group_code'] ?? '');
            $sheet->setCellValue([4, $row], $lesson['discipline'] ?? '');
            $sheet->setCellValue([5, $row], $lesson['examiner'] ?? '');
            $sheet->setCellValue([6, $row], $lesson['lesson_type'] ?? '');
            $sheet->setCellValue([7, $row], $lesson['exam_type'] ?? '');
            $sheet->setCellValue([8, $row], $roomText);
            $sheet->setCellValue([9, $row], $lesson['transfer_cancel'] ?? 'нет');
            $sheet->setCellValue([10, $row], $lesson['group_department'] ?? '');
            $sheet->setCellValue([11, $row], $lesson['teacher_department'] ?? '');
            $sheet->setCellValue([12, $row], $lesson['teacher_position'] ?? '');
            $sheet->setCellValue([13, $row], $lesson['session_start'] ?? '');
            $sheet->setCellValue([14, $row], $lesson['session_end'] ?? '');
            $sheet->setCellValue([15, $row], $lesson['day_of_week'] ?? '');
            $sheet->setCellValue([16, $row], $lesson['numerator_denominator'] ?? '');
            $sheet->setCellValue([17, $row], $lesson['pair_number'] ?? '');
            $sheet->setCellValue([18, $row], (int) ($lesson['is_occupied'] ?? 0));
            $sheet->setCellValue([19, $row], (int) ($lesson['is_nonstandard_time'] ?? 0));
            $row++;
        }

        return $spreadsheet;
    }
}
