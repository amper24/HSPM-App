<?php

namespace App\Services\Export;

use App\Http\Resources\ScheduleData;
use App\Queries\EducationQuery;
use App\Queries\OccupancyReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Формирование Excel-выгрузок.
 *
 * Отделяет выборку данных от оформления: данные получает через EducationQuery или
 * OccupancyReport, а оформлением занимаются классы *Workbook. Файлы сохраняются на
 * диске «local» в каталоге пользователя вне public/.
 */
class ExportService
{
    /**
     * Создаёт файл выгрузки и возвращает его имя.
     *
     * @param  string  $type   Тип: teachers|classrooms|schedule|software|report.
     * @param  int     $userId Идентификатор пользователя (владельца файла).
     * @return string Уникальное имя файла вида <uuid>.xlsx.
     */
    public function create(string $type, int $userId): string
    {
        abort_unless(in_array($type, ['teachers', 'classrooms', 'schedule', 'software', 'report']), 404, 'Неизвестный тип экспорта');
        $rows = $type === 'report' ? app(OccupancyReport::class)->rows()
            : app(EducationQuery::class)->for($type)->get()->map(fn ($row) => $type === 'schedule' ? ScheduleData::from($row) : $row->toArray())->all();
        $builder = match ($type) {
            'teachers' => new TeachersWorkbook, 'classrooms' => new ClassroomsWorkbook, 'schedule' => new ScheduleWorkbook, 'software' => new SoftwareWorkbook, 'report' => new ReportWorkbook
        };
        $book = $builder->build($rows);
        $filename = Str::uuid().'.xlsx';
        $directory = 'exports/'.$userId;
        Storage::disk('local')->makeDirectory($directory);
        try {
            (new Xlsx($book))->save(Storage::disk('local')->path($directory.'/'.$filename));
        } finally {
            $book->disconnectWorksheets();
        }

        return $filename;
    }
}
