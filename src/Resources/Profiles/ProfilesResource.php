<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Profiles Resource
 *
 * Manage user profiles and media content (videos, tracks, images).
 *
 * @example
 * ```php
 * // Create a new profile
 * $profile = $iris->profiles->create([
 *     'name' => 'B&C Detailing',
 *     'bio' => 'Professional mobile detailing service',
 *     'city' => 'Fort Worth',
 *     'state' => 'TX',
 *     'country' => 'United States',
 *     'country_code' => 'US',
 *     'phone' => '817-854-6161',
 *     'website_url' => 'https://bcdetailing.com',
 *     'instagram' => 'bcdetailing'
 * ]);
 *
 * // List user profiles
 * $profiles = $iris->profiles->list();
 *
 * // Search profiles
 * $results = $iris->profiles->search('siralexmayo', [
 *     'limit' => 5,
 *     'order_by' => 'views'
 * ]);
 *
 * // Get media counts for a profile
 * $counts = $iris->profiles->getMediaCounts(1);
 *
 * // Get videos for a profile
 * $videos = $iris->profiles->getVideos(1);
 *
 * // Get tracks/audio for a profile
 * $tracks = $iris->profiles->getTracks(69);
 * ```
 */
class ProfilesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all profiles for the current user.
     *
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     search?: string,
     *     limit?: int,
     *     order_by?: string
     * } $filters Filter options
     * @return ProfileCollection
     */
    public function list(array $filters = []): ProfileCollection
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/user/{$userId}/profiles", $filters);

        return new ProfileCollection(
            array_map(fn($data) => new Profile($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Search profiles by username or keyword.
     *
     * @param string $search Search query
     * @param array{
     *     limit?: int,
     *     order_by?: string
     * } $options Search options
     * @return ProfileCollection
     */
    public function search(string $search, array $options = []): ProfileCollection
    {
        $params = array_merge(['search' => $search], $options);
        $response = $this->http->get("/api/v1/user/profiles", $params);

        return new ProfileCollection(
            array_map(fn($data) => new Profile($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Create a new profile.
     *
     * @param array{
     *     name: string,
     *     bio?: string,
     *     city?: string,
     *     state?: string,
     *     country?: string,
     *     country_code?: string,
     *     lat?: float,
     *     lng?: float,
     *     email?: string,
     *     phone?: string,
     *     photo?: string,
     *     website_url?: string,
     *     twitter?: string,
     *     instagram?: string,
     *     tiktok?: string,
     *     youtube?: string,
     *     spotify?: string,
     *     facebook?: string,
     *     linkedin?: string,
     *     github?: string,
     *     twitch?: string,
     *     soundcloud?: string,
     *     active?: int,
     *     add_profile_to_user?: bool
     * } $data Profile data
     * @return Profile
     */
    public function create(array $data): Profile
    {
        $userId = $this->config->requireUserId();

        // Format data with defaults
        $payload = array_merge([
            'user_id' => $userId,
            'add_profile_to_user' => true,
            'active' => 1,
            'date_created' => date('c')
        ], $data);

        // Strip @ from social handles if present
        foreach (['twitter', 'instagram', 'tiktok', 'youtube', 'spotify', 'facebook', 'linkedin', 'github', 'twitch', 'soundcloud'] as $platform) {
            if (isset($payload[$platform]) && is_string($payload[$platform])) {
                $payload[$platform] = ltrim($payload[$platform], '@');
            }
        }

        $response = $this->http->post("/api/v1/profile", $payload);

        return new Profile($response['data']['profile'] ?? $response['profile'] ?? $response);
    }

    /**
     * Get a single profile by ID.
     *
     * @param int $profileId Profile ID
     * @return Profile
     */
    public function get(int $profileId): Profile
    {
        $response = $this->http->get("/api/v1/profile/{$profileId}");

        return new Profile($response);
    }

    /**
     * Get media counts (videos, tracks, images) for a profile.
     *
     * @param int $profileId Profile ID
     * @return MediaCounts
     */
    public function getMediaCounts(int $profileId): MediaCounts
    {
        $response = $this->http->get("/api/v1/profile/{$profileId}/media/counts");

        return new MediaCounts($response);
    }

    /**
     * Get all videos for a profile.
     *
     * @param int $profileId Profile ID
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     sort?: string
     * } $filters Filter options
     * @return VideoCollection
     */
    public function getVideos(int $profileId, array $filters = []): VideoCollection
    {
        $response = $this->http->get("/api/v1/user/profile/media/{$profileId}/videos", $filters);

        return new VideoCollection(
            array_map(fn($data) => new Video($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Get all audio tracks for a profile.
     *
     * @param int $profileId Profile ID
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     sort?: string
     * } $filters Filter options
     * @return TrackCollection
     */
    public function getTracks(int $profileId, array $filters = []): TrackCollection
    {
        $response = $this->http->get("/api/v1/user/profile/media/{$profileId}/tracks", $filters);

        return new TrackCollection(
            array_map(fn($data) => new Track($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Update an existing profile.
     *
     * @param int $profileId Profile ID
     * @param array $data Updated profile data
     * @return Profile
     */
    public function update(int $profileId, array $data): Profile
    {
        // Strip @ from social handles if present
        foreach (['twitter', 'instagram', 'tiktok', 'youtube', 'spotify', 'facebook', 'linkedin', 'github', 'twitch', 'soundcloud'] as $platform) {
            if (isset($data[$platform]) && is_string($data[$platform])) {
                $data[$platform] = ltrim($data[$platform], '@');
            }
        }

        $response = $this->http->put("/api/v1/profile/{$profileId}", $data);

        return new Profile($response['data']['profile'] ?? $response['profile'] ?? $response);
    }

    /**
     * Delete a profile.
     *
     * @param int $profileId Profile ID
     * @return bool
     */
    public function delete(int $profileId): bool
    {
        $this->http->delete("/api/v1/profile/{$profileId}");

        return true;
    }

    /**
     * Get playlists for a profile.
     *
     * @param int|null $profileId Profile ID (optional, uses current user's if not provided)
     * @return PlaylistCollection
     */
    public function getPlaylists(?int $profileId = null): PlaylistCollection
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/collections", [
            'profile_id' => $profileId,
        ], 'fl-api');

        $playlists = array_map(
            fn($data) => new Playlist($data),
            $response['data'] ?? []
        );

        return new PlaylistCollection($playlists, $response['meta'] ?? []);
    }

    /**
     * Create a new playlist.
     *
     * @param array{
     *     title: string,
     *     description?: string,
     *     profile_id?: int
     * } $data Playlist data
     * @return Playlist
     */
    public function createPlaylist(array $data): Playlist
    {
        $userId = $this->config->requireUserId();
        $payload = array_merge($data, ['user_id' => $userId]);

        $response = $this->http->post('/api/v1/user/collections', $payload, 'fl-api');

        return new Playlist($response['data'] ?? $response);
    }

    /**
     * Get a single playlist by ID.
     *
     * @param int|string $playlistId Playlist ID or unique_id
     * @return Playlist
     */
    public function getPlaylist(int|string $playlistId): Playlist
    {
        $response = $this->http->get("/api/v1/user/collections/{$playlistId}", [], 'fl-api');

        return new Playlist($response['data'] ?? $response);
    }

    /**
     * Update a playlist.
     *
     * @param int|string $playlistId Playlist ID or unique_id
     * @param array $data Updated playlist data
     * @return Playlist
     */
    public function updatePlaylist(int|string $playlistId, array $data): Playlist
    {
        $response = $this->http->put("/api/v1/user/collections/{$playlistId}", $data, 'fl-api');

        return new Playlist($response['data'] ?? $response);
    }

    /**
     * Delete a playlist.
     *
     * @param int|string $playlistId Playlist ID or unique_id
     * @return bool
     */
    public function deletePlaylist(int|string $playlistId): bool
    {
        $this->http->delete("/api/v1/user/collections/{$playlistId}", [], 'fl-api');

        return true;
    }

    /**
     * Add an item to a playlist.
     *
     * @param int|string $playlistId Playlist ID or unique_id
     * @param int $postId Post/media ID
     * @param string $mediaType Media type (article, video, etc.)
     * @return array
     */
    public function addToPlaylist(int|string $playlistId, int $postId, string $mediaType): array
    {
        $userId = $this->config->requireUserId();
        $payload = [
            'unique_id' => $playlistId,
            'post_id' => $postId,
            'media_type' => $mediaType,
            'user_id' => $userId,
        ];

        return $this->http->post("/api/v1/user/collections/{$playlistId}/attach", $payload, 'fl-api');
    }

    /**
     * Remove an item from a playlist.
     *
     * @param int|string $playlistId Playlist ID or unique_id
     * @param string $mediaType Media type
     * @param int $mediaId Media ID
     * @return bool
     */
    public function removeFromPlaylist(int|string $playlistId, string $mediaType, int $mediaId): bool
    {
        $this->http->delete("/api/v1/user/collections/{$playlistId}/{$mediaType}/{$mediaId}", [], 'fl-api');

        return true;
    }
}
