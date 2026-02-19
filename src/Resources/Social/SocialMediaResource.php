<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Social;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Social Media Resource
 *
 * Publish content to social media platforms via UploadPost integration.
 * 
 * Supported platforms:
 * - Instagram (posts, reels, stories)
 * - TikTok
 * - X (Twitter)
 * - Threads
 * - Facebook
 * - YouTube
 * - LinkedIn
 * - Pinterest
 * - Reddit
 *
 * @example
 * ```php
 * // Publish video to Instagram and TikTok
 * $result = $iris->social->publishVideo([
 *     'file_path' => '/path/to/video.mp4',
 *     'title' => 'Check out this amazing content!',
 *     'platforms' => ['instagram', 'tiktok'],
 *     'options' => [
 *         'media_type' => 'REELS',
 *         'share_to_feed' => true,
 *     ],
 * ]);
 *
 * // Publish text to Twitter and Threads
 * $result = $iris->social->publishText([
 *     'text' => 'Hello social media!',
 *     'platforms' => ['twitter', 'threads'],
 * ]);
 *
 * // Check upload status
 * $status = $iris->social->getStatus($result->getRequestId());
 * ```
 */
class SocialMediaResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Publish text to social platforms.
     *
     * @param array{
     *     text: string,
     *     platforms: array<string>,
     *     user?: string,
     *     description?: string,
     *     scheduled_date?: string,
     *     async_upload?: bool,
     *     x_title?: string,
     *     threads_title?: string,
     *     linkedin_title?: string
     * } $params
     * @return SocialPublishResult
     */
    public function publishText(array $params): SocialPublishResult
    {
        $response = $this->http->post('/api/social/publish-text', $params);
        return SocialPublishResult::fromResponse($response);
    }

    /**
     * Publish video to social platforms.
     *
     * @param array{
     *     file_path: string,
     *     title: string,
     *     platforms: array<string>,
     *     user?: string,
     *     description?: string,
     *     async_upload?: bool,
     *     scheduled_date?: string,
     *     options?: array
     * } $params
     * @return SocialPublishResult
     */
    public function publishVideo(array $params): SocialPublishResult
    {
        $response = $this->http->post('/api/social/publish-video', $params);
        return SocialPublishResult::fromResponse($response);
    }

    /**
     * Publish photo(s) to social platforms.
     *
     * @param array{
     *     photos: array<string>,
     *     title: string,
     *     platforms: array<string>,
     *     user?: string,
     *     description?: string,
     *     async_upload?: bool,
     *     options?: array
     * } $params
     * @return SocialPublishResult
     */
    public function publishPhoto(array $params): SocialPublishResult
    {
        $response = $this->http->post('/api/social/publish-photo', $params);
        return SocialPublishResult::fromResponse($response);
    }

    /**
     * Publish Instagram Reel (convenience method).
     *
     * @param string $videoPath Video file path
     * @param string $title Post title/caption
     * @param array{
     *     share_to_feed?: bool,
     *     cover_url?: string,
     *     description?: string
     * } $options
     * @return SocialPublishResult
     */
    public function publishInstagramReel(
        string $videoPath,
        string $title,
        array $options = []
    ): SocialPublishResult {
        return $this->publishVideo([
            'file_path' => $videoPath,
            'title' => $title,
            'platforms' => ['instagram'],
            'options' => array_merge([
                'media_type' => 'REELS',
                'share_to_feed' => true,
            ], $options),
        ]);
    }

    /**
     * Publish TikTok video (convenience method).
     *
     * @param string $videoPath Video file path
     * @param string $title Video title
     * @param array{
     *     privacy_level?: string,
     *     disable_comment?: bool,
     *     disable_duet?: bool,
     *     disable_stitch?: bool
     * } $options
     * @return SocialPublishResult
     */
    public function publishTikTok(
        string $videoPath,
        string $title,
        array $options = []
    ): SocialPublishResult {
        return $this->publishVideo([
            'file_path' => $videoPath,
            'title' => $title,
            'platforms' => ['tiktok'],
            'options' => $options,
        ]);
    }

    /**
     * Get upload status for async uploads.
     *
     * @param string $requestId Request ID from publish result
     * @return SocialStatusResult
     */
    public function getStatus(string $requestId): SocialStatusResult
    {
        $response = $this->http->get('/api/social/status', [
            'request_id' => $requestId,
        ]);

        return SocialStatusResult::fromResponse($response);
    }

    /**
     * Get upload history.
     *
     * @param array{
     *     page?: int,
     *     per_page?: int
     * } $params
     * @return array
     */
    public function getHistory(array $params = []): array
    {
        return $this->http->get('/api/social/history', $params);
    }

    /**
     * Publish compilation to music series platforms.
     * 
     * High-level method for EMC Music Series use case:
     * - Merges audio tracks with crossfade
     * - Creates video with cover art
     * - Publishes to Instagram, TikTok, YouTube
     *
     * @param array{
     *     tracks: array<string>,
     *     title: string,
     *     description: string,
     *     cover_art?: string,
     *     platforms?: array<string>,
     *     crossfade_duration?: int
     * } $params
     * @return SocialPublishResult
     */
    public function publishCompilation(array $params): SocialPublishResult
    {
        $response = $this->http->post('/api/social/publish-compilation', $params);
        return SocialPublishResult::fromResponse($response);
    }
}
