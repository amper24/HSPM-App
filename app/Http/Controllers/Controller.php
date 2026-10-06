<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Единый контракт ответа, совместимый с клиентом учебного отдела. */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200)
    {
        return response()->json(compact('data', 'message') + ['success' => true], $status);
    }
}
