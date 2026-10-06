<?php

namespace App\Http\Controllers;

use App\Services\Export\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function export(Request $request, string $type, ExportService $service)
    {
        $filename = $service->create($type, $request->user()->id);

        return $this->success(['file' => $filename, 'url' => '/api/exports/'.$filename]);
    }

    public function download(Request $request, string $filename)
    {
        // Экспорт доступен только создавшему его пользователю и не лежит в public/.
        $path = 'exports/'.$request->user()->id.'/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404, 'Файл не найден');

        return Storage::disk('local')->download($path);
    }
}
