<?php

namespace App\Services\Import;

/** Читает Excel и возвращает строки. Не выполняет SQL и не меняет БД. */
class TeacherParser
{
    public function parse($spreadsheet): array
    {
        $sheetNames = $spreadsheet->getSheetNames();
        $staffSheet = $spreadsheet->getSheetByName($sheetNames[0]);
        $mainData = [];

        for ($row = 3; $row <= $staffSheet->getHighestDataRow(); $row++) {
            $fullName = trim((string) ($staffSheet->getCell('B'.$row)->getValue() ?? ''));
            if ($fullName === '' || $fullName === 'None') {
                continue;
            }

            $position = trim((string) ($staffSheet->getCell('C'.$row)->getValue() ?? ''));
            $degree = trim((string) ($staffSheet->getCell('D'.$row)->getValue() ?? ''));
            $title = trim((string) ($staffSheet->getCell('E'.$row)->getValue() ?? ''));
            $departmentAndEmployment = trim((string) ($staffSheet->getCell('F'.$row)->getValue() ?? ''));

            $parts = array_map('trim', explode('/', $departmentAndEmployment));
            $department = $parts[0] ?? '';
            $employmentText = '';
            for ($partIndex = 1; $partIndex < count($parts); $partIndex++) {
                $employmentText .= $parts[$partIndex].' ';
            }
            $employmentText = trim($employmentText);
            // В исходной книге занятость вынесена в G, в нашем экспорте записана после «/».
            if ($employmentText === '') {
                $employmentText = trim((string) ($staffSheet->getCell('G'.$row)->getValue() ?? ''));
            }
            $employmentType = ValueNormalizer::normalizeEmploymentType($employmentText);
            $department = ValueNormalizer::normalizeDepartment($department);

            if (mb_stripos($fullName, 'кафедра') !== false && $position === '') {
                continue;
            }
            if ($position === '' && $departmentAndEmployment === '' && $degree === '' && $title === '') {
                continue;
            }

            $nameParts = ValueNormalizer::parseFIO($fullName);
            $mainData[ValueNormalizer::normalizeKey($fullName)] = [
                'last_name' => $nameParts[0], 'first_name' => $nameParts[1], 'middle_name' => $nameParts[2],
                'position' => $position,
                'degree' => ($degree !== '' && $degree !== 'None') ? $degree : null,
                'title' => ($title !== '' && $title !== 'None') ? $title : null,
                'department' => ($department !== '' && $department !== 'None') ? $department : null,
                'employment_type' => $employmentType !== '' ? $employmentType : null,
            ];
        }

        if (count($sheetNames) >= 2) {
            $contactsSheet = $spreadsheet->getSheetByName($sheetNames[1]);
            for ($row = 4; $row <= $contactsSheet->getHighestDataRow(); $row++) {
                $fullName = trim((string) ($contactsSheet->getCell('B'.$row)->getValue() ?? ''));
                $email = trim((string) ($contactsSheet->getCell('C'.$row)->getValue() ?? ''));
                $phone = trim((string) ($contactsSheet->getCell('D'.$row)->getValue() ?? ''));
                if ($fullName === '' || $fullName === 'None') {
                    continue;
                }
                if (ValueNormalizer::isDepartmentHeader($fullName)) {
                    continue;
                }

                $nameKey = ValueNormalizer::normalizeKey($fullName);
                if (isset($mainData[$nameKey])) {
                    if ($email !== '' && $email !== 'None') {
                        $mainData[$nameKey]['email'] = $email;
                    }
                    if ($phone !== '' && $phone !== 'None') {
                        $mainData[$nameKey]['phone'] = $phone;
                    }
                } else {
                    $nameParts = ValueNormalizer::parseFIO($fullName);
                    $mainData[$nameKey] = [
                        'last_name' => $nameParts[0], 'first_name' => $nameParts[1], 'middle_name' => $nameParts[2],
                        'position' => null, 'degree' => null, 'title' => null, 'department' => null, 'employment_type' => null,
                        'email' => ($email !== '' && $email !== 'None') ? $email : null,
                        'phone' => ($phone !== '' && $phone !== 'None') ? $phone : null,
                    ];
                }
            }
        }

        $rows = [];
        foreach ($mainData as $teacher) {
            $rows[] = ['last_name' => $teacher['last_name'], 'first_name' => $teacher['first_name'], 'middle_name' => $teacher['middle_name'] ?? null,
                'position' => $teacher['position'], 'degree' => $teacher['degree'], 'title' => $teacher['title'],
                'department' => $teacher['department'], 'employment_type' => $teacher['employment_type'],
                'email' => $teacher['email'] ?? null, 'phone' => $teacher['phone'] ?? null];

        }

        return $rows;
    }
}
