<?php

namespace App\Http\Controllers;

use App\Queries\EducationQuery;
use Illuminate\Http\Request;

/**
 * Вспомогательные справочники: значения фильтров и быстрый поиск.
 *
 * Имена колонок берутся только из явно разрешённых карт соответствия, поэтому
 * пользователь не может заставить сервер выбирать произвольную колонку таблицы.
 */
class LookupController extends Controller
{
    /**
     * Уникальные значения указанного поля справочника (для выпадающих списков).
     *
     * @param  string          $resource Имя ресурса из разрешённого списка.
     * @param  string          $field    Псевдоним справочника (departments, room-types и т. д.).
     * @param  EducationQuery  $queries  Фабрика запросов.
     * @return \Illuminate\Http\JsonResponse Список уникальных непустых значений.
     */
    public function options(string $resource, string $field, EducationQuery $queries)
    {
        $allowed = ['teachers' => ['departments' => 'department', 'degrees' => 'degree', 'titles' => 'title', 'employment-types' => 'employment_type'], 'classrooms' => ['room-types' => 'room_type'], 'software' => ['buildings' => 'building'], 'schedule' => ['groups' => 'group_code']];
        $column = $allowed[$resource][$field] ?? null;
        abort_unless($column, 404);

        return $this->success($queries->for($resource)->withoutEagerLoads()->reorder()->whereNotNull($column)->where($column, '!=', '')->distinct()->orderBy($column)->pluck($column));
    }

    /**
     * Поиск по преподавателям и аудиториям (для автодополнения).
     *
     * @param  Request         $request Запрос с фильтрами поиска.
     * @param  string          $resource teachers или classrooms.
     * @param  EducationQuery  $queries  Фабрика запросов.
     * @return \Illuminate\Http\JsonResponse До 500 подходящих записей.
     */
    public function search(Request $request, string $resource, EducationQuery $queries)
    {
        abort_unless(in_array($resource, ['teachers', 'classrooms']), 404);

        return $this->success($queries->for($resource, $request->query())->limit(500)->get());
    }
}
