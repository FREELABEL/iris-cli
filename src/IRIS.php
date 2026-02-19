<?php

declare(strict_types=1);

namespace IRIS\SDK;

use IRIS\SDK\Http\Client;
use IRIS\SDK\Auth\AuthManager;
use IRIS\SDK\Resources\Agents\AgentsResource;
use IRIS\SDK\Resources\Workflows\WorkflowsResource;
use IRIS\SDK\Resources\Bloqs\BloqsResource;
use IRIS\SDK\Resources\Leads\LeadsResource;
use IRIS\SDK\Resources\Integrations\IntegrationsResource;
use IRIS\SDK\Resources\RAG\RAGResource;
use IRIS\SDK\Resources\CloudFiles\CloudFilesResource;
use IRIS\SDK\Resources\Usage\UsageResource;
use IRIS\SDK\Resources\Vapi\VapiResource;
use IRIS\SDK\Resources\Models\ModelsResource;
use IRIS\SDK\Resources\Chat\ChatResource;
use IRIS\SDK\Resources\Profiles\ProfilesResource;
use IRIS\SDK\Resources\Services\ServicesResource;
use IRIS\SDK\Resources\Tools\ToolsResource;
use IRIS\SDK\Resources\Articles\ArticlesResource;
use IRIS\SDK\Resources\Schedules\SchedulesResource;
use IRIS\SDK\Resources\ServisAi\ServisAiResource;
use IRIS\SDK\Resources\Programs\ProgramsResource;
use IRIS\SDK\Resources\Courses\CoursesResource;
use IRIS\SDK\Resources\Audio\AudioResource;
use IRIS\SDK\Resources\Social\SocialMediaResource;
use IRIS\SDK\Resources\Voice\VoiceResource;
use IRIS\SDK\Resources\Videos\VideosResource;
use IRIS\SDK\Resources\Phone\PhoneResource;
use IRIS\SDK\Resources\Automations\AutomationsResource;
use IRIS\SDK\Resources\Users\UsersResource;
use IRIS\SDK\Resources\Pages\PagesResource;
use IRIS\SDK\Resources\Payments\PaymentsResource;
use IRIS\SDK\Resources\Marketplace\MarketplaceResource;
use IRIS\SDK\Events\WebhookHandler;

/**
 * IRIS SDK Client
 *
 * The main entry point for interacting with the IRIS AI platform.
 *
 * Simple authentication with just your API token:
 * - User Token: Works for ALL operations (chat, leads, agents, workflows, etc.)
 * - Client Credentials: OPTIONAL - rarely needed
 *
 * @example Basic usage (works for everything!):
 * ```php
 * $iris = new IRIS([
 *     'api_key' => 'your-api-token',
 *     'user_id' => 193,
 * ]);
 *
 * // Chat with agents
 * $response = $iris->chat->execute([
 *     'query' => 'Hello!',
 *     'agentId' => 11,
 * ]);
 *
 * // Create agents (yes, with just a token!)
 * $agent = $iris->agents->create(new AgentConfig(
 *     name: 'My Agent',
 *     prompt: 'You are helpful',
 * ));
 *
 * // Search leads
 * $leads = $iris->leads->search(['status' => 'Won']);
 * ```
 *
 * @example Advanced usage (optional client credentials):
 * ```php
 * // Only needed for specific machine-to-machine scenarios
 * $iris = new IRIS([
 *     'api_key' => 'your-api-token',
 *     'user_id' => 193,
 *     'client_id' => 'optional-client-id',      // Rarely needed
 *     'client_secret' => 'optional-secret',     // Rarely needed
 * ]);
 * ```
 */
class IRIS
{
    /**
     * SDK Version
     */
    public const VERSION = '1.0.0';

    /**
     * Configuration instance
     */
    protected Config $config;

    /**
     * HTTP client instance
     */
    protected Client $http;

    /**
     * Agents resource for managing AI agents
     */
    public AgentsResource $agents;

    /**
     * Workflows resource for V5 workflow execution
     */
    public WorkflowsResource $workflows;

    /**
     * Bloqs resource for document management
     */
    public BloqsResource $bloqs;

    /**
     * Leads resource for CRM functionality
     */
    public LeadsResource $leads;

    /**
     * Integrations resource for third-party services
     */
    public IntegrationsResource $integrations;

    /**
     * RAG resource for knowledge base operations
     */
    public RAGResource $rag;

    /**
     * Cloud Files resource for file management
     */
    public CloudFilesResource $cloudFiles;

    /**
     * Usage resource for tracking API usage and billing
     */
    public UsageResource $usage;

    /**
     * VAPI resource for Voice AI phone numbers and assistants
     */
    public VapiResource $vapi;

    /**
     * Models resource for listing available AI models
     */
    public ModelsResource $models;

    /**
     * Chat resource for real-time agent conversations
     */
    public ChatResource $chat;

    /**
     * Profiles resource for managing user profiles and media
     */
    public ProfilesResource $profiles;

    /**
     * Services resource for managing service offerings
     */
    public ServicesResource $services;

    /**
     * Tools resource for invoking Neuron AI tools (recruitment, enrichment, etc.)
     */
    public ToolsResource $tools;

    /**
     * Articles resource for generating articles from videos, topics, etc.
     */
    public ArticlesResource $articles;

    /**
     * Schedules resource for managing agent scheduled tasks.
     */
    public SchedulesResource $schedules;

