<?php

namespace App\Models;

use App\Support\AccessToken;
use Illuminate\Database\Eloquent\Model;

class VisitorToken extends Model
{
    public $timestamps = false;

    protected $fillable = ['email', 'token_hash', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function findValid(string $plain): ?self
    {
        return static::where('token_hash', AccessToken::hash($plain))
            ->where('expires_at', '>', now('UTC'))
            ->first();
    }

    public static function issue(string $email): string
    {
        static::where('expires_at', '<=', now('UTC'))->delete();

        $plain = AccessToken::plain();

        static::create([
            'email' => $email,
            'token_hash' => AccessToken::hash($plain),
            'expires_at' => AccessToken::expiresAt(),
        ]);

        return $plain;
    }
}
