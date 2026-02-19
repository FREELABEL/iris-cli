<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;
use IRIS\SDK\Resources\Workflows\WorkflowRun;
use IRIS\SDK\Resources\Agents\AgentTemplates;

/**
 * Agents Resource
 *
 * Manage AI agents - create, configure, and chat with intelligent assistants.
 *
 * @example
 * ```php
 * // Create an agent
 * $agent = $fl->agents->create(new AgentConfig(
 *     name: 'Marketing Assistant',
 *     prompt: 'You are a helpful marketing assistant.',
 *     model: 'gpt-4o-mini',
 * ));
 *
 * // Chat with an agent
 * $response = $fl->agents->chat($agent->id, [
 *     ['role' => 'user', 'content' => 'Draft a tweet about our new product']
 * ]);
 * ```
 */
class AgentsResource
{
    protected Client $http;
    protected Config $config;

    public function __construct(Client $http, Config $config)
    {
        $this->http = $http;
        $this->config = $config;
    }

    /**
     * List all agents for the current user.
     *
     * @param array{
     *     page?: int,
     *     per_page?: int,
     *     search?: string,
     *     type?: string
     * } $options List options
     * @return AgentCollection
     */
    public function list(array $options = []): AgentCollection
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/agents", $options);

        return new AgentCollection(
            array_map(fn($data) => new Agent($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Search for agents by name or description.
     *
     * @param string $search Search query
     * @param array $options Additional options
     * @return AgentCollection
     * 
     * @example
     * ```php
     * $agents = $iris->agents->search('Polly');
     * ```
     */
    public function search(string $search, array $options = []): AgentCollection
    {
        return $this->list(array_merge(['search' => $search], $options));
    }

    /**
     * Get a specific agent by ID.
     *
     * @param int|string $agentId Agent ID
     * @return Agent
     */
    public function get(int|string $agentId): Agent
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/agents/{$agentId}");

        return new Agent($response);
    }

    /**
     * Create a new agent.
     *
     * @param AgentConfig $config Agent configuration
     * @return Agent
     */
    public function create(AgentConfig $config): Agent
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post(
            "/api/v1/users/{$userId}/bloqs/agents",
            $config->toArray()
        );

        return new Agent($response);
    }

    /**
     * Create an agent from a template.
     * 
     * Use pre-built templates for common agent types. Templates include
     * prompts, schedules, integrations, and settings optimized for specific use cases.
     *
     * @param string $template Template name ('elderly-care', 'customer-support', 'sales-assistant', 'research-agent')
     * @param array $customizations Override any template values
     * @return Agent
     * 
     * @example Create elderly care agent with custom name
     * ```php
     * $agent = $iris->agents->createFromTemplate('elderly-care', [
     *     'name' => 'Grandma Helper',
     *     'settings' => [
     *         'schedule' => [
     *             'timezone' => 'America/Chicago',
     *             'recurring_tasks' => [
     *                 ['name' => 'Morning Meds', 'time' => '07:30'],
     *                 ['name' => 'Evening Meds', 'time' => '19:00']
     *             ]
     *         ]
     *     ]
     * ]);
     * ```
     */
    public function createFromTemplate(string $template, array $customizations = []): Agent
    {
        // Get template configuration
        $config = AgentTemplates::get($template);
        
        // Deep merge customizations
        $config = $this->deepMerge($config, $customizations);
        
        // Create agent with merged config
        return $this->createFromConfig($config);
    }

    /**
     * List all available templates.
     *
     * @return array Template names and descriptions
     */
    public function listTemplates(): array
    {
        $templates = AgentTemplates::all();
        $list = [];
        
        foreach ($templates as $name => $config) {
            $list[$name] = [
                'name' => $config['name'],
                'description' => $config['description'] ?? '',
                'icon' => $config['icon'] ?? 'fas fa-robot'
            ];
        }
        
        return $list;
    }

    /**
     * Deep merge arrays recursively.
     *
     * @param array $base Base array
     * @param array $override Override array
     * @return array Merged array
     */
    protected function deepMerge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        
        return $base;
    }



    /**
     * Create an agent from array (simplified API).
     * 
     * This method accepts a simple array and handles AgentConfig creation internally.
     * Perfect for CLI usage and quick agent creation.
     *
     * @param array $data Agent data
     * @return Agent
     * 
     * @example Create from simple array
     * ```php
     * $agent = $iris->agents->createFromArray([
     *     'name' => 'News Scout',
     *     'initial_prompt' => 'You are a helpful assistant',
     *     'bloq_id' => 40,
     *     'config' => ['model_id' => 185, 'temperature' => 0.7]
     * ]);
     * ```
     */
    public function createFromArray(array $data): Agent
    {
        $userId = $this->config->requireUserId();
        
        // Add type if not provided
        if (!isset($data['type'])) {
            $data['type'] = 'ai_bloq';
        }
        
        $response = $this->http->post(
            "/api/v1/users/{$userId}/bloqs/agents",
            $data
        );

        return new Agent($response);
    }