    /**
     * Servis.ai resource for healthcare/service business workflows.
     */
    public ServisAiResource $servisAi;

    /**
     * Programs resource for managing membership programs and funnels.
     */
    public ProgramsResource $programs;

    /**
     * Courses resource for managing courses, enrollments, and content.
     */
    public CoursesResource $courses;

    /**
     * Audio resource for FFMPEG audio processing (merge, crossfade, metadata).
     */
    public AudioResource $audio;

    /**
     * Social Media resource for publishing to Instagram, TikTok, X, etc.
     */
    public SocialMediaResource $social;

    /**
     * Voice resource for managing agent voice settings across providers.
     */
    public VoiceResource $voice;

    /**
     * Videos resource for managing video content and uploads.
     */
    public VideosResource $videos;

    /**
     * Phone resource for managing agent phone numbers across providers.
     */
    public PhoneResource $phone;

    /**
     * Automations resource for V6 goal-driven automations.
     */
    public AutomationsResource $automations;

    /**
     * Users resource for managing user accounts in FL-API.
     */
    public UsersResource $users;

    /**
     * Pages resource for managing composable landing pages.
     */
    public PagesResource $pages;

    /**
     * Payments resource for agent wallets and A2P protocol operations.
     */
    public PaymentsResource $payments;

    /**
     * Marketplace resource for browsing, publishing, and installing skills.
     */
    public MarketplaceResource $marketplace;

    /**
     * Create a new IRIS client instance.
     *
     * @param array{
     *     api_key: string,
     *     base_url?: string,
     *     iris_url?: string,
     *     user_id?: int,
     *     timeout?: int,
     *     retries?: int,
     *     webhook_secret?: string,
     *     client_id?: string,
     *     client_secret?: string,
     *     debug?: bool
     * } $options Configuration options
     * @param Client|null $httpClient Optional HTTP client (for testing)
     *
     * @throws \InvalidArgumentException If api_key is not provided
     */
    public function __construct(array $options, ?Client $httpClient = null)
    {
        $this->config = new Config($options);
        $this->http = $httpClient ?? new Client($this->config);

        // Initialize resource modules
        $this->agents = new AgentsResource($this->http, $this->config);
        $this->workflows = new WorkflowsResource($this->http, $this->config);
        $this->bloqs = new BloqsResource($this->http, $this->config);
        $this->leads = new LeadsResource($this->http, $this->config);
        $this->integrations = new IntegrationsResource($this->http, $this->config);
        $this->rag = new RAGResource($this->http, $this->config);
        $this->cloudFiles = new CloudFilesResource($this->http, $this->config);
        $this->usage = new UsageResource($this->http, $this->config);
        $this->vapi = new VapiResource($this->http, $this->config);
        $this->models = new ModelsResource($this->http, $this->config);
        $this->chat = new ChatResource($this->http, $this->config);
        $this->profiles = new ProfilesResource($this->http, $this->config);
        $this->services = new ServicesResource($this->http, $this->config);
        $this->tools = new ToolsResource($this->http, $this->config);
        $this->articles = new ArticlesResource($this->http, $this->config);
        $this->schedules = new SchedulesResource($this->http, $this->config);
        $this->servisAi = new ServisAiResource($this->http, $this->config);
        $this->programs = new ProgramsResource($this->http, $this->config);
        $this->courses = new CoursesResource($this->http, $this->config);
        $this->audio = new AudioResource($this->http, $this->config);
        $this->social = new SocialMediaResource($this->http, $this->config);
        $this->voice = new VoiceResource($this->http, $this->config);
        $this->videos = new VideosResource($this->http, $this->config);
        $this->phone = new PhoneResource($this->http, $this->config);
        $this->automations = new AutomationsResource($this->http, $this->config);
        $this->users = new UsersResource($this->http, $this->config);
        $this->pages = new PagesResource($this->http, $this->config);
        $this->payments = new PaymentsResource($this->http, $this->config);
        $this->marketplace = new MarketplaceResource($this->http, $this->config);
    }

    /**
     * Get the authentication manager.
     *
     * Use this to configure client credentials for management routes:
     * ```php
     * $iris->auth()->setClientCredentials($clientId, $clientSecret);
     * ```
     */
    public function auth(): AuthManager
    {
        return $this->http->auth();
    }

    /**
     * Get the configuration instance.
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Get the HTTP client instance.
     */
    public function getHttpClient(): Client
    {
        return $this->http;
    }

    /**
     * Set the user context for API calls.
     *
     * @param int $userId The user ID to use for subsequent API calls
     * @return $this
     */
    public function asUser(int $userId): self
    {
        $this->config->userId = $userId;
        return $this;
    }

    /**
     * Create a webhook handler for processing incoming webhook events.
     *
     * @return WebhookHandler
     */
    public function webhooks(): WebhookHandler
    {
        return new WebhookHandler($this->config->webhookSecret);
    }

    /**
     * Test the API connection.
     *
     * @return bool True if connection is successful
     * @throws \IRIS\SDK\Exceptions\IRISException
     */
    public function testConnection(): bool
    {
        $response = $this->http->get('/v1/health');
        return $response['status'] === 'ok';
    }

    /**
     * Get account information for the authenticated user.
     *
     * @return array Account details
     */
    public function account(): array
    {
        return $this->http->get('/v1/user');
    }

    /**
     * Get usage statistics for the current billing period.
     *
     * @return array Usage statistics
     */
    public function usage(): array
    {
        return $this->http->get('/v1/billing/usage');
    }
}
