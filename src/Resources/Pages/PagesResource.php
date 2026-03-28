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

        $result = $this->http->post('/api/v1/pages', $data);

        // Enrich response with public URL
        $slug = $result['data']['slug'] ?? $data['slug'] ?? '';
        if ($slug) {
            $result['data']['public_url'] = $this->getPublicUrl($slug);
        }

        return $result;
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

        // Event template is built dynamically based on provided data
        if ($template === 'event') {
            return $this->createEventPage($data);
        }

        if (!isset($templates[$template])) {
            throw new \InvalidArgumentException("Template '{$template}' not found. Available: " . implode(', ', array_keys($templates)) . ', event');
        }

        $templateData = $templates[$template];
        $data['theme'] = $templateData['theme'];
        $data['components'] = $templateData['components'];

        return $this->create($data);
    }

    /**
     * Create an event page with conditional components.
     *
     * Mirrors PageTemplateService::buildEventTemplate() on iris-api.
     * Components are conditionally included based on provided data.
     *
     * @param array $data Event data: title, date, description, venue, schedule[], speakers[], tickets[], etc.
     * @return array Created page
     */
    protected function createEventPage(array $data): array
    {
        $themeMode = $data['theme_mode'] ?? 'dark';
        $eventName = $data['title'] ?? 'Event';
        $eventDate = $data['date'] ?? $data['subtitle'] ?? 'Coming Soon';

        $components = [];

        // SiteNavigation — always
        $components[] = [
            'type' => 'SiteNavigation',
            'id' => 'nav-1',
            'props' => [
                'title' => $eventName,
                'themeMode' => $themeMode,
            ],
        ];

        // Hero — always
        $components[] = [
            'type' => 'Hero',
            'id' => 'hero-main',
            'props' => [
                'title' => $eventName,
                'subtitle' => $eventDate,
                'backgroundGradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                'titleColor' => '#ffffff',
                'subtitleColor' => 'rgba(255, 255, 255, 0.9)',
                'textAlign' => 'center',
                'minHeight' => '500px',
                'themeMode' => $themeMode,
            ],
        ];

        // Description TextBlock — if provided
        if (!empty($data['description'])) {
            $components[] = [
                'type' => 'TextBlock',
                'id' => 'details-1',
                'props' => [
                    'content' => $data['description'],
                    'markdown' => true,
                    'maxWidth' => '3xl',
                    'themeMode' => $themeMode,
                ],
            ];
        }

        // Schedule DataTable — if provided
        if (!empty($data['schedule']) && is_array($data['schedule'])) {
            $components[] = [
                'type' => 'DataTable',
                'id' => 'schedule-1',
                'props' => [
                    'title' => 'Schedule',
                    'themeMode' => $themeMode,
                    'columns' => [
                        ['key' => 'time', 'label' => 'Time', 'type' => 'text'],
                        ['key' => 'session', 'label' => 'Session', 'type' => 'text'],
                        ['key' => 'speaker', 'label' => 'Speaker', 'type' => 'text'],
                        ['key' => 'location', 'label' => 'Location', 'type' => 'text'],
                    ],
                    'data' => array_map(fn($item) => [
                        'time' => $item['time'] ?? '',
                        'session' => $item['session'] ?? $item['title'] ?? '',
                        'speaker' => $item['speaker'] ?? '',
                        'location' => $item['location'] ?? '',
                    ], $data['schedule']),
                ],
            ];
        }

        // Speakers PortfolioGrid — if provided
        if (!empty($data['speakers']) && is_array($data['speakers'])) {
            $components[] = [
                'type' => 'PortfolioGrid',
                'id' => 'speakers-1',
                'props' => [
                    'title' => 'Speakers',
                    'themeMode' => $themeMode,
                    'columns' => min(count($data['speakers']), 3),
                    'items' => array_map(fn($s) => [
                        'title' => $s['name'] ?? $s['title'] ?? 'Speaker',
                        'description' => $s['role'] ?? $s['description'] ?? $s['bio'] ?? '',
                        'imageUrl' => $s['image_url'] ?? $s['imageUrl'] ?? $s['photo'] ?? '',
                    ], $data['speakers']),
                ],
            ];
        }

        // Tickets PricingPlans — if provided
        if (!empty($data['tickets']) && is_array($data['tickets'])) {
            $components[] = [
                'type' => 'PricingPlans',
                'id' => 'tickets-1',
                'props' => [
                    'title' => 'Tickets',
                    'subtitle' => 'Choose your experience',
                    'themeMode' => $themeMode,
                    'packages' => array_map(fn($t) => [
                        'id' => 'ticket-' . bin2hex(random_bytes(4)),
                        'name' => $t['name'] ?? 'General Admission',
                        'description' => $t['description'] ?? '',
                        'price' => $t['price'] ?? 0,
                        'billingType' => 'one_time',
                        'features' => $t['features'] ?? [],
                        'highlighted' => $t['highlighted'] ?? false,
                    ], $data['tickets']),
                ],
            ];
        }

        // Venue TextBlock — if provided
        if (!empty($data['venue'])) {
            $venueText = $data['venue'];
            if (!empty($data['venue_map_url'])) {
                $venueText .= "\n\n[View on Map]({$data['venue_map_url']})";
            }
            $components[] = [
                'type' => 'TextBlock',
                'id' => 'venue-1',
                'props' => [
                    'title' => 'Venue',
                    'content' => $venueText,
                    'markdown' => true,
                    'maxWidth' => '3xl',
                    'themeMode' => $themeMode,
                ],
            ];
        }

        // EnrollmentForm — unless explicitly disabled
        $includeRegistration = $data['include_registration'] ?? true;
        if ($includeRegistration) {
            $fields = [
                ['name' => 'name', 'label' => 'Full Name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ];
            if (!empty($data['tickets'])) {
                $fields[] = [
                    'name' => 'ticket_type',
                    'label' => 'Ticket Type',
                    'type' => 'select',
                    'required' => true,
                    'options' => array_map(fn($t) => $t['name'] ?? 'General', $data['tickets']),
                ];
            }
            $components[] = [
                'type' => 'EnrollmentForm',
                'id' => 'rsvp-1',
                'props' => [
                    'title' => 'Register',
                    'subtitle' => 'Secure your spot',
                    'themeMode' => $themeMode,
                    'fields' => $fields,
                    'submitLabel' => 'Register Now',
                    'successMessage' => 'You\'re registered! See you there.',
                ],
            ];
        }

        // SiteFooter — always
        $components[] = [
            'type' => 'SiteFooter',
            'id' => 'footer-1',
            'props' => [
                'themeMode' => $themeMode,
            ],
        ];

        // Build page data
        $pageData = [
            'slug' => $data['slug'] ?? '',
            'title' => $eventName,
            'seo_title' => $data['seo_title'] ?? $eventName,
            'seo_description' => $data['seo_description'] ?? "Join us for {$eventName} on {$eventDate}",
            'auto_publish' => true,
            'theme' => [
                'mode' => $themeMode,
                'backgroundColor' => $themeMode === 'dark' ? '#0a0a0a' : '#ffffff',
                'primaryColor' => '#f5576c',
                'secondaryColor' => '#f093fb',
            ],
            'components' => $components,
        ];

        // Store event metadata in json_content for cross-compatibility with Genesis
        $jsonContent = [
            'version' => '1.0',
            'type' => 'event',
            'theme' => $pageData['theme'],
            'components' => $components,
            'meta' => [
                'created_by' => 'cli',
                'template' => 'event',
                'event_type' => $data['event_type'] ?? 'conference',
                'event_date' => $eventDate,
                'created_at' => date('c'),
            ],
        ];

        unset($pageData['theme'], $pageData['components']);

        if (!isset($pageData['owner_type'])) {
            $pageData['owner_type'] = 'user';
        }
        if (!isset($pageData['owner_id']) && $this->config->userId) {
            $pageData['owner_id'] = $this->config->userId;
        }

        $pageData['json_content'] = $jsonContent;

        $result = $this->http->post('/api/v1/pages', $pageData);

        // Enrich response with public URL
        $slug = $result['data']['slug'] ?? $data['slug'] ?? '';
        if ($slug) {
            $result['data']['public_url'] = $this->getPublicUrl($slug);
        }

        return $result;
    }

    /**
     * Get the public URL for a page by slug.
     */
    public function getPublicUrl(string $slug): string
    {
        $env = getenv('IRIS_ENV') ?: 'production';
        return $env === 'local'
            ? "http://local.iris.freelabel.net:9300/p/{$slug}"
            : "https://heyiris.io/p/{$slug}";
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

    // ========================================================================
    // Genesis Integration Functions (via execute-direct)
    // ========================================================================

    /**
     * Execute a Genesis integration function via the execute-direct endpoint.
     */
    private function executeGenesis(string $function, array $params = []): array
    {
        $userId = $this->config->userId;
        return $this->http->post(
            "/api/v1/users/{$userId}/integrations/execute-direct?user_id={$userId}",
            ['integration' => 'genesis', 'action' => $function, 'params' => $params]
        );
    }

    /**
     * AI-edit a page using natural language instructions.
     *
     * @param int $pageId Page ID to edit
     * @param string $instructions Natural language editing instructions
     * @return array Changes applied
     */
    public function aiEdit(int $pageId, string $instructions): array
    {
        return $this->executeGenesis('ai_edit_page', [
            'page_id' => $pageId,
            'instructions' => $instructions,
        ]);
    }

    /**
     * Get analytics for a single page.
     *
     * @param int $pageId Page ID
     * @return array Analytics data (views, engagement, etc.)
     */
    public function analytics(int $pageId): array
    {
        return $this->executeGenesis('get_page_analytics', [
            'page_id' => $pageId,
        ]);
    }

    /**
     * Get analytics for multiple pages at once.
     *
     * @param array $pageIds Array of page IDs
     * @return array Batch analytics data
     */
    public function analyticsBatch(array $pageIds): array
    {
        return $this->executeGenesis('get_page_analytics', [
            'page_ids' => implode(',', $pageIds),
        ]);
    }

    /**
     * List or export form submissions for a page.
     *
     * @param int $pageId Page ID
     * @param string $action 'list' or 'export'
     * @param string $format Export format: 'json' or 'csv'
     * @return array Submissions data
     */
    public function submissions(int $pageId, string $action = 'list', string $format = 'json'): array
    {
        return $this->executeGenesis('manage_submissions', [
            'page_id' => $pageId,
            'action' => $action,
            'format' => $format,
        ]);
    }

    /**
     * Create a Stripe checkout link for a page's pricing package.
     *
     * @param int $pageId Page ID with PricingPlans component
     * @param string $packageId Package ID from the PricingPlans component
     * @param string $buyerEmail Buyer's email address
     * @return array Contains checkout_url
     */
    public function createCheckoutLink(int $pageId, string $packageId, string $buyerEmail): array
    {
        return $this->executeGenesis('create_checkout_link', [
            'page_id' => $pageId,
            'package_id' => $packageId,
            'buyer_email' => $buyerEmail,
        ]);
    }

    /**
     * Add pricing packages to a page (monetization setup).
     *
     * @param int $pageId Page ID to monetize
     * @param array $packages Array of packages: [{name, price, billingType, features}, ...]
     * @param array $options Optional: section_title, section_subtitle
     * @return array Result with page_url, packages_added count
     */
    public function setupMonetization(int $pageId, array $packages, array $options = []): array
    {
        return $this->executeGenesis('setup_page_monetization', array_merge([
            'page_id' => $pageId,
            'packages' => $packages,
        ], $options));
    }

    /**
     * AI-compose a full page from a video URL, website URL, topic, or raw content.
     *
     * @param string $sourceType Source type: 'video', 'url', 'topic', 'content'
     * @param string $source The URL or text content
     * @param array $options Optional: title, style (landing/article/product/portfolio), theme_mode, include_form
     * @return array Created page data with page_url, page_id
     */
    public function composePage(string $sourceType, string $source, array $options = []): array
    {
        return $this->executeGenesis('compose_page', array_merge([
            'source_type' => $sourceType,
            'source' => $source,
        ], $options));
    }

    /**
     * Generate or regenerate a preview thumbnail for a page.
     *
     * @param int $pageId Page ID
     * @return array Contains thumbnail_url
     */
    public function generateThumbnail(int $pageId): array
    {
        return $this->executeGenesis('generate_thumbnail', [
            'page_id' => $pageId,
        ]);
    }

    /**
     * Manage custom domain mappings for pages.
     *
     * @param string $action Action: 'list', 'map', 'verify', 'remove'
     * @param array $params Additional params: domain, page_id, site_id, mapping_id
     * @return array Domain mapping data
     */
    public function domains(string $action = 'list', array $params = []): array
    {
        return $this->executeGenesis('manage_domains', array_merge([
            'action' => $action,
        ], $params));
    }

    // ========================================================================
    // Private Helpers
    // ========================================================================

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
        // Normalize bracket notation: components[0] → components.0
        $path = preg_replace('/\[(\d+)\]/', '.$1', $path);
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
