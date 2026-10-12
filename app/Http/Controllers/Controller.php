<?php

namespace App\Http\Controllers;

/**
 * Базовый контроллер приложения.
 *
 * Не наследует Illuminate\Routing\Controller, поскольку приложению не требуются
 * стандартные трейты авторизации/валидации; вместо этого определяет единый контракт
 * успешного JSON-ответа, совместимый с клиентом учебного отдела.
 */
abstract class Controller
{
    /**
     * Формирует единообразный успешный JSON-ответ.
     *
     * @param  mixed   $data    Полезные данные (модель, массив или null).
     * @param  string  $message Сообщение для пользователя (по умолчанию «OK»).
     * @param  int     $status  HTTP-статус ответа (по умолчанию 200).
     * @return \Illuminate\Http\JsonResponse Ответ вида {success, data, message}.
     */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200)
    {
        return response()->json(compact('data', 'message') + ['success' => true], $status);
    }
}
