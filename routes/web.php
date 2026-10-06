<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FreeClassroomController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
// Cookie-сессия и CSRF Laravel действуют для всех маршрутов, включая multipart-импорт.
Route::prefix('api')->group(function () {
    Route::get('csrf', fn () => response()->json(['success' => true, 'data' => ['token' => csrf_token()]]));
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::middleware('auth')->group(function () {
        Route::get('dashboard/stats', DashboardController::class);
        Route::get('classrooms/free', FreeClassroomController::class);
        Route::get('{resource}/search', [LookupController::class, 'search'])->whereIn('resource', ['teachers', 'classrooms']);
        Route::get('{resource}/{field}', [LookupController::class, 'options'])
            ->whereIn('resource', ['teachers', 'classrooms', 'schedule', 'software'])->whereIn('field', ['departments', 'degrees', 'titles', 'employment-types', 'room-types', 'buildings', 'groups']);
        Route::get('export/{type}', [ExportController::class, 'export']);
        Route::get('exports/{filename}', [ExportController::class, 'download'])->where('filename', '[a-f0-9-]+\.xlsx');
        foreach (['teachers' => TeacherController::class, 'classrooms' => ClassroomController::class, 'software' => SoftwareController::class, 'schedule' => ScheduleController::class, 'users' => UserController::class] as $resource => $controller) {
            Route::middleware($resource === 'users' ? ['admin'] : [])->group(function () use ($resource, $controller) {
                Route::get($resource, [$controller, 'index']);
                Route::get($resource.'/{id}', [$controller, 'show'])->whereNumber('id');
                Route::middleware('admin')->group(function () use ($resource, $controller) {
                    Route::post($resource, [$controller, 'store']);
                    Route::put($resource.'/{id}', [$controller, 'update'])->whereNumber('id');
                    Route::delete($resource.'/{id}', [$controller, 'destroy'])->whereNumber('id');
                    if ($resource !== 'users') {
                        Route::post($resource.'/truncate', [$controller, 'clear']);
                    }
                });
            });
        }
        Route::post('schedule/bulk-delete', [ScheduleController::class, 'bulkDelete'])->middleware('admin');
        Route::post('import', ImportController::class)->middleware('admin');
    });
});
