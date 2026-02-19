<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Articles;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Articles Resource
 *
 * Generate articles from various sources:
 * - YouTube videos (transcript-based)
 * - Topics (research-based)
 * - Webpages (content extraction)
 * - RSS feeds (synthesis)
 *
 * @example
 * ```php
 * // Generate article from YouTube video
 * $result = $iris->articles->generateFromVideo([
 *     'youtube_url' => 'https://www.youtube.com/watch?v=abc123',
 *     'article_length' => 'medium',
 *     'article_style' => 'informative',
 * ]);
 *
 * // Generate from any source
 * $result = $iris->articles->generate([
 *     'source_type' => 'video',
 *     'source' => 'https://www.youtube.com/watch?v=abc123',
 * ]);
 * ```
 */
class ArticlesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * Generate article from various sources.
     *
     * @param array{
     *     source_type: string,
     *     source: string,
     *     article_length?: string,
     *     article_style?: string,
     *     profile_id?: int,
     *     publish_to_fl?: bool,
     *     publish_to_social?: bool,
     *     social_platforms?: array,
     *     photo?: string,
     *     generate_image?: bool,
     *     user_id?: int
     * } $params Generation parameters
     * @return array Job dispatch result
     *
     * @example
     * ```php
     * $result = $iris->articles->generate([
     *     'source_type' => 'video',
     *     'source' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
     *     'article_length' => 'medium',
     *     'article_style' => 'informative',
     * ]);
     * ```
     */
    public function generate(array $params): array
    {
        // Auto-inject user_id if not provided
        if (!isset($params['user_id']) && $this->config->userId) {
            $params['user_id'] = $this->config->userId;
        }

        return $this->http->post('/api/v1/articles/generate', $params, 'fl-api');
    }

    /**
     * Generate article from YouTube video.
     *
     * Convenience method for video source type.
     *
     * @param array{
     *     youtube_url: string,
     *     article_length?: string,
     *     article_style?: string,
     *     profile_id?: int,
     *     publish_to_fl?: bool,
     *     publish_to_social?: bool,
     *     social_platforms?: array,
     *     photo?: string,
     *     generate_image?: bool,
     *     user_id?: int
     * } $params Generation parameters
     * @return array Job dispatch result
     *
     * @example
     * ```php
     * $result = $iris->articles->generateFromVideo([
     *     'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
     *     'article_length' => 'long',
     *     'article_style' => 'analysis',
     * ]);
     * ```
     */
    public function generateFromVideo(array $params): array
    {
        // Auto-inject user_id if not provided
        if (!isset($params['user_id']) && $this->config->userId) {
            $params['user_id'] = $this->config->userId;
        }

        return $this->http->post('/api/v1/articles/generate-from-video', $params, 'fl-api');
    }

    /**
     * Generate article from topic research.
     *
     * @param string $topic Topic to research
     * @param array $options Additional options
     * @return array Job dispatch result
     */
    public function generateFromTopic(string $topic, array $options = []): array
    {
        return $this->generate(array_merge([
            'source_type' => 'topic',
            'source' => $topic,
        ], $options));
    }

    /**
     * Generate article from webpage.
     *
     * @param string $url Webpage URL
     * @param array $options Additional options
     * @return array Job dispatch result
     */
    public function generateFromWebpage(string $url, array $options = []): array
    {
        return $this->generate(array_merge([
            'source_type' => 'webpage',
            'source' => $url,
        ], $options));
    }

    /**
     * Generate article from RSS feed.
     *
     * @param string $feedUrl RSS feed URL
     * @param array $options Additional options
     * @return array Job dispatch result
     */
    public function generateFromRss(string $feedUrl, array $options = []): array
    {
        return $this->generate(array_merge([
            'source_type' => 'rss',
            'source' => $feedUrl,
        ], $options));
    }

    /**
     * Generate article from research notes.
     *
     * Transforms raw research notes into a polished, structured article.
     * Use for unorganized notes, bullet points, or raw findings.
     *
     * @param string $content Research notes content (inline text)
     * @param array{
     *     article_length?: string,
     *     article_style?: string,
     *     profile_id?: int,
     *     publish_to_fl?: bool,
     *     article_status?: int,
     *     output_format?: string,
     *     user_id?: int
     * } $options Additional options
     * @return array Job dispatch result
     *
     * @example
     * ```php
     * $result = $iris->articles->generateFromResearchNotes(
     *     "AI trends 2025: - Telemedicine up 300% - AI diagnostics improving - Patient engagement focus",
     *     ['article_length' => 'medium', 'profile_id' => 9203684]
     * );
     * ```
     */
    public function generateFromResearchNotes(string $content, array $options = []): array
    {
        return $this->generate(array_merge([
            'source_type' => 'research-notes',
            'source' => $content,
            'content' => $content,
        ], $options));
    }

    /**
     * Generate article from draft.
     *
     * Polishes an existing article draft to publication quality.
     * Preserves author's voice while improving grammar, clarity, and flow.
     *
     * @param string $draft Existing article draft content
     * @param array{
     *     editing_instructions?: string,
     *     article_length?: string,
     *     article_style?: string,
     *     profile_id?: int,
     *     publish_to_fl?: bool,
     *     article_status?: int,
     *     output_format?: string,
     *     user_id?: int
     * } $options Additional options (editing_instructions for guided edits)
     * @return array Job dispatch result
     *
     * @example
     * ```php
     * $result = $iris->articles->generateFromDraft(
     *     "# My Draft\nThis is my rough article that needs polishing...",
     *     [
     *         'editing_instructions' => 'Make more casual, add examples',
     *         'profile_id' => 9203684
     *     ]
     * );
     * ```
     */
    public function generateFromDraft(string $draft, array $options = []): array
    {
        return $this->generate(array_merge([
            'source_type' => 'draft',
            'source' => $draft,
            'content' => $draft,
        ], $options));
    }

    /**
     * Create a new article.
     *
     * @param array{
     *     profile_id: int,
     *     title: string,
     *     content: string,
     *     photo?: string,
     *     is_bulletin?: bool,
     *     status?: int
     * } $data Article data
     * @return Article
     */
    public function create(array $data): Article
    {
        $response = $this->http->post('/api/v1/articles', $data, 'fl-api');

        return new Article($response['data'] ?? $response);
    }

    /**
     * List articles with filters.
     *
     * @param array{
     *     profile_id?: int,
     *     status?: int,
     *     search?: string,
     *     page?: int,
     *     per_page?: int
     * } $filters Filter options
     * @return ArticleCollection
     */
    public function list(array $filters = []): ArticleCollection
    {
        $response = $this->http->get('/api/v1/articles', $filters, 'fl-api');

        $articles = array_map(
            fn($data) => new Article($data),
            $response['data'] ?? []
        );

        return new ArticleCollection($articles, $response['meta'] ?? []);
    }

    /**
     * Get a single article by ID.
     *
     * @param int $articleId Article ID
     * @return Article
     */
    public function get(int $articleId): Article
    {
        $response = $this->http->get("/api/v1/articles/{$articleId}", [], 'fl-api');

        return new Article($response['data'] ?? $response);
    }

    /**
     * Update an existing article.
     *
     * @param int $articleId Article ID
     * @param array $data Updated article data
     * @return Article
     */
    public function update(int $articleId, array $data): Article
    {
        $response = $this->http->put("/api/v1/articles/{$articleId}", $data, 'fl-api');

        return new Article($response['data'] ?? $response);
    }

    /**
     * Delete an article.
     *
     * @param int $articleId Article ID
     * @return bool
     */
    public function delete(int $articleId): bool
    {
        $this->http->delete("/api/v1/articles/{$articleId}", [], 'fl-api');

        return true;
    }
}
