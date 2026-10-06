<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    /** Блокируем администраторов в одном порядке, чтобы параллельные запросы не удалили последнего. */
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
