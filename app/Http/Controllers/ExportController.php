<?php

namespace App\Http\Controllers;

use App\Services\Export\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Формирование и выдача Excel-выгрузок.
 *
 * Файлы сохраняются на диске «local» вне public/, в каталоге конкретного
 * пользователя, поэтому скачать выгрузку может только её автор.
 */
class ExportController extends Controller
{
    /**
     * Создаёт файл выгрузки заданного типа и возвращает ссылку на него.
     *
     * @param  Request        $request Текущий запрос (нужен идентификатор пользователя).
     * @param  string         $type    Тип: teachers|classrooms|schedule|software|report.
     * @param  ExportService  $service Сервис формирования выгрузки.
     * @return \Illuminate\Http\JsonResponse {file, url}.
     */
    public function export(Request $request, string $type, ExportService $service)
    {
        $filename = $service->create($type, $request->user()->id);

        return $this->success(['file' => $filename, 'url' => '/api/exports/'.$filename]);
    }

    /**
     * Отдаёт файл выгрузки только её владельцу.
     *
     * @param  Request  $request  Текущий запрос (нужен идентификатор пользователя).
     * @param  string   $filename Имя файла вида <uuid>.xlsx.
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download(Request $request, string $filename)
    {
        // Экспорт доступен только создавшему его пользователю и не лежит в public/.
        $path = 'exports/'.$request->user()->id.'/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404, 'Файл не найден');

        return Storage::disk('local')->download($path);
    }
}
