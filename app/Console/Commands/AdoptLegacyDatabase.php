<?php

namespace App\Console\Commands;

use App\Models\Classroom;
use App\Models\ScheduleEntry;
use App\Models\Software;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Регистрирует исходную схему как уже существующую, не пересоздавая таблицы. */
class AdoptLegacyDatabase extends Command
{
    protected $signature = 'hspm:adopt-legacy {--backup-confirmed : Резервная копия создана и проверена}';

    protected $description = 'Подключить существующую базу старого приложения к миграциям Laravel';

    public function handle(): int
    {
        if (! $this->option('backup-confirmed')) {
            $this->error('Сначала сделайте и проверьте резервную копию. Затем передайте --backup-confirmed.');

            return self::FAILURE;
        }
        $migration = '2026_09_30_000001_create_education_tables';
        foreach ([new User, new Teacher, new Classroom, new ScheduleEntry, new Software] as $model) {
            $required = array_merge(['id', 'created_at', 'updated_at'], $model->getFillable());
            if (! Schema::hasTable($model->getTable()) || ! Schema::hasColumns($model->getTable(), $required)) {
                $this->error('Схема не совпадает: '.$model->getTable().'. Изменений не выполнено.');

                return self::FAILURE;
            }
        }
        if (! Schema::hasTable('schedule_classrooms') || ! Schema::hasColumns('schedule_classrooms', ['id', 'schedule_id', 'classroom_id'])) {
            $this->error('Отсутствует связь schedule_classrooms. Изменений не выполнено.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('migrations')) {
            $this->call('migrate:install');
        }
        DB::table('migrations')->updateOrInsert(['migration' => $migration], ['batch' => 1]);
        $this->info('Исходная схема зарегистрирована. Данные не изменены. Выполните php artisan migrate --force.');

        return self::SUCCESS;
    }
}
