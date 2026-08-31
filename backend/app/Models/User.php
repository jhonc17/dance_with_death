<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
