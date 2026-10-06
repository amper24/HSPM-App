<?php

namespace App\Services\Import;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** Загрузка книги, выбор парсера и атомарная запись результата. */
class ImportService
{
    public function import(string $path, string $type, bool $createMissing = false, bool $replace = false): array
    {
        $readerType = IOFactory::identify($path, ['Xlsx', 'Xls']);
        $reader = IOFactory::createReader($readerType);
        $reader->setReadEmptyCells(false);
        $book = $reader->load($path);
        try {
            $parser = match ($type) {
                'teachers' => new TeacherParser, 'classrooms' => new ClassroomParser, 'schedule' => new ScheduleParser, 'software' => new SoftwareParser
            };
            $parsed = $parser->parse($book);
            $rows = $type === 'software' ? $parsed['rows'] : $parsed;
            if (! $rows && ! ($type === 'software' && $parsed['rooms'])) {
                throw ValidationException::withMessages(['file' => 'В файле не найдены записи выбранного типа. База данных не изменена.']);
            }

            return app(ImportWriter::class)->save($type, $rows, $createMissing, $replace, $parsed['rooms'] ?? []);
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
