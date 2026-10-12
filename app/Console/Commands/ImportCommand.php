<?php

namespace App\Console\Commands;

use App\Services\Import\ImportService;
use Illuminate\Console\Command;

/**
 * Импорт Excel из командной строки тем же сервисом, что используется в браузере.
 *
 * Удобен для пакетной загрузки исторических данных без веб-интерфейса.
 */
class ImportCommand extends Command
{
    protected $signature = 'hspm:import {type} {file} {--replace}';

    protected $description = 'Импорт Excel тем же сервисом, который используется в браузере';

    /**
     * Выполняет импорт и печатает JSON-результат.
     *
     * @param  ImportService  $service Сервис разбора и записи данных.
     * @return int SUCCESS при успешном импорте, FAILURE при неизвестном типе.
     */
    public function handle(ImportService $service): int
    {
        if (! in_array($this->argument('type'), ['teachers', 'classrooms', 'schedule', 'software'])) {
            $this->error('Неизвестный тип');

            return self::FAILURE;
        }
        $result = $service->import($this->argument('file'), $this->argument('type'), $this->option('replace'));
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
