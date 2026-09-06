<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the app adds on top of the framework's own tables. One file
 * rather than one per surface: none of it has shipped to a database that is
 * not disposable, so there is nothing for an incremental migration to
 * protect, and a reader gets the whole schema in one read.
 */
return new class extends Migration
{
    public function up(): void
    {
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

        // One row per (provider, account at that provider). The unique index
        // on provider + external_id is the whole security model of the
        // callback: a second account can never claim an identity that is
        // already linked.
        Schema::create('user_social_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_id');
            $table->string('nickname')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->unique(['user_id', 'provider']);
        });

        // A named list state: the search term, the filter chips, the sort and
        // the page size of one table, kept so the user can come back to it.
        // `scope` is the table's own key ("people"), so two lists never see
        // each other's views.
        Schema::create('saved_views', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 64);
            $table->string('name', 80);
            $table->json('params');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'scope', 'name']);
            $table->index(['user_id', 'scope']);
        });

        // The webpush package ships this table with `morphs()`, whose id column
        // is an auto-incrementing integer; every model here has a ULID. Written
        // out rather than published so the owner column matches. The row's own
        // key stays an integer, because the package's model owns it.
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulidMorphs('subscribable', 'push_subscriptions_subscribable_idx');
            // The endpoint is the browser's own push URL and is what identifies
            // a device; unique, so a re-subscribe replaces rather than doubles.
            $table->string('endpoint', 500)->unique();
            $table->string('public_key')->nullable();
            $table->string('auth_token')->nullable();
            $table->string('content_encoding')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('saved_views');
        Schema::dropIfExists('user_social_links');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('email_changes');
        Schema::dropIfExists('user_sessions');
    }
};
