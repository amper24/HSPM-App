<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Формирует Excel для Software. Получает готовые данные, не обращается к БД. */
class SoftwareWorkbook
{
    public function build(array $items): Spreadsheet
    {

        // Группируем ПО по аудиториям, сохраняя порядок появления
        $order = [];
        $rooms = [];
        foreach ($items as $software) {
            $building = $software['building'] ?: 'Д';
            $key = $building.'|'.$software['room_number'];
            if (! isset($rooms[$key])) {
                $order[] = $key;
                $rooms[$key] = ['room_number' => $software['room_number'], 'building' => $building, 'software' => []];
            }
            if (! in_array($software['name'], $rooms[$key]['software'], true)) {
                $rooms[$key]['software'][] = $software['name'];
            }
        }

        $rowCount = 0;
        foreach ($rooms as $room) {
            if (count($room['software']) > $rowCount) {
                $rowCount = count($room['software']);
            }
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new StringValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('ПО');

        $column = 1;
        foreach ($order as $key) {
            $room = $rooms[$key];
            $header = $room['room_number'];
            if ($room['building'] === 'В') {
                $header .= ' взн';
            }
            $sheet->setCellValue([$column, 3], $header);
            $column++;
        }

        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            $column = 1;
            foreach ($order as $key) {
                $list = $rooms[$key]['software'];
                $sheet->setCellValue([$column, 4 + $rowIndex], $list[$rowIndex] ?? '');
                $column++;
            }
        }

        for ($columnIndex = 1; $columnIndex < $column; $columnIndex++) {
            $sheet->getColumnDimension(WorkbookStyle::column($columnIndex))->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
