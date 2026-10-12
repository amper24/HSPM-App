<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Models\ScheduleEntry;
use App\Services\Schedule\ScheduleService;
use Illuminate\Http\Request;

/**
 * CRUD расписания с дополнительными операциями над аудиториями.
 *
 * Создание и обновление идут через ScheduleService, который в одной транзакции
 * сохраняет занятие и синхронизирует связи с аудиториями.
 */
class ScheduleController extends ResourceController
{
    protected string $resource = 'schedule';

    /**
     * Создаёт занятие.
     *
     * @param  ScheduleRequest  $request Валидированный запрос.
     * @param  ScheduleService  $service Сервис транзакционного сохранения.
     * @return \Illuminate\Http\JsonResponse Созданная запись, статус 201.
     */
    public function store(ScheduleRequest $request, ScheduleService $service)
    {
        return $this->success($this->present($service->save($request->validated())), 'Занятие создано', 201);
    }

    /**
     * Обновляет занятие по идентификатору.
     *
     * @param  ScheduleRequest  $request Валидированный запрос (частичные данные).
     * @param  int              $id      Идентификатор занятия.
     * @param  ScheduleService  $service Сервис транзакционного сохранения.
     * @return \Illuminate\Http\JsonResponse Обновлённая запись.
     */
    public function update(ScheduleRequest $request, int $id, ScheduleService $service)
    {
        return $this->success($this->present($service->save($request->validated(), $id)));
    }

    /**
     * Массовое удаление занятий по списку id или диапазону дат.
     *
     * Требует явного указания области удаления: либо непустой массив ids, либо
     * пару date_from/date_to. Защищает от случайного удаления всей таблицы.
     *
     * @param  Request  $request Запрос с ids или date_from/date_to.
     * @return \Illuminate\Http\JsonResponse {deleted}.
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate(['ids' => 'required_without:date_from|array|min:1|max:1000', 'ids.*' => 'integer|min:1', 'date_from' => 'required_without:ids|date_format:Y-m-d', 'date_to' => 'required_with:date_from|date_format:Y-m-d|after_or_equal:date_from']);
        $query = ScheduleEntry::query();
        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } else {
            $query->whereBetween('date', [$data['date_from'], $data['date_to']]);
        }

        return $this->success(['deleted' => $query->delete()]);
    }
}
