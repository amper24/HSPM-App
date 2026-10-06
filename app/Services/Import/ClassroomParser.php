<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** Читает Excel и возвращает строки. Не выполняет SQL и не меняет БД. */
class ClassroomParser
{
    public function parse($spreadsheet): array
    {
        // Пытаемся найти лист «Тех хар-ка» (старый спец-формат)
        $technicalSheet = $spreadsheet->getSheetByName('Тех хар-ка');
        if ($technicalSheet) {
            return $this->parseTechnicalSheet($technicalSheet);
        }

        // Иначе — универсальный формат по заголовкам
        return $this->parseHeaderTable($spreadsheet->getActiveSheet());
    }

    private function parseTechnicalSheet($sheet): array
    {
        $rows = [];
        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $roomNumber = trim((string) ($sheet->getCell('A'.$row)->getValue() ?? ''));
            $building = trim((string) ($sheet->getCell('B'.$row)->getValue() ?? ''));
            $roomType = trim((string) ($sheet->getCell('C'.$row)->getValue() ?? ''));
            $computerCount = trim((string) ($sheet->getCell('D'.$row)->getValue() ?? ''));
            $projector = trim((string) ($sheet->getCell('E'.$row)->getValue() ?? ''));
            $speakers = trim((string) ($sheet->getCell('F'.$row)->getValue() ?? ''));
            $seats = trim((string) ($sheet->getCell('G'.$row)->getValue() ?? ''));
            if ($roomNumber === '' || $building === '' || $roomType === '') {
                continue;
            }
            $rows[] = ['room_number' => $roomNumber, 'building' => $building, 'room_type' => $roomType,
                'computers_count' => is_numeric($computerCount) ? (int) $computerCount : 0,
                'has_projector' => $projector === '1' ? 1 : 0, 'has_speakers' => $speakers === '1' ? 1 : 0,
                'seats' => is_numeric($seats) ? (int) $seats : null];

        }

        return $rows;
    }

    private function parseHeaderTable($sheet): array
    {
        $columnCount = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        // Читаем заголовки (строка 1)
        $columnFields = []; // colIndex => fieldName
        for ($column = 1; $column <= $columnCount; $column++) {
            $header = mb_strtolower(trim((string) ($sheet->getCell(ValueNormalizer::colLetter($column).'1')->getValue() ?? '')), 'UTF-8');
            if ($header === '') {
                continue;
            }
            if (preg_match('/^(№|n).*(ауд|room|аудит)/ui', $header)) {
                $columnFields[$column] = 'room_number';
            } elseif (mb_strpos($header, 'корпус') !== false || mb_strpos($header, 'building') !== false || mb_strpos($header, 'здание') !== false) {
                $columnFields[$column] = 'building';
            } elseif (mb_strpos($header, 'тип') !== false || mb_strpos($header, 'type') !== false) {
                $columnFields[$column] = 'room_type';
            } elseif (mb_strpos($header, 'пк') !== false || mb_strpos($header, 'компьютер') !== false || mb_strpos($header, 'computers') !== false) {
                $columnFields[$column] = 'computers_count';
            } elseif (mb_strpos($header, 'проектор') !== false || mb_strpos($header, 'projector') !== false) {
                $columnFields[$column] = 'has_projector';
            } elseif (mb_strpos($header, 'колонк') !== false || mb_strpos($header, 'speakers') !== false) {
                $columnFields[$column] = 'has_speakers';
            } elseif (mb_strpos($header, 'мест') !== false || mb_strpos($header, 'seats') !== false || mb_strpos($header, 'посад') !== false) {
                $columnFields[$column] = 'seats';
            }
        }

        // Если не нашли room_number — пытаемся по первой колонке с цифрами
        if (! in_array('room_number', $columnFields)) {
            for ($column = 1; $column <= $columnCount; $column++) {
                $value = trim((string) ($sheet->getCell(ValueNormalizer::colLetter($column).'2')->getValue() ?? ''));
                if (preg_match('/^\d{2,4}$/', $value)) {
                    $columnFields[$column] = 'room_number';
                    break;
                }
            }
        }
        // Если не нашли building — пробуем по первой букве В/Д
        if (! in_array('building', $columnFields)) {
            for ($column = 1; $column <= $columnCount; $column++) {
                $value = trim((string) ($sheet->getCell(ValueNormalizer::colLetter($column).'2')->getValue() ?? ''));
                if (in_array($value, ['В', 'Д', 'БМ'], true)) {
                    $columnFields[$column] = 'building';
                    break;
                }
            }
        }

        if (! in_array('room_number', $columnFields)) {
            return [];
        } // не смогли определить

        $rows = [];
        for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
            $record = ['room_number' => '', 'building' => 'Д', 'room_type' => '', 'computers_count' => 0, 'has_projector' => 0, 'has_speakers' => 0, 'seats' => null];
            foreach ($columnFields as $column => $field) {
                $value = trim((string) ($sheet->getCell(ValueNormalizer::colLetter($column).$row)->getValue() ?? ''));
                if ($field === 'has_projector' || $field === 'has_speakers') {
                    $record[$field] = ($value === '1' || $value === 'да' || mb_strtolower($value) === 'есть') ? 1 : 0;
                } elseif ($field === 'computers_count') {
                    $record[$field] = is_numeric($value) ? (int) $value : 0;
                } elseif ($field === 'seats') {
                    $record[$field] = is_numeric($value) ? (int) $value : null;
                } else {
                    $record[$field] = $value;
                }
            }
            if ($record['room_number'] === '') {
                continue;
            }

            $rows[] = $record;

        }

        return $rows;
    }
}
