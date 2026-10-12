<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

/** Агрегация по всем аудиториям, включая дополнительные аудитории одного занятия. */
/**
 * Агрегация занятости по всем аудиториям.
 *
 * Учитывает как основную аудиторию занятия (schedule.classroom_id), так и
 * дополнительные связи (schedule_classrooms). Строит сводку одним SQL с UNION и
 * условными SUM, не выгружая строки занятий в PHP.
 */
class OccupancyReport
{
    /**
     * Возвращает по каждой аудитории число занятий, занятых записей, переносов и отмен.
     *
     * @return array<int, array<string, mixed>> Строки сводки с полями total_lessons,
     *                                           occupied_count, transfers, cancellations.
     */
    public function rows(): array
    {
        // Все связи «занятие — аудитория»: основная аудитория + дополнительные.
        $links = DB::table('schedule_classrooms')->select('schedule_id', 'classroom_id')
            ->union(DB::table('schedule')->whereNotNull('classroom_id')->selectRaw('id as schedule_id, classroom_id'));
        $totals = DB::query()->fromSub($links, 'links')->join('schedule as s', 's.id', '=', 'links.schedule_id')
            ->select('links.classroom_id')->selectRaw("COUNT(*) as total_lessons, SUM(CASE WHEN s.is_occupied = 1 AND (s.transfer_cancel IS NULL OR s.transfer_cancel != 'отмена') THEN 1 ELSE 0 END) as occupied_count, SUM(CASE WHEN s.transfer_cancel = 'перенос' THEN 1 ELSE 0 END) as transfers, SUM(CASE WHEN s.transfer_cancel = 'отмена' THEN 1 ELSE 0 END) as cancellations")
            ->groupBy('links.classroom_id');

        return DB::table('classrooms as c')->leftJoinSub($totals, 'totals', 'totals.classroom_id', '=', 'c.id')
            ->select('c.*')->selectRaw('COALESCE(total_lessons, 0) as total_lessons, COALESCE(occupied_count, 0) as occupied_count, COALESCE(transfers, 0) as transfers, COALESCE(cancellations, 0) as cancellations')
            ->orderBy('c.building')->orderBy('c.room_number')->get()->map(fn ($row) => (array) $row)->all();
    }
}
