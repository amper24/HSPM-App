<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** Учётная запись; пароль никогда не сериализуется в API. */
class User extends Authenticatable
{
    protected $fillable = ['username', 'password', 'role', 'full_name'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
