<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\UserService;

class UserController extends ResourceController
{
    protected string $resource = 'users';

    public function store(UserRequest $request)
    {
        return $this->success(User::create($request->validated()), 'Пользователь создан', 201);
    }

    public function update(UserRequest $request, int $id, UserService $service)
    {
        return $this->success($service->change($id, $request->validated(), $request->user()->id));
    }

    public function destroy(int $id)
    {
        return $this->success(app(UserService::class)->change($id, null, auth()->id()));
    }
}
