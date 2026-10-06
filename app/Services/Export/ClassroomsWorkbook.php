<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Формирует Excel для Classrooms. Получает готовые данные, не обращается к БД. */
class ClassroomsWorkbook
{
    public function build(array $rooms): Spreadsheet
    {

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new StringValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Аудитории');

        $headers = ['№ аудитории', 'Корпус', 'Тип', 'Компьютеров', 'Проектор', 'Колонки', 'Мест'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        WorkbookStyle::header($sheet, 1, count($headers));

        $row = 2;
        foreach ($rooms as $room) {
            $sheet->setCellValue([1, $row], $room['room_number']);
            $sheet->setCellValue([2, $row], $room['building']);
            $sheet->setCellValue([3, $row], $room['room_type']);
            $sheet->setCellValue([4, $row], (int) $room['computers_count']);
            $sheet->setCellValue([5, $row], (int) $room['has_projector']);
            $sheet->setCellValue([6, $row], (int) $room['has_speakers']);
            $sheet->setCellValue([7, $row], $room['seats'] !== null && $room['seats'] !== '' ? (int) $room['seats'] : '');
            $row++;
        }

        return $spreadsheet;
    }
}
