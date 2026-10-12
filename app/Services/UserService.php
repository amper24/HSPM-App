<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Изменение и удаление пользователей с защитой последнего администратора.
 *
 * Все операции идут внутри транзакции и блокируют строки администраторов
 * (lockForUpdate), чтобы параллельные запросы не могли одновременно удалить или
 * понизить последнего администратора.
 */
class UserService
{
    /**
     * Обновляет или удаляет пользователя с учётом инвариантов.
     *
     * @param  int        $id      Идентификатор изменяемого пользователя.
     * @param  array|null $data    Новые данные; null означает удаление записи.
     * @param  int        $actorId Идентификатор текущего пользователя.
     * @return \App\Models\User|null Обновлённая запись или null при удалении.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 при попытке
     *         удалить/понизить последнего администратора или удалить собственную запись.
     */
    public function change(int $id, ?array $data, int $actorId): ?User
    {
        return DB::transaction(function () use ($id, $data, $actorId) {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $user = User::findOrFail($id);
            $removesAdmin = $data === null || (($data['role'] ?? $user->role) !== 'admin');
            abort_if($user->role === 'admin' && $removesAdmin && $admins->count() <= 1, 422, 'Нельзя удалить или понизить последнего администратора');
            abort_if($data === null && $id === $actorId, 422, 'Нельзя удалить собственную учётную запись');
            if ($data === null) {
                $user->delete();

                return null;
            }
            $user->fill($data)->save();

            return $user;
        });
    }
}
