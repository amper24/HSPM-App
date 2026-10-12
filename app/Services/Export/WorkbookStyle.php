<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Стилизация заголовков Excel-выгрузок (шрифт и заливка).
 */
class WorkbookStyle
{
    /**
     * Применяет к строке заголовков жирный шрифт и цветную заливку.
     *
     * @param  Worksheet  $sheet  Лист книги.
     * @param  int        $row    Номер строки заголовка.
     * @param  int        $maxCol Число колонок заголовка.
     */
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

    /**
     * Преобразует индекс колонки в её буквенное имя.
     *
     * @param  int  $index Индекс колонки, начиная с 1.
     * @return string Буквенное имя колонки.
     */
    public static function column(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }
}
