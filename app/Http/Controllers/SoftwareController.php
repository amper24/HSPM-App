<?php

namespace App\Http\Controllers;

use App\Http\Requests\SoftwareRequest;
use App\Models\Software;

class SoftwareController extends ResourceController
{
    protected string $resource = 'software';

    public function store(SoftwareRequest $request)
    {
        return $this->success(Software::create($request->validated()), 'Запись создана', 201);
    }

    public function update(SoftwareRequest $request, int $id)
    {
        $record = Software::findOrFail($id);
        $record->fill($request->validated())->save();

        return $this->success($record, 'Запись обновлена');
    }
}
