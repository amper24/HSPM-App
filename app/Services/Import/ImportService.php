<?php

namespace App\Services\Import;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Загрузка книги Excel, выбор парсера и атомарная запись результата.
 *
 * Единственная точка входа импорта как из браузера (ImportController), так и из
 * командной строки (ImportCommand). Освобождение ресурсов книги гарантируется в
 * блоке finally.
 */
class ImportService
{
    /**
     * Импортирует файл выбранного типа.
     *
     * @param  string  $path    Путь к файлу .xlsx/.xls.
     * @param  string  $type    Тип: teachers|classrooms|schedule|software.
     * @param  bool    $replace Заменять ли данные групп в диапазоне дат файла (для schedule).
     * @return array{imported: int, deleted?: int} Счётчики результата.
     *
     * @throws \Illuminate\Validation\ValidationException Если файл пуст или не содержит
     *         записей выбранного типа.
     */
    public function import(string $path, string $type, bool $replace = false): array
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

            return app(ImportWriter::class)->save($type, $rows, $replace, $parsed['rooms'] ?? []);
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
