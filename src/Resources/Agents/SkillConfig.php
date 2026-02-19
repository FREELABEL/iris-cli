<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * SkillConfig
 *
 * Configuration for creating or updating agent skills.
 * 
 * @example
 * ```php
 * $skill = $iris->agents->skills($agentId)->create(new SkillConfig(
 *     skill_name: 'Research & Analysis',
 *     description: 'Conducts deep research on companies and industries',
 *     instructions: 'When asked to research, use multiple sources...',
 *     tool_mappings: ['research_tool', 'user_agent_456'],
 *     trigger_conditions: ['User asks to research', 'User mentions analysis'],
 *     examples: [
 *         ['input' => 'Research Acme Corp', 'output' => 'Found 3 sources...']
 *     ],
 *     success_criteria: ['At least 3 sources', 'Verified information']
 * ));
 * ```
 */
class SkillConfig
{
    public function __construct(
        public string $skill_name,
        public string $description,
        public ?string $instructions = null,
        public ?array $tool_mappings = null,
        public ?array $trigger_conditions = null,
        public ?array $examples = null,
        public ?array $success_criteria = null,
        public ?bool $active = true
    ) {}

    /**
     * Convert config to array for API requests
     */
    public function toArray(): array
    {
        return array_filter([
            'skill_name' => $this->skill_name,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'tool_mappings' => $this->tool_mappings,
            'trigger_conditions' => $this->trigger_conditions,
            'examples' => $this->examples,
            'success_criteria' => $this->success_criteria,
            'active' => $this->active,
        ], fn($value) => $value !== null);
    }
}
