<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Формирует Excel для Report. Получает готовые данные, не обращается к БД. */
class ReportWorkbook
{
    public function build(array $report): Spreadsheet
    {

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new StringValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Отчет по аудиториям');

        $headers = ['Аудитория', 'Корпус', 'Тип', 'Мест', 'Проектор', 'Колонки', 'ПК',
            'Всего занятий', 'Занятых записей', 'Переносов', 'Отмен'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        WorkbookStyle::header($sheet, 1, count($headers));

        $row = 2;
        foreach ($report as $room) {
            $sheet->setCellValue([1, $row], $room['room_number']);
            $sheet->setCellValue([2, $row], $room['building']);
            $sheet->setCellValue([3, $row], $room['room_type']);
            $sheet->setCellValue([4, $row], $room['seats']);
            $sheet->setCellValue([5, $row], $room['has_projector'] ? '+' : '-');
            $sheet->setCellValue([6, $row], $room['has_speakers'] ? '+' : '-');
            $sheet->setCellValue([7, $row], $room['computers_count']);
            $sheet->setCellValue([8, $row], $room['total_lessons']);
            $sheet->setCellValue([9, $row], $room['occupied_count']);
            $sheet->setCellValue([10, $row], $room['transfers']);
            $sheet->setCellValue([11, $row], $room['cancellations']);
            $row++;
        }

        return $spreadsheet;
    }
}
