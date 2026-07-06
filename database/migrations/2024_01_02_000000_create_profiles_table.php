<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            $table->string('platform'); // instagram | youtube
            $table->string('username');
            $table->string('username_normalized'); // lower(username), set on save - backs the partial unique index below

            $table->string('external_id')->nullable(); // provider's own profile id, filled in after first successful fetch
            $table->string('display_name')->nullable();
            $table->text('avatar_url')->nullable();

            // fetch state machine: pending -> fetching -> fetched | failed
            $table->string('status')->default('pending');
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestampTz('last_fetch_attempted_at')->nullable();
            $table->timestampTz('last_fetched_at')->nullable(); // last SUCCESSFUL fetch - scheduler uses this to find stale profiles
            $table->text('last_error')->nullable();

            // denormalized latest stats - kept in sync transactionally whenever a new
            // snapshot is written, so the watchlist list never has to join/window-function
            $table->unsignedBigInteger('last_followers_count')->nullable();
            $table->unsignedBigInteger('last_following_count')->nullable();
            $table->unsignedBigInteger('last_posts_count')->nullable();

            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampTz('deleted_at')->nullable();
            $table->timestampsTz();
        });

        // CHECK constraints - defense in depth alongside the PHP-level backed enums
        DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_platform_check CHECK (platform IN ('instagram', 'youtube'))");
        DB::statement("ALTER TABLE profiles ADD CONSTRAINT profiles_status_check CHECK (status IN ('pending', 'fetching', 'fetched', 'failed'))");

        // partial unique index - only enforced among active (non soft-deleted) rows,
        // so a removed handle can be re-added later without a stale row blocking it
        DB::statement('CREATE UNIQUE INDEX profiles_platform_username_unique ON profiles (platform, username_normalized) WHERE deleted_at IS NULL');

        DB::statement('CREATE INDEX profiles_status_index ON profiles (status)');
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
