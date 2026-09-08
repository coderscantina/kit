<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key', 64)->unique();
            $table->string('name', 128);
            $table->unsignedSmallInteger('level')->default(0);
            $table->json('abilities');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 128);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('locale', 8)->default('en');
            // E.164, and only ever written once the code texted to it came
            // back: an unverified number is not a route the SMS channel uses.
            $table->string('phone', 24)->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('avatar_path')->nullable();
            $table->foreignUlid('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->boolean('is_root')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_backup_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('invites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email');
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
    }
};
