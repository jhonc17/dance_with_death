<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email?} {password?}';

    protected $description = 'Create an admin. Pass email and password, or use -it to be prompted.';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Email'))));
        $password = (string) ($this->argument('password') ?: $this->secret('Password'));

        if ($email === '' || $password === '') {
            $this->error('Need email and password.');
            $this->line('Interactive: docker exec -it <backend> php artisan admin:create');
            $this->line('Or: docker exec <backend> php artisan admin:create email@x.com secretpass');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email.');

            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('That email is already an admin.');

            return self::FAILURE;
        }

        User::create([
            'name' => Str::before($email, '@') ?: 'Admin',
            'email' => $email,
            'password' => $password,
        ]);

        $this->info("Admin {$email} created.");

        return self::SUCCESS;
    }
}
