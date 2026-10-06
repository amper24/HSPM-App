<?php

use App\Http\Middleware\RequireAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => RequireAdmin::class]);
        $middleware->redirectGuestsTo(fn () => '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($exception instanceof ValidationException) {
                return response()->json(['success' => false, 'error' => collect($exception->errors())->flatten()->first(), 'errors' => $exception->errors()], 422);
            }
            if ($exception instanceof AuthenticationException) {
                return response()->json(['success' => false, 'error' => 'Требуется авторизация'], 401);
            }
            // SQL и параметры подключения не должны попадать в ответ браузеру.
            if ($exception instanceof QueryException) {
                $conflict = in_array($exception->errorInfo[0] ?? '', ['23000', '23505']);

                return response()->json(['success' => false, 'error' => $conflict ? 'Такая запись уже существует или нарушает связи данных.' : 'Не удалось выполнить операцию с базой данных.'], $conflict ? 409 : 500);
            }
            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

            return response()->json(['success' => false, 'error' => $status >= 500 ? 'Внутренняя ошибка сервера' : ($exception->getMessage() ?: 'Запрос не выполнен')], $status);
        });
    })->create();
