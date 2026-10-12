<?php

namespace App\Http\Controllers;

use App\Models\ScheduleEntry;
use App\Queries\EducationQuery;
use Illuminate\Http\Request;

/**
 * Поиск свободных аудиторий на заданную дату и пару.
 *
 * Аудитория считается занятой, если у неё есть занятие с календарной датой и
 * флагом is_occupied, не отменённое. Учитываются как основная аудитория занятия
 * (primaryLessons), так и дополнительные связи (lessons). Недельные сетки без дат
 * в расчёт не попадают — о них пользователь получает отдельное уведомление.
 */
class FreeClassroomController extends Controller
{
    /**
     * Возвращает свободные аудитории, соответствующие фильтрам.
     *
     * @param  Request         $request Запрос с фильтрами: date (обязательно), pair_number,
     *                                  building, room_type, has_projector, has_speakers, seats_min.
     * @param  EducationQuery  $queries Фабрика запросов со списком разрешённых фильтров.
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request, EducationQuery $queries)
    {
        $filters = $request->validate(['date' => 'required|date_format:Y-m-d', 'pair_number' => 'nullable|integer|min:1|max:20', 'building' => 'nullable|string|max:5', 'room_type' => 'nullable|string|max:50', 'has_projector' => 'nullable|boolean', 'has_speakers' => 'nullable|boolean', 'seats_min' => 'nullable|integer|min:0']);
        $occupied = function ($lessons) use ($filters) {
            $lessons->where('date', $filters['date'])->where('is_occupied', true)
                ->where(fn ($q) => $q->whereNull('transfer_cancel')->orWhere('transfer_cancel', '!=', 'отмена'));
            if (! empty($filters['pair_number'])) {
                $lessons->where('pair_number', $filters['pair_number']);
            }
        };

        // Учитываем и старую основную аудиторию, и таблицу множественных связей.
        $rooms = $queries->for('classrooms', $filters)->whereDoesntHave('primaryLessons', $occupied)->whereDoesntHave('lessons', $occupied)->get();
        $notice = ScheduleEntry::whereNull('date')->exists()
            ? 'Проверены только занятия с календарными датами. В базе есть недельные сетки без дат: их занятость нужно проверить отдельно.' : '';

        return $this->success($rooms, $notice);
    }
}
