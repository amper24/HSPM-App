<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Управление учётной записью администратора.
 *
 * Пароль вводится скрыто (secret) либо берётся из переменной окружения
 * ADMIN_PASSWORD — это используется при первом запуске Docker-контейнера, где
 * интерактивный ввод невозможен.
 */
class AdminCommand extends Command
{
    protected $signature = 'hspm:admin {username=admin} {--create : Создать только отсутствующую учётную запись}';

    protected $description = 'Создать администратора или изменить его пароль';

    /**
     * Создаёт администратора или обновляет его пароль.
     *
     * @return int SUCCESS при успехе, FAILURE при пустом/коротком пароле или когда
     *             в режиме --create учётная запись уже существует.
     */
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
