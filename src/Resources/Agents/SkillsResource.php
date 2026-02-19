<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

use IRIS\SDK\Config;
use IRIS\SDK\Http\Client;

/**
 * Skills Resource
 *
 * Manage agent skills - train agents with specific capabilities.
 * 
 * @example
 * ```php
 * // Get skills resource for an agent
 * $skills = $iris->agents->skills(123);
 *
 * // List all skills
 * $allSkills = $skills->list();
 *
 * // Create a new skill
 * $skill = $skills->create(new SkillConfig(
 *     skill_name: 'Research & Analysis',
 *     description: 'Conducts deep research',
 *     instructions: 'Use multiple sources...',
 *     tool_mappings: ['research_tool']
 * ));
 *
 * // Update a skill
 * $updated = $skills->update($skill->id, ['active' => false]);
 *
 * // Delete a skill
 * $skills->delete($skill->id);
 * ```
 */
class SkillsResource
{
    protected Client $http;
    protected Config $config;
    protected int $agentId;

    public function __construct(Client $http, Config $config, int $agentId)
    {
        $this->http = $http;
        $this->config = $config;
        $this->agentId = $agentId;
    }

    /**
     * List all skills for the agent
     *
     * @return array<Skill>
     */
    public function list(): array
    {
        $response = $this->http->get("/api/v6/bloqs/agents/{$this->agentId}/skills");

        if (!isset($response['skills'])) {
            return [];
        }

        return array_map(fn($data) => new Skill($data), $response['skills']);
    }

    /**
     * Get a specific skill by ID
     *
     * @param int $skillId Skill ID
     * @return Skill
     */
    public function get(int $skillId): Skill
    {
        $response = $this->http->get("/api/v6/bloqs/agents/{$this->agentId}/skills/{$skillId}");

        return new Skill($response['skill']);
    }

    /**
     * Create a new skill
     *
     * @param SkillConfig $config Skill configuration
     * @return Skill
     */
    public function create(SkillConfig $config): Skill
    {
        $response = $this->http->post("/api/v6/bloqs/agents/{$this->agentId}/skills", $config->toArray());

        return new Skill($response['skill']);
    }

    /**
     * Update an existing skill
     *
     * @param int $skillId Skill ID
     * @param array<string, mixed> $updates Fields to update
     * @return Skill
     */
    public function update(int $skillId, array $updates): Skill
    {
        $response = $this->http->put("/api/v6/bloqs/agents/{$this->agentId}/skills/{$skillId}", $updates);

        return new Skill($response['skill']);
    }

    /**
     * Delete a skill (soft delete)
     *
     * @param int $skillId Skill ID
     * @return bool Success status
     */
    public function delete(int $skillId): bool
    {
        $response = $this->http->delete("/api/v6/bloqs/agents/{$this->agentId}/skills/{$skillId}");

        return $response['success'] ?? false;
    }

    /**
     * Get the training prompt for all active skills
     * This is what gets injected into the agent's context
     *
     * @return string Training prompt
     */
    public function getTrainingPrompt(): string
    {
        $response = $this->http->get("/api/v6/bloqs/agents/{$this->agentId}/skills/training-prompt");

        return $response['training_prompt'] ?? '';
    }

    /**
     * Activate a skill
     *
     * @param int $skillId Skill ID
     * @return Skill
     */
    public function activate(int $skillId): Skill
    {
        return $this->update($skillId, ['active' => true]);
    }

    /**
     * Deactivate a skill
     *
     * @param int $skillId Skill ID
     * @return Skill
     */
    public function deactivate(int $skillId): Skill
    {
        return $this->update($skillId, ['active' => false]);
    }
}
