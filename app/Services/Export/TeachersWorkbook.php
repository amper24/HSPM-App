<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Формирует Excel для Teachers. Получает готовые данные, не обращается к БД. */
class TeachersWorkbook
{
    public function build(array $teachers): Spreadsheet
    {

        $spreadsheet = new Spreadsheet;
        $spreadsheet->setValueBinder(new StringValueBinder);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Преподаватели');

        $sheet->setCellValue('B1', 'ФИО');
        $sheet->setCellValue('C1', 'Должность');
        $sheet->setCellValue('D1', 'Степень');
        $sheet->setCellValue('E1', 'Звание');
        $sheet->setCellValue('F1', 'Кафедра/Форма занятости');
        WorkbookStyle::header($sheet, 1, 6);

        $row = 3;
        foreach ($teachers as $teacher) {
            $fullName = trim(($teacher['last_name'] ?? '').' '.($teacher['first_name'] ?? '').' '.($teacher['middle_name'] ?? ''));
            $departmentAndEmployment = ($teacher['department'] ?? '').'/'.($teacher['employment_type'] ?? '');
            $sheet->setCellValue('B'.$row, $fullName);
            $sheet->setCellValue('C'.$row, $teacher['position'] ?? '');
            $sheet->setCellValue('D'.$row, $teacher['degree'] ?? '');
            $sheet->setCellValue('E'.$row, $teacher['title'] ?? '');
            $sheet->setCellValue('F'.$row, $departmentAndEmployment);
            $row++;
        }

        $contactsSheet = $spreadsheet->createSheet();
        $contactsSheet->setTitle('Контакты');
        $contactsSheet->setCellValue('B1', 'ФИО');
        $contactsSheet->setCellValue('C1', 'Email');
        $contactsSheet->setCellValue('D1', 'Телефон');

        $contactRow = 4;
        foreach ($teachers as $teacher) {
            $fullName = trim(($teacher['last_name'] ?? '').' '.($teacher['first_name'] ?? '').' '.($teacher['middle_name'] ?? ''));
            $contactsSheet->setCellValue('B'.$contactRow, $fullName);
            $contactsSheet->setCellValue('C'.$contactRow, $teacher['email'] ?? '');
            $contactsSheet->setCellValue('D'.$contactRow, $teacher['phone'] ?? '');
            $contactRow++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
