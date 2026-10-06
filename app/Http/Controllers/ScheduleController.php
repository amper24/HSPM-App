<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleRequest;
use App\Models\ScheduleEntry;
use App\Services\Schedule\ScheduleService;
use Illuminate\Http\Request;

class ScheduleController extends ResourceController
{
    protected string $resource = 'schedule';

    public function store(ScheduleRequest $request, ScheduleService $service)
    {
        return $this->success($this->present($service->save($request->validated())), 'Занятие создано', 201);
    }

    public function update(ScheduleRequest $request, int $id, ScheduleService $service)
    {
        return $this->success($this->present($service->save($request->validated(), $id)));
    }

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
