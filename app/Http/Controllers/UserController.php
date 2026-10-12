<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;

/**
 * Управление пользователями.
 *
 * Изменение и удаление идут через UserService, который в транзакции защищает
 * систему от удаления последнего администратора и от удаления собственной записи.
 */
class UserController extends ResourceController
{
    protected string $resource = 'users';

    /**
     * Создаёт пользователя из проверенных данных.
     *
     * @param  UserRequest  $request Валидированный запрос.
     * @return \Illuminate\Http\JsonResponse Созданная запись, статус 201.
     */
    public function store(UserRequest $request)
    {
        return $this->success(User::create($request->validated()), 'Пользователь создан', 201);
    }

    /**
     * Обновляет пользователя, сохраняя защитные ограничения.
     *
     * @param  UserRequest  $request Валидированный запрос (частичные данные).
     * @param  int          $id      Идентификатор пользователя.
     * @param  UserService  $service Сервис изменения пользователя.
     * @return \Illuminate\Http\JsonResponse Обновлённая запись.
     */
    public function update(UserRequest $request, int $id, UserService $service)
    {
        return $this->success($service->change($id, $request->validated(), $request->user()->id));
    }

    /**
     * Удаляет пользователя через UserService (с защитой последнего администратора).
     *
     * @param  int  $id Идентификатор пользователя.
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        return $this->success(app(UserService::class)->change($id, null, auth()->id()));
    }
}
