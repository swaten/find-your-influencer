<?php

namespace App\Http\Controllers\Api;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InfluencerController extends Controller
{
    // list view reads only the denormalized last_* columns on profiles - no join to
    // profile_snapshots needed here, that's reserved for the per-profile detail page
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $search = $request->input('search');
        $platform = $request->input('platform');
        $status = $request->input('status');

        $profiles = Profile::query()
            ->when($search, fn ($query) => $query->where('username', 'ilike', "%{$search}%"))
            ->when($platform, fn ($query) => $query->where('platform', $platform))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (Profile $profile) => [
                'id' => $profile->id,
                'handle' => '@'.$profile->username,
                'platform' => $profile->platform->value,
                'followers' => $profile->last_followers_count,
                'status' => $profile->status->value,
                'last_synced_at' => $profile->last_fetched_at?->diffForHumans(),
            ]);

        return response()->json($profiles);
    }

    // manual trigger for now - always queued, never run inline, so a slow provider never blocks the request
    public function refresh(Profile $profile)
    {
        FetchProfileJob::dispatch($profile->id);

        return response()->json(['message' => 'Refresh queued.']);
    }

    // adds a handle to the watchlist and queues its first fetch immediately
    public function store(Request $request)
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(array_column(Platform::cases(), 'value'))],
            'username' => ['required', 'string', 'max:255'],
        ]);

        // strip a leading @ regardless of platform - storage is always bare, the UI
        // is what prepends exactly one @ when displaying a handle
        $username = ltrim(trim($data['username']), '@');
        $usernameNormalized = mb_strtolower($username);

        // mirrors the partial unique index (platform, username_normalized) where not soft-deleted
        $duplicate = Profile::query()
            ->where('platform', $data['platform'])
            ->where('username_normalized', $usernameNormalized)
            ->whereNull('deleted_at')
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'username' => ['This profile is already on your watchlist.'],
            ]);
        }

        $profile = Profile::create([
            'platform' => $data['platform'],
            'username' => $username,
            'added_by' => $request->user()->id,
        ]);

        FetchProfileJob::dispatch($profile->id);

        return response()->json([
            'message' => 'Added. Fetching stats now.',
            'id' => $profile->id,
        ], 201);
    }

    // profile detail + recent snapshot history, for the per-profile page
    public function show(Profile $profile)
    {
        $snapshots = $profile->snapshots()
            ->orderByDesc('fetched_at')
            ->limit(30)
            ->get(['id', 'followers_count', 'following_count', 'posts_count', 'fetched_at']);

        return response()->json([
            'id' => $profile->id,
            'platform' => $profile->platform->value,
            'username' => $profile->username,
            'display_name' => $profile->display_name,
            'avatar_url' => $profile->avatar_url,
            'status' => $profile->status->value,
            'last_error' => $profile->last_error,
            'consecutive_failures' => $profile->consecutive_failures,
            'last_followers_count' => $profile->last_followers_count,
            'last_following_count' => $profile->last_following_count,
            'last_posts_count' => $profile->last_posts_count,
            'last_fetched_at' => $profile->last_fetched_at,
            'added_by' => $profile->addedBy?->name,
            'created_at' => $profile->created_at,
            'snapshots' => $snapshots,
        ]);
    }

    // soft delete - the partial unique index lets the same handle be re-added later
    public function destroy(Profile $profile)
    {
        $profile->delete();

        return response()->json(['message' => 'Removed from watchlist.']);
    }
}
