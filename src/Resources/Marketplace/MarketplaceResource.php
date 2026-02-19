<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Marketplace;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Marketplace Resource
 *
 * Browse, publish, install, and manage skills in the IRIS marketplace.
 *
 * @example
 * ```php
 * $marketplace = $iris->marketplace;
 *
 * // Search skills
 * $skills = $marketplace->search('php development');
 *
 * // Get skill details
 * $skill = $marketplace->get('herd-manager');
 *
 * // Publish a skill from manifest
 * $skill = $marketplace->publish($manifest);
 *
 * // Install a skill
 * $marketplace->install('herd-manager');
 *
 * // List installed skills
 * $installed = $marketplace->installed();
 * ```
 */
class MarketplaceResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    // =========================================================================
    // BROWSING
    // =========================================================================

    public function search(string $query = '', array $filters = []): array
    {
        $params = array_merge($filters, ['q' => $query]);
        return $this->http->get('/api/v1/marketplace/skills', $params);
    }

    public function get(string $slug): array
    {
        return $this->http->get("/api/v1/marketplace/skills/{$slug}");
    }

    public function categories(): array
    {
        return $this->http->get('/api/v1/marketplace/skills/categories');
    }

    public function featured(): array
    {
        return $this->http->get('/api/v1/marketplace/skills/featured');
    }

    public function versions(string $slug): array
    {
        return $this->http->get("/api/v1/marketplace/skills/{$slug}/versions");
    }

    public function reviews(string $slug): array
    {
        return $this->http->get("/api/v1/marketplace/skills/{$slug}/reviews");
    }

    // =========================================================================
    // PUBLISHING
    // =========================================================================

    public function publish(array $manifest, array $options = []): array
    {
        return $this->http->post('/api/v1/marketplace/skills', array_merge(
            ['manifest' => $manifest],
            $options
        ));
    }

    public function update(string $slug, array $manifest, array $options = []): array
    {
        return $this->http->put("/api/v1/marketplace/skills/{$slug}", array_merge(
            ['manifest' => $manifest],
            $options
        ));
    }

    public function publishVersion(string $slug, array $manifest, ?array $changelog = null): array
    {
        return $this->http->post("/api/v1/marketplace/skills/{$slug}/versions", [
            'manifest' => $manifest,
            'changelog' => $changelog,
        ]);
    }

    public function unpublish(string $slug): array
    {
        return $this->http->delete("/api/v1/marketplace/skills/{$slug}");
    }

    // =========================================================================
    // INSTALLATION
    // =========================================================================

    public function install(string $slug, ?array $config = null): array
    {
        return $this->http->post("/api/v1/marketplace/skills/{$slug}/install", [
            'config' => $config,
        ]);
    }

    public function uninstall(string $slug): array
    {
        return $this->http->post("/api/v1/marketplace/skills/{$slug}/uninstall");
    }

    public function purchase(string $slug): array
    {
        return $this->http->post("/api/v1/marketplace/skills/{$slug}/purchase");
    }

    // =========================================================================
    // USER'S SKILLS
    // =========================================================================

    public function installed(): array
    {
        return $this->http->get('/api/v1/marketplace/skills/my/installed');
    }

    public function published(): array
    {
        return $this->http->get('/api/v1/marketplace/skills/my/published');
    }

    // =========================================================================
    // REVIEWS
    // =========================================================================

    public function review(string $slug, int $rating, ?string $text = null): array
    {
        return $this->http->post("/api/v1/marketplace/skills/{$slug}/reviews", [
            'rating' => $rating,
            'review_text' => $text,
        ]);
    }

    public function respondToReview(string $slug, int $reviewId, string $response): array
    {
        return $this->http->post("/api/v1/marketplace/skills/{$slug}/reviews/{$reviewId}/respond", [
            'response' => $response,
        ]);
    }
}
