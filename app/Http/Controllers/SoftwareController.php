<?php

namespace App\Http\Controllers;

use App\Http\Requests\SoftwareRequest;
use App\Models\Software;

/**
 * CRUD программного обеспечения; общие операции унаследованы от ResourceController.
 */
class SoftwareController extends ResourceController
{
    protected string $resource = 'software';

    /**
     * Создаёт запись о ПО из проверенных данных.
     *
     * @param  SoftwareRequest  $request Валидированный запрос.
     * @return \Illuminate\Http\JsonResponse Созданная запись, статус 201.
     */
    public function store(SoftwareRequest $request)
    {
        return $this->success(Software::create($request->validated()), 'Запись создана', 201);
    }

    /**
     * Обновляет запись о ПО по идентификатору.
     *
     * @param  SoftwareRequest  $request Валидированный запрос (частичные данные).
     * @param  int              $id      Идентификатор записи.
     * @return \Illuminate\Http\JsonResponse Обновлённая запись.
     */
    public function update(SoftwareRequest $request, int $id)
    {
        $record = Software::findOrFail($id);
        $record->fill($request->validated())->save();

        return $this->success($record, 'Запись обновлена');
    }
}
