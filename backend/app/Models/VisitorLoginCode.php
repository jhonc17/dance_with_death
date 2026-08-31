<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class VisitorLoginCode extends Model
{
    private const MINUTES = 15;

    private const MAX_ATTEMPTS = 5;

    public $timestamps = false;

    protected $fillable = ['email', 'code_hash', 'attempts', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function issue(string $email, string $plain): void
    {
        static::where('expires_at', '<=', now('UTC'))->delete();

        static::updateOrCreate(
            ['email' => $email],
            [
                'code_hash' => Hash::make($plain),
                'attempts' => 0,
                'expires_at' => now('UTC')->addMinutes(self::MINUTES),
            ]
        );
    }

    /** @return 'ok'|'invalid'|'expired' */
    public static function consume(string $email, string $plain): string
    {
        $row = static::where('email', $email)->first();

        if (! $row || $row->expires_at->lte(now('UTC'))) {
            $row?->delete();

            return 'expired';
        }

        if (! Hash::check($plain, $row->code_hash)) {
            $row->increment('attempts');
            if ($row->attempts >= self::MAX_ATTEMPTS) {
                $row->delete();
            }

            return 'invalid';
        }

        $row->delete();

        return 'ok';
    }

    public static function discard(string $email): void
    {
        static::where('email', $email)->delete();
    }
}
