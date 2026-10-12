<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherRequest;
use App\Models\Teacher;

/**
 * CRUD преподавателей; общие операции унаследованы от ResourceController.
 */
class TeacherController extends ResourceController
{
    protected string $resource = 'teachers';

    /**
     * Создаёт преподавателя из проверенных данных.
     *
     * @param  TeacherRequest  $request Валидированный запрос.
     * @return \Illuminate\Http\JsonResponse Созданная запись, статус 201.
     */
    public function store(TeacherRequest $request)
    {
        return $this->success(Teacher::create($request->validated()), 'Запись создана', 201);
    }

    /**
     * Обновляет преподавателя по идентификатору.
     *
     * @param  TeacherRequest  $request Валидированный запрос (частичные данные).
     * @param  int             $id      Идентификатор преподавателя.
     * @return \Illuminate\Http\JsonResponse Обновлённая запись.
     */
    public function update(TeacherRequest $request, int $id)
    {
        $record = Teacher::findOrFail($id);
        $record->fill($request->validated())->save();

        return $this->success($record, 'Запись обновлена');
    }
}
