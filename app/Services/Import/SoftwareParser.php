<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Исходный лист ПО хранит аудитории в колонках, а программы в строках. */
class SoftwareParser
{
    public function parse(Spreadsheet $book): array
    {
        $sheet = $book->getSheetByName('ПО');
        if (! $sheet) {
            return ['rows' => [], 'rooms' => []];
        }
        $rooms = [];
        $records = [];
        $maxColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($column = 1; $column <= $maxColumn; $column++) {
            $room = ValueNormalizer::parseRoomHeader(ExcelValue::text(ExcelValue::cell($sheet, $column, 3)));
            if ($room) {
                $rooms[$column] = $room;
            }
        }
        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            foreach ($rooms as $column => $room) {
                $name = ExcelValue::text(ExcelValue::cell($sheet, $column, $row));
                if ($name !== '') {
                    $records[] = $room + ['name' => $name];
                }
            }
        }

        return ['rows' => $records, 'rooms' => array_values($rooms)];
    }
}
