<?php

namespace App\Http\Controllers;

use App\Models\ScheduleEntry;
use App\Queries\EducationQuery;
use Illuminate\Http\Request;

class FreeClassroomController extends Controller
{
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
