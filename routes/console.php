<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('hspm:exports-clean', function () {
    $disk = Storage::disk('local');
    foreach ($disk->allFiles('exports') as $file) {
        if ($disk->lastModified($file) < now()->subDay()->timestamp) {
            $disk->delete($file);
        }
    }
    $this->info('Экспорты старше суток удалены.');
})->purpose('Удалить временные выгрузки старше суток');
Schedule::command('hspm:exports-clean')->daily();
