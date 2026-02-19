<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Videos;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;
use IRIS\SDK\Resources\Profiles\Video;
use IRIS\SDK\Resources\Profiles\VideoCollection;

/**
 * Videos Resource
 *
 * Manage video content for profiles.
 *
 * @example
 * ```php
 * // List videos
 * $videos = $iris->videos->list(['profile_id' => 123]);
 *
 * // Create video from external URL
 * $video = $iris->videos->create([
 *     'profile_id' => 123,
 *     'title' => 'My Video',
 *     'media_id' => 'abc123',
 *     'description' => 'Video description'
 * ]);
 *
 * // Upload video file
 * $video = $iris->videos->upload('/path/to/video.mp4', [
 *     'profile_id' => 123,
 *     'title' => 'Uploaded Video'
 * ]);
 * ```
 */
class VideosResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List videos with filters.
     *
     * @param array{
     *     profile_id?: int,
     *     status?: int,
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return VideoCollection
     */
    public function list(array $filters = []): VideoCollection
    {
        $response = $this->http->get('/api/v1/videos', $filters, 'fl-api');

        $videos = array_map(
            fn($data) => new Video($data),
            $response['data'] ?? []
        );

        return new VideoCollection($videos, $response['meta'] ?? []);
    }

    /**
     * Get a single video by ID.
     *
     * @param int $videoId Video ID
     * @return Video
     */
    public function get(int $videoId): Video
    {
        $response = $this->http->get("/api/v1/videos/{$videoId}", [], 'fl-api');

        return new Video($response['data'] ?? $response);
    }

    /**
     * Create a new video.
     *
     * @param array{
     *     profile_id: int,
     *     title: string,
     *     description?: string,
     *     media_id: string,
     *     thumbnail_url?: string,
     *     twitter?: string,
     *     instagram?: string,
     *     status?: int
     * } $data Video data
     * @return Video
     */
    public function create(array $data): Video
    {
        $response = $this->http->post('/api/v1/videos', $data, 'fl-api');

        return new Video($response['data'] ?? $response);
    }

    /**
     * Update an existing video.
     *
     * @param int $videoId Video ID
     * @param array $data Updated video data
     * @return Video
     */
    public function update(int $videoId, array $data): Video
    {
        $response = $this->http->put("/api/v1/videos/{$videoId}", $data, 'fl-api');

        return new Video($response['data'] ?? $response);
    }

    /**
     * Delete a video.
     *
     * @param int $videoId Video ID
     * @return bool
     */
    public function delete(int $videoId): bool
    {
        $this->http->delete("/api/v1/videos/{$videoId}", [], 'fl-api');

        return true;
    }

    /**
     * Upload a video file and create video record.
     *
     * @param string $filePath Path to video file
     * @param array{
     *     profile_id: int,
     *     title: string,
     *     description?: string,
     *     thumbnail_url?: string,
     *     twitter?: string,
     *     instagram?: string,
     *     status?: int
     * } $data Additional video data
     * @return Video
     */
    public function upload(string $filePath, array $data = []): Video
    {
        $response = $this->http->upload('/api/v1/videos/upload', $filePath, $data, 'fl-api');

        return new Video($response['data'] ?? $response);
    }
}