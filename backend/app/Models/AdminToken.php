<?php

namespace App\Models;

use App\Support\AccessToken;
use Illuminate\Database\Eloquent\Model;

class AdminToken extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'token_hash', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function findValid(string $plain): ?self
    {
        return static::with('user')
            ->where('token_hash', AccessToken::hash($plain))
            ->where('expires_at', '>', now('UTC'))
            ->first();
    }

    public static function issue(User $user): string
    {
        static::where('expires_at', '<=', now('UTC'))->delete();

        $plain = AccessToken::plain();

        static::create([
            'user_id' => $user->id,
            'token_hash' => AccessToken::hash($plain),
            'expires_at' => AccessToken::expiresAt(),
        ]);

        return $plain;
    }
}
