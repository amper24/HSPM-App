<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomRequest;
use App\Models\Classroom;

/**
 * CRUD аудиторий; общие операции унаследованы от ResourceController.
 */
class ClassroomController extends ResourceController
{
    protected string $resource = 'classrooms';

    /**
     * Создаёт аудиторию из проверенных данных.
     *
     * @param  ClassroomRequest  $request Валидированный запрос.
     * @return \Illuminate\Http\JsonResponse Созданная запись, статус 201.
     */
    public function store(ClassroomRequest $request)
    {
        return $this->success(Classroom::create($request->validated()), 'Запись создана', 201);
    }

    /**
     * Обновляет аудиторию по идентификатору.
     *
     * @param  ClassroomRequest  $request Валидированный запрос (частичные данные).
     * @param  int               $id      Идентификатор аудитории.
     * @return \Illuminate\Http\JsonResponse Обновлённая запись.
     */
    public function update(ClassroomRequest $request, int $id)
    {
        $record = Classroom::findOrFail($id);
        $record->fill($request->validated())->save();

        return $this->success($record, 'Запись обновлена');
    }
}
