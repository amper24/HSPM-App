<?php

namespace App\Http\Controllers;

use App\Services\Import\ImportService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class ImportController extends Controller
{
    public function __invoke(Request $request, ImportService $service)
    {
        $data = $request->validate(['file' => 'required|file|max:20480|extensions:xls,xlsx', 'type' => 'required|in:teachers,classrooms,schedule,software', 'create_missing' => 'sometimes|boolean', 'replace' => 'sometimes|boolean']);
        try {
            $result = $service->import($request->file('file')->getRealPath(), $data['type'], $request->boolean('create_missing'), $request->boolean('replace'));
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages(['file' => 'Не удалось прочитать Excel. Проверьте формат и целостность файла.']);
        }

        return $this->success($result, 'Обработано записей: '.$result['imported'].'. Удалено устаревших: '.$result['deleted']);
    }
}
