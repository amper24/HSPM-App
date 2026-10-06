<?php

namespace App\Console\Commands;

use App\Services\Import\ImportService;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'hspm:import {type} {file} {--create-missing} {--replace}';

    protected $description = 'Импорт Excel тем же сервисом, который используется в браузере';

    public function handle(ImportService $service): int
    {
        if (! in_array($this->argument('type'), ['teachers', 'classrooms', 'schedule', 'software'])) {
            $this->error('Неизвестный тип');

            return self::FAILURE;
        }
        $result = $service->import($this->argument('file'), $this->argument('type'), $this->option('create-missing'), $this->option('replace'));
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
