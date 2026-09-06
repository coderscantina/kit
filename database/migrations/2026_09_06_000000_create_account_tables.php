<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('locale');
        });

        // One row per browser holding a session. The primary key is the
        // SHA-256 of the session id, never the id itself: a dump of this table
        // cannot be replayed as a cookie. Revocation is a flag the owning
        // browser trips on its next request, which is how the framework's own
        // AuthenticateSession invalidation already behaves.
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->char('id', 64)->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_active_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'last_active_at']);
        });

        // A requested address change, pending until the link mailed to the new
        // address is opened. Only the token hash is stored, as with invites.
        Schema::create('email_changes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('new_email');
            $table->char('token_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Append-only trail of the things a user would want to notice: sign
        // ins, credential changes, session revocations.
        Schema::create('security_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event', 64);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('email_changes');
        Schema::dropIfExists('user_sessions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
