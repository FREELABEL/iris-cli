<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Pages;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Pages Resource
 *
 * Manage composable landing pages with JSON-based components.
 * Create custom pages from reusable components like Hero, TextBlock, ButtonCTA.
 *
 * @example
 * ```php
 * // Create a page
 * $page = $iris->pages->create([
 *     'slug' => 'my-landing-page',
 *     'title' => 'Welcome to Our Platform',
 *     'components' => [
 *         [
 *             'type' => 'Hero',
 *             'props' => [
 *                 'title' => 'Build Amazing Products',
 *                 'subtitle' => 'AI-powered platform',
 *                 'backgroundGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
 *                 'titleColor' => '#ffffff',
 *             ],
 *         ],
 *     ],
 * ]);
 *
 * // Publish the page
 * $iris->pages->publish($page['id']);
 * ```
 */
class PagesResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all pages with optional filtering.
     *
     * @param array{
     *     owner_type?: string,
     *     owner_id?: int,
     *     status?: string,
     *     search?: string,
     *     per_page?: int,
     *     page?: int
     * } $params Filter parameters
     * @return array List of pages
     */
    public function list(array $params = []): array
    {
        return $this->http->get('/api/v1/pages', $params);
    }

    /**
     * Get a single page by ID.
     *
     * @param int $id Page ID
     * @param bool $includeJson Include JSON content (default: true)
     * @return array Page data
     */
    public function get(int $id, bool $includeJson = true): array
    {
        return $this->http->get("/api/v1/pages/{$id}", [
            'include_json' => $includeJson ? 1 : 0,
        ]);
    }

    /**
     * Get a page by slug.
     *
     * @param string $slug Page slug
     * @param bool $includeJson Include JSON content (default: true)
     * @param bool $includeDrafts Include draft pages (default: true for SDK/CLI usage)
     * @return array Page data
     */
    public function getBySlug(string $slug, bool $includeJson = true, bool $includeDrafts = true): array
    {
        return $this->http->get("/api/v1/pages/by-slug/{$slug}", [
            'include_json' => $includeJson ? 1 : 0,
            'include_drafts' => $includeDrafts ? 1 : 0,
        ]);
    }

    /**
     * Create a new page.
     *
     * @param array{
     *     slug: string,
     *     title: string,
     *     seo_title?: string,
     *     seo_description?: string,
     *     og_image?: string,
     *     owner_type?: string,
     *     owner_id?: int,
     *     status?: string,
     *     theme?: array,
     *     components?: array
     * } $data Page data
     * @return array Created page
     */
    public function create(array $data): array
    {
        // Set defaults for owner if not provided
        if (!isset($data['owner_type'])) {
            $data['owner_type'] = 'user';
        }
        if (!isset($data['owner_id']) && $this->config->userId) {
            $data['owner_id'] = $this->config->userId;
        }

        // Build JSON content from theme and components
        $jsonContent = [];

        if (isset($data['theme'])) {
            $jsonContent['theme'] = $data['theme'];
            unset($data['theme']);
        }

        if (isset($data['components'])) {
            $jsonContent['components'] = $data['components'];
            unset($data['components']);
        }

        if (!empty($jsonContent)) {
            $data['json_content'] = $jsonContent;
        }

        return $this->http->post('/api/v1/pages', $data);
    }

    /**
     * Update an existing page.
     *
     * @param int $id Page ID
     * @param array $data Updated page data
     * @return array Updated page
     */
    public function update(int $id, array $data): array
    {
        // Build JSON content from theme and components if provided
        $jsonContent = [];
        
        if (isset($data['theme'])) {
            $jsonContent['theme'] = $data['theme'];
            unset($data['theme']);
        }
        
        if (isset($data['components'])) {
            $jsonContent['components'] = $data['components'];
            unset($data['components']);
        }
        
        if (!empty($jsonContent)) {
            $data['json_content'] = $jsonContent;
        }
        
        return $this->http->put("/api/v1/pages/{$id}", $data);
    }

    /**
     * Delete a page (soft delete).
     *
     * @param int $id Page ID
     * @return array Deletion result
     */
    public function delete(int $id): array
    {
        return $this->http->delete("/api/v1/pages/{$id}");
    }

    /**
     * Publish a page.
     *
     * @param int $id Page ID
     * @return array Published page
     */
    public function publish(int $id): array
    {
        return $this->http->post("/api/v1/pages/{$id}/publish");
    }

    /**
     * Unpublish a page (back to draft).
     *
     * @param int $id Page ID
     * @return array Unpublished page
     */
    public function unpublish(int $id): array
    {
        return $this->http->post("/api/v1/pages/{$id}/unpublish");
    }

    /**
     * Archive a page.
     *
     * @param int $id Page ID
     * @return array Archived page
     */
    public function archive(int $id): array
    {
        return $this->http->post("/api/v1/pages/{$id}/archive");
    }

    /**
     * Duplicate a page.
     *
     * @param int $id Page ID to duplicate
     * @param string|null $newSlug New slug for duplicated page
     * @return array Duplicated page
     */
    public function duplicate(int $id, ?string $newSlug = null): array
    {
        $data = [];
        if ($newSlug) {
            $data['slug'] = $newSlug;
        }
        
        return $this->http->post("/api/v1/pages/{$id}/duplicate", $data);
    }

    /**
     * Get version history for a page.
     *
     * @param int $id Page ID
     * @return array Version history
     */
    public function versions(int $id): array
    {
        return $this->http->get("/api/v1/pages/{$id}/versions");
    }

    /**
     * Get a specific version.
     *
     * @param int $id Page ID
     * @param int $versionNumber Version number
     * @return array Version data
     */
    public function getVersion(int $id, int $versionNumber): array
    {
        return $this->http->get("/api/v1/pages/{$id}/versions/{$versionNumber}");
    }

    /**
     * Rollback to a previous version.
     *
     * @param int $id Page ID
     * @param int $versionNumber Version number to rollback to
     * @return array Updated page
     */
    public function rollback(int $id, int $versionNumber): array
    {
        return $this->http->post("/api/v1/pages/{$id}/rollback/{$versionNumber}");
    }

    /**
     * Create a page from a template with predefined components.
     *
     * @param string $template Template name: 'landing', 'product', 'about', 'contact'
     * @param array $data Page data (slug, title, etc.)
     * @return array Created page
     */
    public function createFromTemplate(string $template, array $data): array
    {
        $templates = [
            'landing' => [
                'theme' => [
                    'mode' => 'dark',
                    'branding' => [
                        'name' => $data['title'] ?? 'My Landing Page',
                        'primaryColor' => '#6366f1',
                        'secondaryColor' => '#8b5cf6',
                    ],
                ],
                'components' => [
                    [
                        'type' => 'Hero',
                        'id' => 'hero-main',
                        'props' => [
                            'title' => $data['title'] ?? 'Welcome to Our Platform',
                            'subtitle' => $data['subtitle'] ?? 'Build amazing experiences',
                            'backgroundGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                            'titleColor' => '#ffffff',
                            'subtitleColor' => 'rgba(255, 255, 255, 0.9)',
                            'textAlign' => 'center',
                            'minHeight' => '600px',
                        ],
                    ],
                    [
                        'type' => 'TextBlock',
                        'id' => 'intro',
                        'props' => [
                            'content' => $data['intro'] ?? "## Why Choose Us\n\nWe provide cutting-edge solutions.",
                            'markdown' => true,
                            'textAlign' => 'center',
                            'maxWidth' => '4xl',
                            'themeMode' => 'dark',
                        ],
                    ],
                ],
            ],
            'product' => [
                'theme' => [
                    'mode' => 'light',
                    'branding' => [
                        'name' => $data['title'] ?? 'Product Page',
                        'primaryColor' => '#10b981',
                        'secondaryColor' => '#3b82f6',
                    ],
                ],
                'components' => [
                    [
                        'type' => 'Hero',
                        'id' => 'hero-product',
                        'props' => [
                            'title' => $data['title'] ?? 'Our Product',
                            'subtitle' => $data['subtitle'] ?? 'Powerful features for your needs',
                            'backgroundGradient' => 'linear-gradient(135deg, #10b981 0%, #3b82f6 100%)',
                            'titleColor' => '#ffffff',
                            'subtitleColor' => 'rgba(255, 255, 255, 0.85)',
                            'textAlign' => 'center',
                            'minHeight' => '400px',
                        ],
                    ],
                ],
            ],
        ];

        if (!isset($templates[$template])) {
            throw new \InvalidArgumentException("Template '{$template}' not found. Available: " . implode(', ', array_keys($templates)));
        }

        $templateData = $templates[$template];
        $data['theme'] = $templateData['theme'];
        $data['components'] = $templateData['components'];

        return $this->create($data);
    }

    /**
     * Add a component to an existing page.
     *
     * @param int $id Page ID
     * @param array $component Component data (type, id, props)
     * @param int|null $position Position to insert (null = append to end)
     * @return array Updated page
     */
    public function addComponent(int $id, array $component, ?int $position = null): array
    {
        $page = $this->get($id, true);
        $jsonContent = $page['json_content'] ?? [];
        
        if (!isset($jsonContent['components'])) {
            $jsonContent['components'] = [];
        }
        
        // Ensure component has an ID
        if (!isset($component['id'])) {
            $component['id'] = ($component['type'] ?? 'component') . '-' . uniqid();
        }
        
        if ($position === null) {
            $jsonContent['components'][] = $component;
        } else {
            array_splice($jsonContent['components'], $position, 0, [$component]);
        }
        
        return $this->http->put("/api/v1/pages/{$id}", [
            'json_content' => $jsonContent,
        ]);
    }

    /**
     * Update a component by its ID.
     *
     * @param int $id Page ID
     * @param string $componentId Component ID to update
     * @param array $updates Partial updates to merge (e.g., ['props.title' => 'New Title'])
     * @return array Updated page
     */
    public function updateComponentById(int $id, string $componentId, array $updates): array
    {
        $page = $this->get($id, true);
        $jsonContent = $page['json_content'] ?? [];
        
        if (!isset($jsonContent['components'])) {
            throw new \RuntimeException("Page has no components");
        }
        
        $found = false;
        foreach ($jsonContent['components'] as &$component) {
            if (($component['id'] ?? null) === $componentId) {
                $component = $this->mergeUpdates($component, $updates);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            throw new \RuntimeException("Component with ID '{$componentId}' not found");
        }
        
        return $this->http->put("/api/v1/pages/{$id}", [
            'json_content' => $jsonContent,
        ]);
    }

    /**
     * Update a component by its index position.
     *
     * @param int $id Page ID
     * @param int $index Component index (0-based)
     * @param array $updates Partial updates to merge
     * @return array Updated page
     */
    public function updateComponentByIndex(int $id, int $index, array $updates): array
    {
        $page = $this->get($id, true);
        $jsonContent = $page['json_content'] ?? [];
        
        if (!isset($jsonContent['components'][$index])) {
            throw new \RuntimeException("Component at index {$index} not found");
        }
        
        $jsonContent['components'][$index] = $this->mergeUpdates(
            $jsonContent['components'][$index],
            $updates
        );
        
        return $this->http->put("/api/v1/pages/{$id}", [
            'json_content' => $jsonContent,
        ]);
    }

    /**
     * Remove a component by its ID.
     *
     * @param int $id Page ID
     * @param string $componentId Component ID to remove
     * @return array Updated page
     */
    public function removeComponentById(int $id, string $componentId): array
    {
        $page = $this->get($id, true);
        $jsonContent = $page['json_content'] ?? [];
        
        if (!isset($jsonContent['components'])) {
            throw new \RuntimeException("Page has no components");
        }
        
        $filtered = array_values(array_filter(
            $jsonContent['components'],
            fn($c) => ($c['id'] ?? null) !== $componentId
        ));
        
        if (count($filtered) === count($jsonContent['components'])) {
            throw new \RuntimeException("Component with ID '{$componentId}' not found");
        }
        
        $jsonContent['components'] = $filtered;
        
        return $this->http->put("/api/v1/pages/{$id}", [
            'json_content' => $jsonContent,
        ]);
    }

    /**
     * Remove a component by its index position.
     *
     * @param int $id Page ID
     * @param int $index Component index (0-based)
     * @return array Updated page
     */
    public function removeComponentByIndex(int $id, int $index): array
    {
        $page = $this->get($id, true);
        $jsonContent = $page['json_content'] ?? [];
        
        if (!isset($jsonContent['components'][$index])) {
            throw new \RuntimeException("Component at index {$index} not found");
        }
        
        array_splice($jsonContent['components'], $index, 1);
        
        return $this->http->put("/api/v1/pages/{$id}", [
            'json_content' => $jsonContent,
        ]);
    }

    /**
     * Update theme settings.
     *
     * @param int $id Page ID
     * @param array $themeUpdates Theme updates (e.g., ['mode' => 'light', 'branding.primaryColor' => '#10b981'])
     * @return array Updated page
     */
    public function updateTheme(int $id, array $themeUpdates): array
    {
        $page = $this->get($id, true);
        $jsonContent = $page['json_content'] ?? [];
        
        if (!isset($jsonContent['theme'])) {
            $jsonContent['theme'] = [];
        }
        
        $jsonContent['theme'] = $this->mergeUpdates($jsonContent['theme'], $themeUpdates);
        
        return $this->http->put("/api/v1/pages/{$id}", [
            'json_content' => $jsonContent,
        ]);
    }

    /**
     * Update a specific path in the JSON content using dot notation.
     *
     * @param int $id Page ID
     * @param string $path Dot notation path (e.g., 'components.0.props.title', 'theme.branding.primaryColor')
     * @param mixed $value New value
     * @return array Updated page
     */
    public function updatePath(int $id, string $path, $value): array
    {
        $page = $this->get($id, true);
        $jsonContent = $page['json_content'] ?? [];
        
        $this->setNestedValue($jsonContent, $path, $value);
        
        return $this->http->put("/api/v1/pages/{$id}", [
            'json_content' => $jsonContent,
        ]);
    }

    /**
     * Get all components from a page.
     *
     * @param int $id Page ID
     * @return array List of components
     */
    public function getComponents(int $id): array
    {
        $page = $this->get($id, true);
        return $page['json_content']['components'] ?? [];
    }

    /**
     * Find component by ID.
     *
     * @param int $id Page ID
     * @param string $componentId Component ID
     * @return array|null Component data or null if not found
     */
    public function findComponentById(int $id, string $componentId): ?array
    {
        $components = $this->getComponents($id);
        
        foreach ($components as $component) {
            if (($component['id'] ?? null) === $componentId) {
                return $component;
            }
        }
        
        return null;
    }

    /**
     * Merge updates into a nested array using dot notation.
     *
     * Supports:
     * - Dot notation keys: ['props.title' => 'New'] sets nested value
     * - Nested arrays: ['props' => ['title' => 'New']] deep merges into existing props
     * - Simple values: ['type' => 'Hero'] replaces the value
     *
     * @param array $target Target array
     * @param array $updates Updates with dot notation keys or nested arrays
     * @return array Merged array
     */
    private function mergeUpdates(array $target, array $updates): array
    {
        foreach ($updates as $key => $value) {
            if (strpos($key, '.') !== false) {
                // Dot notation: set nested value directly
                $this->setNestedValue($target, $key, $value);
            } elseif (is_array($value) && isset($target[$key]) && is_array($target[$key])) {
                // Both are arrays: deep merge (preserves existing keys)
                $target[$key] = array_merge($target[$key], $value);
            } else {
                // Simple value or target doesn't have the key: replace
                $target[$key] = $value;
            }
        }

        return $target;
    }

    /**
     * Set a nested value using dot notation.
     *
     * @param array &$array Array to modify (by reference)
     * @param string $path Dot notation path
     * @param mixed $value Value to set
     */
    private function setNestedValue(array &$array, string $path, $value): void
    {
        $keys = explode('.', $path);
        $current = &$array;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }
}
