<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();

            $table->string('provider'); // which API actually served this snapshot (rapidapi | apify | youtube)

            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('posts_count')->nullable();

            $table->jsonb('raw_payload')->nullable(); // full API response - room to add metrics later without a migration

            $table->timestampTz('fetched_at');
            $table->timestampTz('created_at')->nullable();
        });

        // "this profile's history, newest first" and "every profile's latest snapshot"
        // both hit this instead of a full table scan
        DB::statement('CREATE INDEX profile_snapshots_profile_fetched_index ON profile_snapshots (profile_id, fetched_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_snapshots');
    }
};
