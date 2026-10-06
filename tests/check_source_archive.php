<?php

use App\Services\Import\ImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** Optional integration check against the user's source directory. No production DB is used. */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
DB::purge();
Artisan::call('migrate', ['--force' => true]);
$directory = $argv[1] ?? null;
if (! $directory || ! is_dir($directory)) {
    fwrite(STDERR, "Usage: php tests/check_source_archive.php <source-directory>\n");
    exit(1);
}
$results = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
foreach ($files as $file) {
    if (! preg_match('/\.xlsx?$/i', $file->getFilename())) {
        continue;
    }
    $name = $file->getFilename();
    if (str_contains($name, 'ЖУРНАЛ')) {
        continue;
    } // A change journal is not a timetable snapshot.
    $types = str_contains($name, 'преподавателей') ? ['teachers'] : (str_contains($name, 'Тех_хар') ? ['classrooms', 'software'] : ['schedule']);
    foreach ($types as $type) {
        $started = microtime(true);
        $queries = 0;
        DB::connection()->setEventDispatcher(new Dispatcher);
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        try {
            $first = app(ImportService::class)->import($file->getPathname(), $type);
            $counts = [];
            foreach (['teachers', 'classrooms', 'software', 'schedule', 'schedule_classrooms'] as $table) {
                $counts[$table] = DB::table($table)->count();
            }
            $second = app(ImportService::class)->import($file->getPathname(), $type);
            foreach ($counts as $table => $count) {
                if (DB::table($table)->count() !== $count) {
                    throw new RuntimeException('Repeated import changes row count: '.$table);
                }
            }
            $results[] = ['file' => $name, 'type' => $type, 'status' => 'ok', 'rows' => $first['imported'], 'queries_two_runs' => $queries, 'seconds' => round(microtime(true) - $started, 2)];
        } catch (Throwable $error) {
            $results[] = ['file' => $name, 'type' => $type, 'status' => 'error', 'error' => $error->getMessage()];
        }
        echo json_encode(end($results), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
        gc_collect_cycles();
    }
}
if (isset($argv[2])) {
    file_put_contents($argv[2], json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
