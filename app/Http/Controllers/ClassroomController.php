<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClassroomRequest;
use App\Models\Classroom;

class ClassroomController extends ResourceController
{
    protected string $resource = 'classrooms';

    public function store(ClassroomRequest $request)
    {
        return $this->success(Classroom::create($request->validated()), 'Запись создана', 201);
    }

    public function update(ClassroomRequest $request, int $id)
    {
        $record = Classroom::findOrFail($id);
        $record->fill($request->validated())->save();

        return $this->success($record, 'Запись обновлена');
    }
}
