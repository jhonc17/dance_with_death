<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class AccessToken
{
    private const DAYS = 7;

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function plain(): string
    {
        return Str::random(64);
    }

    public static function expiresAt(): CarbonInterface
    {
        return now('UTC')->addDays(self::DAYS);
    }
}
