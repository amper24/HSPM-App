<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Пароль вводится скрыто или берётся из окружения при первом запуске контейнера. */
class AdminCommand extends Command
{
    protected $signature = 'hspm:admin {username=admin} {--create : Создать только отсутствующую учётную запись}';

    protected $description = 'Создать администратора или изменить его пароль';

    public function handle(): int
    {
        $username = $this->argument('username');
        $user = User::where('username', $username)->first();
        if ($this->option('create') && $user) {
            $this->info('Учётная запись уже существует; пароль не изменён.');

            return self::SUCCESS;
        }
        $password = getenv('ADMIN_PASSWORD') ?: $this->secret('Новый пароль (не менее 12 символов)');
        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Нужен пароль длиной не менее 12 символов.');

            return self::FAILURE;
        }
        $user ??= new User(['username' => $username, 'full_name' => 'Администратор']);
        $user->fill(['password' => $password, 'role' => 'admin'])->save();
        $this->info('Учётная запись сохранена.');

        return self::SUCCESS;
    }
}
