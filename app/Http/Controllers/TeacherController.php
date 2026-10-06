<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherRequest;
use App\Models\Teacher;

class TeacherController extends ResourceController
{
    protected string $resource = 'teachers';

    public function store(TeacherRequest $request)
    {
        return $this->success(Teacher::create($request->validated()), 'Запись создана', 201);
    }

    public function update(TeacherRequest $request, int $id)
    {
        $record = Teacher::findOrFail($id);
        $record->fill($request->validated())->save();

        return $this->success($record, 'Запись обновлена');
    }
}
