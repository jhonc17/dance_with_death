<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('admin_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->timestampTz('starts_at')->unique();
            $table->string('timezone');
            $table->timestamps();
        });

        Schema::create('visitor_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['email', 'expires_at']);
        });

        Schema::create('visitor_login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('expires_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_login_codes');
        Schema::dropIfExists('visitor_tokens');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('admin_tokens');
        Schema::dropIfExists('users');
    }
};
