<?php

namespace App\Http\Controllers;

use App\Services\Import\ImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

/**
 * Приём Excel-файла и запуск импорта.
 *
 * Тонкий слой над ImportService: валидирует загруженный файл, переводит ошибки
 * чтения Excel в понятные сообщения валидации и возвращает счётчики результата.
 */
class ImportController extends Controller
{
    /**
     * Импортирует загруженный файл выбранного типа.
     *
     * @param  Request        $request Запрос с полями file, type, replace.
     * @param  ImportService  $service Сервис разбора и записи данных.
     * @return \Illuminate\Http\JsonResponse Счётчики импортированных/удалённых записей.
     */
    public function __invoke(Request $request, ImportService $service)
    {
        $data = $request->validate(['file' => 'required|file|max:20480|extensions:xls,xlsx', 'type' => 'required|in:teachers,classrooms,schedule,software', 'replace' => 'sometimes|boolean']);
        try {
            $result = $service->import($request->file('file')->getRealPath(), $data['type'], $request->boolean('replace'));
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages(['file' => 'Не удалось прочитать Excel. Проверьте формат и целостность файла.']);
        }

        return $this->success($result, 'Обработано записей: '.$result['imported'].'. Удалено устаревших: '.$result['deleted']);
    }
}