    /**
     * Update an existing agent (full replacement).
     *
     * @param int|string $agentId Agent ID
     * @param array $data Update data
     * @return Agent
     */
    public function update(int|string $agentId, array $data): Agent
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->put(
            "/api/v1/users/{$userId}/bloqs/agents/{$agentId}",
            $data
        );

        return new Agent($response);
    }

    /**
     * Partially update an agent (only specified fields).
     * 
     * This method fetches the current agent, merges your changes,
     * and updates. Perfect for updating just one field without
     * overwriting everything else.
     *
     * @param int|string $agentId Agent ID
     * @param array $data Fields to update (e.g., ['initial_prompt' => '...'])
     * @return Agent
     * 
     * @example Update just the prompt
     * ```php
     * $agent = $iris->agents->patch(356, [
     *     'initial_prompt' => 'New instructions...'
     * ]);
     * ```
     */
    public function patch(int|string $agentId, array $data): Agent
    {
        // Get current agent data
        $current = $this->get($agentId);
        
        // Merge with new data (new data overwrites current)
        $merged = array_merge($current->toArray(), $data);
        
        // Update with merged data
        return $this->update($agentId, $merged);
    }

    /**
     * Delete an agent.
     *
     * @param int|string $agentId Agent ID
     * @return bool
     */
    public function delete(int|string $agentId): bool
    {
        $userId = $this->config->requireUserId();
        $this->http->delete("/api/v1/users/{$userId}/bloqs/agents/{$agentId}");
        return true;
    }

    /**
     * Chat with an agent (single turn).
     *
     * @param int|string $agentId Agent ID
     * @param array<array{role: string, content: string}> $messages Chat messages
     * @param array{
     *     bloq_id?: int,
     *     thread_id?: string,
     *     use_rag?: bool,
     *     model?: string
     * } $options Chat options
     * @return ChatResponse
     */
    public function chat(int|string $agentId, array $messages, array $options = []): ChatResponse
    {
        $response = $this->http->post('/api/v1/bloqs/agents/generate-response', [
            'agent_id' => $agentId,
            'messages' => $messages,
            'bloq_id' => $options['bloq_id'] ?? null,
            'thread_id' => $options['thread_id'] ?? null,
            'use_rag' => $options['use_rag'] ?? true,
            'model' => $options['model'] ?? null,
        ]);

        return new ChatResponse($response);
    }

    /**
     * Multi-step agent conversation (V5 workflow).
     *
     * Executes a complex query that may involve multiple steps,
     * tool usage, and human-in-the-loop approval.
     *
     * @param int|string $agentId Agent ID
     * @param string $query The user's query/request
     * @param array{
     *     bloq_id?: int,
     *     conversation_history?: array,
     *     require_approval?: bool,
     *     metadata?: array
     * } $options Workflow options
     * @return WorkflowRun
     */
    public function multiStep(int|string $agentId, string $query, array $options = []): WorkflowRun
    {
        $response = $this->http->post('/api/v1/bloqs/agents/multi-step-response', [
            'agent_id' => $agentId,
            'query' => $query,
            'bloq_id' => $options['bloq_id'] ?? null,
            'conversation_history' => $options['conversation_history'] ?? [],
            'require_approval' => $options['require_approval'] ?? false,
            'metadata' => $options['metadata'] ?? [],
        ]);

        return new WorkflowRun($response, $this->http, $this->config);
    }

    /**
     * Call an integration directly (Pattern 1 - Manual Execution).
     *
     * Execute a specific integration action without LLM planning.
     * Useful for automation, scripting, and programmatic access.
     *
     * @example
     * ```php
     * // Send email via Gmail integration
     * $result = $fl->agents->callIntegration(11, 'gmail', 'send', [
     *     'to' => 'john@example.com',
     *     'subject' => 'Meeting Reminder',
     *     'body' => 'Tomorrow at 2pm'
     * ]);
     *
     * // Post to Slack
     * $result = $fl->agents->callIntegration(11, 'slack', 'post', [
     *     'channel' => '#general',
     *     'message' => 'Deployment complete!'
     * ]);
     * ```
     *
     * @param int|string $agentId Agent ID
     * @param string $integration Integration name (gmail, slack, google-calendar, etc.)
     * @param string $action Action to perform (send, post, create, etc.)
     * @param array $params Action parameters
     * @return array Integration execution result
     */
    public function callIntegration(int|string $agentId, string $integration, string $action, array $params = []): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->post("/api/v1/users/{$userId}/bloqs/agents/{$agentId}/call-integration", [
            'integration' => $integration,
            'action' => $action,
            'params' => $params,
        ]);
    }

    /**
     * Add a file to the agent's memory (knowledge base).
     *
     * @param int|string $agentId Agent ID
     * @param string $filePath Path to the file
     * @param array{
     *     title?: string,
     *     description?: string,
     *     tags?: array
     * } $metadata File metadata
     * @return bool
     */
    public function addMemory(int|string $agentId, string $filePath, array $metadata = []): bool
    {
        // Read file content and add to metadata
        if (!isset($metadata['content'])) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \InvalidArgumentException("Failed to read file: {$filePath}");
            }
            $metadata['content'] = $content;
        }
        
        $this->http->upload(
            "/api/v1/bloqs/agents/{$agentId}/add-memory",
            $filePath,
            $metadata
        );

        return true;
    }

    /**
     * Attach knowledge content to an agent for RAG (Retrieval-Augmented Generation).
     *
     * This method indexes content to the vector store and updates the agent's
     * file_attachments field to enable RAG in chat. This is the recommended
     * way to add knowledge to an agent without creating a separate bloq.
     *
     * @param int|string $agentId Agent ID
     * @param string $content Text content to index
     * @param array{
     *     title?: string,
     *     type?: string,
     *     description?: string
     * } $metadata Content metadata
     * @return array{agent: Agent, vector_id: string} Updated agent and vector ID
     *
     * @example
     * ```php
     * $result = $iris->agents->attachKnowledge(387, $medicalInfo, [
     *     'title' => 'Medical Information',
     *     'type' => 'medical_record'
     * ]);
     * echo "Vector ID: {$result['vector_id']}\n";
     * echo "Agent now has " . count($result['agent']->fileAttachments) . " attachments\n";
     * ```
     */
    public function attachKnowledge(int|string $agentId, string $content, array $metadata = []): array
    {
        // 1. Index content to vector store with agent_id
        $vectorData = array_merge(
            [
                'content' => $content,
                'agent_id' => (int) $agentId,
            ],
            $metadata
        );
        
        $indexResponse = $this->http->post("/api/v1/vector/store", $vectorData);
        $vectorId = $indexResponse['vector_id'] ?? $indexResponse['id'] ?? null;

        if (!$vectorId) {
            throw new \RuntimeException('Failed to index content: no vector_id returned');
        }

        // 2. Get current agent to retrieve existing file_attachments
        $agent = $this->get($agentId);
        $fileAttachments = $agent->fileAttachments ?? [];

        // 3. Add new attachment
        $newAttachment = [
            'title' => $metadata['title'] ?? 'Knowledge Document',
            'type' => $metadata['type'] ?? 'document',
            'vector_ids' => [$vectorId],
        ];

        if (isset($metadata['description'])) {
            $newAttachment['description'] = $metadata['description'];
        }

        $fileAttachments[] = $newAttachment;

        // 4. Update agent with new file_attachments
        $updatedAgent = $this->update($agentId, [
            'file_attachments' => $fileAttachments,
        ]);

        return [
            'agent' => $updatedAgent,
            'vector_id' => $vectorId,
        ];
    }

    /**
     * Attach knowledge from a file to an agent for RAG.
     *
     * Reads file content, indexes it to vector store, and updates the agent's
     * file_attachments field. Supports text files, markdown, JSON, etc.
     *
     * @param int|string $agentId Agent ID
     * @param string $filePath Path to file
     * @param array{
     *     title?: string,
     *     type?: string,
     *     description?: string
     * } $metadata File metadata
     * @return array{agent: Agent, vector_id: string} Updated agent and vector ID
     * @throws \InvalidArgumentException if file cannot be read
     */
    public function attachKnowledgeFile(int|string $agentId, string $filePath, array $metadata = []): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \InvalidArgumentException("Failed to read file: {$filePath}");
        }

        // Use filename as title if not provided
        if (!isset($metadata['title'])) {
            $metadata['title'] = basename($filePath);
        }

        return $this->attachKnowledge($agentId, $content, $metadata);
    }

    /**
     * Toggle public access for an agent.
     *
     * @param int|string $agentId Agent ID
     * @param bool $isPublic Whether the agent should be public
     * @return Agent
     */
    public function togglePublic(int|string $agentId, bool $isPublic): Agent
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post(
            "/api/v1/users/{$userId}/bloqs/agents/{$agentId}/public/toggle",
            ['is_public' => $isPublic]
        );

        return new Agent($response);
    }

    /**
     * Get analytics for a public agent.
     *
     * @param int|string $agentId Agent ID
     * @return array Analytics data
     */
    public function getAnalytics(int|string $agentId): array
    {
        $userId = $this->config->requireUserId();
        return $this->http->get("/api/v1/users/{$userId}/bloqs/agents/{$agentId}/public/analytics");
    }

    /**
     * Generate a webhook URL for the agent.
     *
     * @param int|string $agentId Agent ID
     * @return array{url: string, secret: string}
     */
    public function generateWebhook(int|string $agentId): array
    {
        return $this->http->post("/api/v1/bloqs/agents/{$agentId}/webhook/generate");
    }

    /**
     * Get webhook settings for an agent.
     *
     * @param int|string $agentId Agent ID
     * @return array Webhook settings
     */
    public function getWebhook(int|string $agentId): array
    {
        return $this->http->get("/api/v1/bloqs/agents/{$agentId}/webhook");
    }

    /**
     * Discover MCP tools available for an agent.
     *
     * @param int|string $agentId Agent ID
     * @return array Available MCP tools
     */
    public function discoverTools(int|string $agentId): array
    {
        return $this->http->post("/api/v1/agents/{$agentId}/mcp/discover-tools");
    }

    /**
     * Get all public agents.
     *
     * @param array{
     *     search?: string,
     *     category?: string,
     *     page?: int,
     *     per_page?: int
     * } $options Search options
     * @return AgentCollection
     */
    public function listPublic(array $options = []): AgentCollection
    {
        $response = $this->http->get('/api/v1/public/agents', $options);

        return new AgentCollection(
            array_map(fn($data) => new Agent($data), $response['data'] ?? $response),
            $response['meta'] ?? []
        );
    }

    /**
     * Chat with a public agent by slug (no authentication required).
     *
     * @param string $slug Agent's public slug
     * @param array<array{role: string, content: string}> $messages Chat messages
     * @return ChatResponse
     */
    public function chatPublic(string $slug, array $messages): ChatResponse
    {
        $response = $this->http->post("/api/v1/public/agents/{$slug}/chat", [
            'messages' => $messages,
        ]);

        return new ChatResponse($response);
    }

    /**
     * Create an agent with full configuration (unified API).
     * 
     * This method exposes the complete API configuration including schedules,
     * integrations, and settings in a single call. Perfect for complex setups.
     *
     * @param array $config Full agent configuration
     * @return Agent
     * 
     * @example Create elderly care agent with scheduling
     * ```php
     * $agent = $iris->agents->createFromConfig([
     *     'name' => 'Grandma Helper',
     *     'initial_prompt' => 'You are a caring assistant...',
     *     'type' => 'content',
     *     'config' => [
     *         'model' => 'gpt-4o-mini',
     *         'temperature' => 0.7
     *     ],
     *     'settings' => [
     *         'schedule' => [
     *             'enabled' => true,
     *             'timezone' => 'America/New_York',
     *             'recurring_tasks' => [
     *                 ['name' => 'Morning Medication', 'time' => '08:00', 'message' => 'Time for your morning meds'],
     *                 ['name' => 'Evening Check-in', 'time' => '21:00', 'message' => 'Good evening! How are you feeling?']
     *             ]
     *         ],
     *         'agentIntegrations' => [
     *             'gmail' => true,
     *             'google-calendar' => true,
     *             'slack' => false
     *         ],
     *         'enabledFunctions' => [
     *             'manageLeads' => true,
     *             'deepResearch' => false
     *         ]
     *     ]
     * ]);
     * ```
     */
    public function createFromConfig(array $config): Agent
    {
        $userId = $this->config->requireUserId();
        
        // Ensure type is set
        if (!isset($config['type'])) {
            $config['type'] = 'content';
        }
        
        $response = $this->http->post(
            "/api/v1/users/{$userId}/bloqs/agents",
            $config
        );

        return new Agent($response['data'] ?? $response);
    }

    /**
     * Update agent with full configuration.
     * 
     * Updates an existing agent with complete configuration including
     * schedules, integrations, and all settings.
     *
     * @param int|string $agentId Agent ID
     * @param array $config Full configuration to update
     * @return Agent
     */
    public function updateFullConfig(int|string $agentId, array $config): Agent
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->put(
            "/api/v1/users/{$userId}/bloqs/agents/{$agentId}",
            $config
        );

        return new Agent($response['data'] ?? $response);
    }

    /**
     * Get agent schedule configuration.
     *
     * @param int|string $agentId Agent ID
     * @return array Schedule configuration
     */
    public function getSchedule(int|string $agentId): array
    {
        $agent = $this->get($agentId);
        return $agent->settings['schedule'] ?? [];
    }

    /**
     * Set agent schedule with recurring tasks.
     *
     * @param int|string $agentId Agent ID
     * @param array $schedule Schedule configuration
     * @return Agent Updated agent
     * 
     * @example Set medication reminders
     * ```php
     * $agent = $iris->agents->setSchedule(335, [
     *     'enabled' => true,
     *     'timezone' => 'America/Chicago',
     *     'recurring_tasks' => [
     *         ['name' => 'Morning Meds', 'time' => '08:00', 'message' => 'Time for medications'],
     *         ['name' => 'Lunch Meds', 'time' => '12:00', 'message' => 'Lunch medication reminder'],
     *         ['name' => 'Evening Meds', 'time' => '18:00', 'message' => 'Evening medications'],
     *         ['name' => 'Bedtime Meds', 'time' => '21:00', 'message' => 'Bedtime medications']
     *     ]
     * ]);
     * ```
     */
    public function setSchedule(int|string $agentId, array $schedule): Agent
    {
        $agent = $this->get($agentId);
        $settings = $agent->settings ?? [];
        $settings['schedule'] = $schedule;
        
        return $this->patch($agentId, ['settings' => $settings]);
    }

    /**
     * Add a recurring task to agent schedule.
     *
     * @param int|string $agentId Agent ID
     * @param array $task Task configuration
     * @return Agent Updated agent
     * 
     * @example Add single reminder
     * ```php
     * $agent = $iris->agents->addScheduledTask(335, [
     *     'name' => 'Afternoon Water Reminder',
     *     'time' => '15:00',
     *     'message' => 'Time to drink some water!'
     * ]);
     * ```
     */
    public function addScheduledTask(int|string $agentId, array $task): Agent
    {
        $schedule = $this->getSchedule($agentId);
        $tasks = $schedule['recurring_tasks'] ?? [];
        $tasks[] = $task;
        
        $schedule['recurring_tasks'] = $tasks;
        if (!isset($schedule['enabled'])) {
            $schedule['enabled'] = true;
        }
        
        return $this->setSchedule($agentId, $schedule);
    }

    /**
     * Remove a scheduled task by name.
     *
     * @param int|string $agentId Agent ID
     * @param string $taskName Name of task to remove
     * @return Agent Updated agent
     */
    public function removeScheduledTask(int|string $agentId, string $taskName): Agent
    {
        $schedule = $this->getSchedule($agentId);
        $tasks = $schedule['recurring_tasks'] ?? [];
        
        $tasks = array_filter($tasks, fn($task) => ($task['name'] ?? '') !== $taskName);
        $schedule['recurring_tasks'] = array_values($tasks);
        
        return $this->setSchedule($agentId, $schedule);
    }

    /**
     * Get agent integrations configuration.
     *
     * @param int|string $agentId Agent ID
     * @return array Integrations status
     */
    public function getIntegrations(int|string $agentId): array
    {
        $agent = $this->get($agentId);
        return $agent->settings['agentIntegrations'] ?? [];
    }

    /**
     * Enable/disable agent integrations.
     *
     * @param int|string $agentId Agent ID
     * @param array $integrations Integrations to enable/disable
     * @return Agent Updated agent
     * 
     * @example Enable Gmail and Google Calendar
     * ```php
     * $agent = $iris->agents->setIntegrations(335, [
     *     'gmail' => true,
     *     'google-calendar' => true,
     *     'slack' => false,
     *     'google-drive' => true
     * ]);
     * ```
     */
    public function setIntegrations(int|string $agentId, array $integrations): Agent
    {
        $agent = $this->get($agentId);
        $settings = $agent->settings ?? [];
        $settings['agentIntegrations'] = $integrations;
        
        return $this->patch($agentId, ['settings' => $settings]);
    }

    /**
     * Enable a single integration.
     *
     * @param int|string $agentId Agent ID
     * @param string $integration Integration name (e.g., 'gmail', 'slack')
     * @return Agent Updated agent
     */
    public function enableIntegration(int|string $agentId, string $integration): Agent
    {
        $integrations = $this->getIntegrations($agentId);
        $integrations[$integration] = true;
        return $this->setIntegrations($agentId, $integrations);
    }

    /**
     * Disable a single integration.
     *
     * @param int|string $agentId Agent ID
     * @param string $integration Integration name
     * @return Agent Updated agent
     */
    public function disableIntegration(int|string $agentId, string $integration): Agent
    {
        $integrations = $this->getIntegrations($agentId);
        $integrations[$integration] = false;
        return $this->setIntegrations($agentId, $integrations);
    }

    /**
     * Get enabled functions for agent.
     *
     * @param int|string $agentId Agent ID
     * @return array Enabled functions
     */
    public function getEnabledFunctions(int|string $agentId): array
    {
        $agent = $this->get($agentId);
        return $agent->settings['enabledFunctions'] ?? [];
    }

    /**
     * Set enabled functions for agent.
     *
     * @param int|string $agentId Agent ID
     * @param array $functions Functions to enable/disable
     * @return Agent Updated agent
     * 
     * @example Enable lead management
     * ```php
     * $agent = $iris->agents->setEnabledFunctions(335, [
     *     'manageLeads' => true,
     *     'deepResearch' => false,
     *     'marketResearch' => true
     * ]);
     * ```
     */
    public function setEnabledFunctions(int|string $agentId, array $functions): Agent
    {
        $agent = $this->get($agentId);
        $settings = $agent->settings ?? [];
        $settings['enabledFunctions'] = $functions;
        
        return $this->patch($agentId, ['settings' => $settings]);
    }

    /**
     * Get the current file attachments for an agent.
     *
     * @param int|string $agentId Agent ID
     * @return array Current file attachments
     */
    public function getFileAttachments(int|string $agentId): array
    {
        $agent = $this->get($agentId);
        return $agent->fileAttachments ?? [];
    }

    /**
     * Add file attachments to an agent.
     *
     * This method adds files to the agent's existing attachments without
     * removing any current ones. The files should be in the format returned
     * by CloudFilesResource::uploadForAgent().
     *
     * @param int|string $agentId Agent ID
     * @param array $attachments Array of file attachment data
     * @return Agent Updated agent
     *
     * @example
     * ```php
     * // Upload a file and attach it to an agent
     * $attachment = $iris->cloudFiles->uploadForAgent('/path/to/data.csv', 40);
     * $agent = $iris->agents->addFileAttachments(335, [$attachment]);
     *
     * // Or upload and attach in one call
     * $agent = $iris->agents->uploadAndAttachFiles(335, ['/path/to/file.csv'], 40);
     * ```
     */
    public function addFileAttachments(int|string $agentId, array $attachments): Agent
    {
        // Get current agent with its file attachments
        $agent = $this->get($agentId);
        $currentAttachments = $agent->fileAttachments ?? [];

        // Merge with new attachments
        $allAttachments = array_merge($currentAttachments, $attachments);

        // Update agent with new attachments
        return $this->patch($agentId, [
            'fileAttachments' => $allAttachments,
        ]);
    }

    /**
     * Replace all file attachments on an agent.
     *
     * This method replaces ALL file attachments with the new ones.
     * Use addFileAttachments() to add without removing existing.
     *
     * @param int|string $agentId Agent ID
     * @param array $attachments Array of file attachment data
     * @return Agent Updated agent
     */
    public function setFileAttachments(int|string $agentId, array $attachments): Agent
    {
        return $this->patch($agentId, [
            'fileAttachments' => $attachments,
        ]);
    }

    /**
     * Remove a file attachment from an agent.
     *
     * @param int|string $agentId Agent ID
     * @param int $cloudFileId Cloud file ID to remove
     * @return Agent Updated agent
     */
    public function removeFileAttachment(int|string $agentId, int $cloudFileId): Agent
    {
        $agent = $this->get($agentId);
        $currentAttachments = $agent->fileAttachments ?? [];

        // Filter out the attachment
        $filtered = array_values(array_filter(
            $currentAttachments,
            fn($a) => ($a['cloud_file_id'] ?? 0) !== $cloudFileId
        ));

        return $this->patch($agentId, [
            'fileAttachments' => $filtered,
        ]);
    }

    /**
     * Clear all file attachments from an agent.
     *
     * @param int|string $agentId Agent ID
     * @return Agent Updated agent
     */
    public function clearFileAttachments(int|string $agentId): Agent
    {
        return $this->patch($agentId, [
            'fileAttachments' => [],
        ]);
    }

    /**
     * Get the public/shareable URL for an agent.
     *
     * Returns URLs in the format: https://app.heyiris.io/agent/simple/{id}?bloq={bloqId}
     *
     * @param int|string $agentId Agent ID
     * @param string $baseUrl Base URL (default: https://app.heyiris.io)
     * @return array{simple: string, embed: string, public: ?string}
     *
     * @example
     * ```php
     * // Get all URLs for an agent
     * $urls = $iris->agents->getUrls(11);
     * echo $urls['simple'];  // https://app.heyiris.io/agent/simple/11?bloq=40
     * echo $urls['embed'];   // Same as simple
     * echo $urls['public'];  // https://app.heyiris.io/agent/my-agent-slug (if public)
     * ```
     */
    public function getUrls(int|string $agentId, string $baseUrl = 'https://app.heyiris.io'): array
    {
        $agent = $this->get($agentId);
        return $agent->getUrls($baseUrl);
    }

    /**
     * Get the simple/embed URL for an agent.
     *
     * This is the direct link to chat with the agent.
     * Format: https://app.heyiris.io/agent/simple/{id}?bloq={bloqId}
     *
     * @param int|string $agentId Agent ID
     * @param string $baseUrl Base URL (default: https://app.heyiris.io)
     * @return string
     *
     * @example
     * ```php
     * $url = $iris->agents->getUrl(11);
     * // https://app.heyiris.io/agent/simple/11?bloq=40
     * ```
     */
    public function getUrl(int|string $agentId, string $baseUrl = 'https://app.heyiris.io'): string
    {
        $agent = $this->get($agentId);
        return $agent->getSimpleUrl($baseUrl);
    }

    /**
     * Upload files and attach them to an agent in one step.
     *
     * This is a convenience method that:
     * 1. Uploads each file to cloud storage
     * 2. Formats the attachment data
     * 3. Adds them to the agent's file attachments
     *
     * Requires the CloudFilesResource to be injected.
     *
     * @param int|string $agentId Agent ID
     * @param array $filePaths Array of file paths to upload
     * @param int $bloqId Bloq ID to upload files to
     * @param array $options Upload options (applied to all files)
     * @return Agent Updated agent with new attachments
     *
     * @example
     * ```php
     * // Upload and attach files in one call
     * $agent = $iris->agents->uploadAndAttachFiles(335, [
     *     '/path/to/training_data.csv',
     *     '/path/to/product_info.pdf',
     * ], 40);
     *
     * echo "Agent now has " . count($agent->fileAttachments) . " files attached\n";
     * ```
     */
    public function uploadAndAttachFiles(
        int|string $agentId,
        array $filePaths,
        int $bloqId,
        array $options = []
    ): Agent {
        // We need to access the CloudFilesResource
        // Since we have access to the same http client and config, we can create one
        $cloudFiles = new \IRIS\SDK\Resources\CloudFiles\CloudFilesResource(
            $this->http,
            $this->config
        );

        // Upload all files and format for attachment
        $attachments = $cloudFiles->uploadMultipleForAgent($filePaths, $bloqId, $options);

        // Add to agent
        return $this->addFileAttachments($agentId, $attachments);
    }

    /**
     * Get skills resource for managing agent skills.
     *
     * @param int|string $agentId Agent ID
     * @return SkillsResource
     * 
     * @example
     * ```php
     * // Manage skills for an agent
     * $skills = $iris->agents->skills(123);
     *
     * // List skills
     * $allSkills = $skills->list();
     *
     * // Create a skill
     * $skill = $skills->create(new SkillConfig(
     *     skill_name: 'Research',
     *     description: 'Conducts research',
     *     instructions: 'Use multiple sources...'
     * ));
     * ```
     */
    public function skills(int|string $agentId): SkillsResource
    {
        return new SkillsResource($this->http, $this->config, (int) $agentId);
    }

    /**
     * List workflows attached to an agent.
     *
     * @param int|string $agentId Agent ID
     * @return array Array of workflows with pivot data (is_enabled, priority, config)
     * 
     * @example
     * ```php
     * $workflows = $iris->agents->listWorkflows(164);
     * foreach ($workflows as $workflow) {
     *     echo "{$workflow['name']} - Priority: {$workflow['priority']}\n";
     * }
     * ```
     */
    public function listWorkflows(int|string $agentId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->get("/api/v1/users/{$userId}/bloqs/agents/{$agentId}/workflows");
        
        return $response['data'] ?? [];
    }

    /**
     * Attach a workflow to an agent.
     *
     * @param int|string $agentId Agent ID
     * @param int|string $workflowId Workflow Template ID
     * @param array{
     *     is_enabled?: bool,
     *     priority?: int,
     *     config?: array
     * } $options Workflow configuration
     * @return array Response data
     * 
     * @example
     * ```php
     * $iris->agents->attachWorkflow(164, 8, [
     *     'is_enabled' => true,
     *     'priority' => 10,
     *     'config' => ['max_results' => 50]
     * ]);
     * ```
     */
    public function attachWorkflow(int|string $agentId, int|string $workflowId, array $options = []): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->post(
            "/api/v1/users/{$userId}/bloqs/agents/{$agentId}/workflows/{$workflowId}",
            $options
        );
        
        return $response['data'] ?? $response;
    }

    /**
     * Detach a workflow from an agent.
     *
     * @param int|string $agentId Agent ID
     * @param int|string $workflowId Workflow Template ID
     * @return array Response data
     * 
     * @example
     * ```php
     * $iris->agents->detachWorkflow(164, 8);
     * ```
     */
    public function detachWorkflow(int|string $agentId, int|string $workflowId): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->delete(
            "/api/v1/users/{$userId}/bloqs/agents/{$agentId}/workflows/{$workflowId}"
        );
        
        return $response;
    }

    /**
     * Update workflow settings for an agent.
     *
     * @param int|string $agentId Agent ID
     * @param int|string $workflowId Workflow Template ID
     * @param array{
     *     is_enabled?: bool,
     *     priority?: int,
     *     config?: array
     * } $settings Updated settings
     * @return array Response data
     * 
     * @example
     * ```php
     * $iris->agents->updateWorkflowSettings(164, 8, [
     *     'priority' => 20,
     *     'is_enabled' => false
     * ]);
     * ```
     */
    public function updateWorkflowSettings(int|string $agentId, int|string $workflowId, array $settings): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->patch(
            "/api/v1/users/{$userId}/bloqs/agents/{$agentId}/workflows/{$workflowId}",
            $settings
        );
        
        return $response;
    }

    /**
     * Sync all workflows for an agent (replaces existing attachments).
     *
     * @param int|string $agentId Agent ID
     * @param array $workflows Array of workflow configurations
     * @return array Response data with synced count
     * 
     * @example
     * ```php
     * $iris->agents->syncWorkflows(164, [
     *     ['workflow_id' => 8, 'priority' => 10, 'is_enabled' => true],
     *     ['workflow_id' => 1, 'priority' => 5, 'is_enabled' => true],
     * ]);
     * ```
     */
    public function syncWorkflows(int|string $agentId, array $workflows): array
    {
        $userId = $this->config->requireUserId();
        $response = $this->http->put(
            "/api/v1/users/{$userId}/bloqs/agents/{$agentId}/workflows",
            ['workflows' => $workflows]
        );
        
        return $response['data'] ?? $response;
    }

    // ========================================================================
    // Workflow Discovery Methods
    // ========================================================================

    /**
     * List all available callable workflows that can be attached to agents.
     *
     * @param array{
     *     category?: string,
     *     execution_mode?: string,
     *     search?: string
     * } $filters Optional filters
     * @return array List of available workflows
     * 
     * @example
     * ```php
     * // List all callable workflows
     * $workflows = $iris->agents->listAvailableWorkflows();
     * 
     * // Search for specific workflows
     * $workflows = $iris->agents->listAvailableWorkflows(['search' => 'candidate']);
     * 
     * // Filter by execution mode
     * $workflows = $iris->agents->listAvailableWorkflows(['execution_mode' => 'agentic']);
     * ```
     */
    public function listAvailableWorkflows(array $filters = []): array
    {
        $response = $this->http->get('/api/v1/workflow-templates/callable', $filters);
        return $response['data'] ?? $response;
    }

    /**
     * Search for a workflow by its callable_name.
     *
     * @param string $callableName Workflow callable name (e.g., 'find_candidates')
     * @return array|null Workflow data or null if not found
     * 
     * @example
     * ```php
     * // Find workflow by callable name
     * $workflow = $iris->agents->findWorkflowByName('find_candidates');
     * 
     * if ($workflow) {
     *     echo "Found: {$workflow['name']} (ID: {$workflow['id']})\n";
     *     // Now attach it to an agent
     *     $iris->agents->attachWorkflow(164, $workflow['id']);
     * }
     * ```
     */
    public function findWorkflowByName(string $callableName): ?array
    {
        $response = $this->http->get('/api/v1/workflow-templates/search', [
            'callable_name' => $callableName
        ]);
        
        if (isset($response['success']) && $response['success'] === false) {
            return null;
        }
        
        return $response['data'] ?? null;
    }

    /**
     * Get detailed information about a workflow template.
     *
     * @param int|string $identifier Workflow ID or callable_name
     * @return array Detailed workflow information
     * 
     * @example
     * ```php
     * // Get workflow details by ID
     * $details = $iris->agents->getWorkflowDetails(8);
     * 
     * // Get workflow details by callable name
     * $details = $iris->agents->getWorkflowDetails('find_candidates');
     * 
     * echo "Workflow: {$details['name']}\n";
     * echo "Description: {$details['callable_description']}\n";
     * echo "Model: {$details['default_model']}\n";
     * echo "Agentic: " . ($details['is_agentic'] ? 'Yes' : 'No') . "\n";
     * ```
     */
    public function getWorkflowDetails(int|string $identifier): array
    {
        $response = $this->http->get("/api/v1/workflow-templates/{$identifier}");
        return $response['data'] ?? $response;
    }

    /**
     * Get the monitoring resource for an agent.
     *
     * Access performance metrics, logs, and evaluation data for an agent.
     *
     * @param int|string $agentId Agent ID
     * @return AgentMonitorResource
     *
     * @example
     * ```php
     * // Get performance metrics
     * $metrics = $iris->agents->monitor(387)->getMetrics('7d');
     * echo "Success rate: {$metrics['success_rate']}%\n";
     * echo "Avg response time: {$metrics['avg_response_time_ms']}ms\n";
     *
     * // Get latest evaluation
     * $evaluation = $iris->agents->monitor(387)->getLatestEvaluation();
     * if ($evaluation) {
     *     echo "Badge: {$evaluation['certification_badge']}\n";
     *     echo "Score: {$evaluation['average_score']}%\n";
     * }
     *
     * // Check agent health
     * $health = $iris->agents->monitor(387)->health();
     * echo "Status: {$health['status']}\n";
     * ```
     */
    public function monitor(int|string $agentId): AgentMonitorResource
    {
        return new AgentMonitorResource($this->http, $this->config, (int) $agentId);
    }

}
