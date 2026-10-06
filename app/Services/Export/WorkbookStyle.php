<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkbookStyle
{
    public static function header(Worksheet $sheet, int $row, int $maxCol): void
    {
        if ($maxCol < 1) {
            return;
        }
        $sheet->getStyle('A'.$row.':'.self::column($maxCol).$row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'],
            ],
        ]);
    }

    public static function column(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }
}
