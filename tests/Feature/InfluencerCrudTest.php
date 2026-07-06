<?php

namespace Tests\Feature;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InfluencerCrudTest extends TestCase
{
    use RefreshDatabase;

    // ── negative: auth is required on every influencer route ──────────────

    public function test_guest_cannot_list_influencers(): void
    {
        $this->getJson('/api/influencers')->assertUnauthorized();
    }

    public function test_guest_cannot_add_an_influencer(): void
    {
        $this->postJson('/api/influencers', ['platform' => 'instagram', 'username' => 'someone'])
            ->assertUnauthorized();
    }

    // ── positive: list, search, filter, paginate ───────────────────────────

    public function test_authenticated_user_can_list_influencers(): void
    {
        $user = User::factory()->create();
        Profile::factory()->count(3)->create(['added_by' => $user->id]);

        $this->actingAs($user)
            ->getJson('/api/influencers')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_can_be_filtered_by_platform_and_searched_by_username(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create(['platform' => 'instagram', 'username' => 'dhruvrathee', 'added_by' => $user->id]);
        Profile::factory()->create(['platform' => 'youtube', 'username' => 'mrbeast', 'added_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/influencers?platform=youtube');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('youtube', $response->json('data.0.platform'));

        $response = $this->actingAs($user)->getJson('/api/influencers?search=dhruv');
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('@dhruvrathee', $response->json('data.0.handle'));
    }

    public function test_list_is_paginated(): void
    {
        $user = User::factory()->create();
        Profile::factory()->count(15)->create(['added_by' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/influencers?per_page=10');
        $response->assertOk()->assertJsonCount(10, 'data');
        $this->assertSame(15, $response->json('total'));
        $this->assertSame(2, $response->json('last_page'));
    }

    // ── add handle: positive + negative ─────────────────────────────────────

    public function test_can_add_a_valid_instagram_handle_and_it_queues_the_first_fetch(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/influencers', [
            'platform' => 'instagram',
            'username' => 'dhruvrathee',
        ]);

        $response->assertCreated()->assertJsonStructure(['message', 'id']);

        $this->assertDatabaseHas('profiles', [
            'platform' => 'instagram',
            'username' => 'dhruvrathee',
            'status' => 'pending',
        ]);

        Queue::assertPushed(FetchProfileJob::class, fn (FetchProfileJob $job) => $job->profileId === $response->json('id'));
    }

    public function test_can_add_a_valid_youtube_handle_with_a_leading_at_sign_stripped(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/influencers', [
            'platform' => 'youtube',
            'username' => '@MrBeast',
        ])->assertCreated();

        // stored bare, no leading @ - the frontend/API layer is what prepends exactly one
        $this->assertDatabaseHas('profiles', [
            'platform' => 'youtube',
            'username' => 'MrBeast',
            'username_normalized' => 'mrbeast',
        ]);
    }

    public function test_adding_a_duplicate_handle_on_the_same_platform_is_rejected(): void
    {
        $user = User::factory()->create();
        Profile::factory()->create(['platform' => 'instagram', 'username' => 'dhruvrathee']);

        $this->actingAs($user)->postJson('/api/influencers', [
            'platform' => 'instagram',
            'username' => 'DhruvRathee', // different case - must still collide via username_normalized
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_the_same_handle_is_allowed_on_a_different_platform(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Profile::factory()->create(['platform' => 'instagram', 'username' => 'mrbeast']);

        $this->actingAs($user)->postJson('/api/influencers', [
            'platform' => 'youtube',
            'username' => 'mrbeast',
        ])->assertCreated();
    }

    public function test_adding_without_a_username_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/influencers', ['platform' => 'instagram', 'username' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_adding_with_an_invalid_platform_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/influencers', ['platform' => 'tiktok', 'username' => 'someone'])
            ->assertUnprocessable()->assertJsonValidationErrors('platform');
    }

    public function test_a_removed_handle_can_be_re_added(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = Profile::factory()->create(['platform' => 'instagram', 'username' => 'dhruvrathee']);
        $profile->delete(); // soft delete

        $this->actingAs($user)->postJson('/api/influencers', [
            'platform' => 'instagram',
            'username' => 'dhruvrathee',
        ])->assertCreated();
    }

    // ── detail page ──────────────────────────────────────────────────────

    public function test_can_view_a_profile_detail_with_its_snapshot_history(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create();
        $profile->snapshots()->create([
            'provider' => 'apify',
            'followers_count' => 1000,
            'following_count' => 10,
            'posts_count' => 5,
            'raw_payload' => ['x' => 1],
            'fetched_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/influencers/{$profile->id}");

        $response->assertOk()
            ->assertJsonPath('id', $profile->id)
            ->assertJsonCount(1, 'snapshots');
    }

    public function test_viewing_a_nonexistent_profile_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/influencers/999999')->assertNotFound();
    }

    // ── delete ───────────────────────────────────────────────────────────

    public function test_can_delete_a_profile_and_it_is_soft_deleted_not_removed(): void
    {
        $user = User::factory()->create();
        $profile = Profile::factory()->create();

        $this->actingAs($user)->deleteJson("/api/influencers/{$profile->id}")->assertOk();

        $this->assertSoftDeleted('profiles', ['id' => $profile->id]);
        $this->actingAs($user)->getJson('/api/influencers')->assertJsonCount(0, 'data');
    }

    public function test_deleting_a_nonexistent_profile_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson('/api/influencers/999999')->assertNotFound();
    }

    // ── manual refresh ───────────────────────────────────────────────────

    public function test_refresh_dispatches_a_fetch_job_without_running_it_inline(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $profile = Profile::factory()->create();

        $this->actingAs($user)->postJson("/api/influencers/{$profile->id}/refresh")->assertOk();

        Queue::assertPushed(FetchProfileJob::class, fn (FetchProfileJob $job) => $job->profileId === $profile->id);
    }
}
