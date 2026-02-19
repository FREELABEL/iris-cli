<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

/**
 * Skill
 *
 * Represents an agent skill - a trained capability with specific instructions.
 * 
 * @property int $id Skill ID
 * @property int $bloq_agent_id Agent ID this skill belongs to
 * @property string $skill_name Name of the skill
 * @property string $description Skill description
 * @property string|null $instructions Detailed training instructions
 * @property array<string> $tool_mappings Tools this skill uses
 * @property array<string> $trigger_conditions When to use this skill
 * @property array<array> $examples Example inputs/outputs
 * @property array<string> $success_criteria Success evaluation criteria
 * @property bool $active Whether the skill is active
 * @property int $usage_count Number of times skill has been used
 * @property string|null $last_used_at Last usage timestamp
 * @property string $created_at Creation timestamp
 * @property string $updated_at Update timestamp
 */
class Skill
{
    /** @var array<string, mixed> Raw skill data */
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function __get(string $name)
    {
        return $this->data[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    /**
     * Get the raw data array
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Get the skill ID
     */
    public function getId(): int
    {
        return (int) $this->data['id'];
    }

    /**
     * Get the skill name
     */
    public function getName(): string
    {
        return $this->data['skill_name'];
    }

    /**
     * Get the skill description
     */
    public function getDescription(): string
    {
        return $this->data['description'];
    }

    /**
     * Get skill instructions
     */
    public function getInstructions(): ?string
    {
        return $this->data['instructions'] ?? null;
    }

    /**
     * Get tool mappings
     */
    public function getToolMappings(): array
    {
        return $this->data['tool_mappings'] ?? [];
    }

    /**
     * Get trigger conditions
     */
    public function getTriggerConditions(): array
    {
        return $this->data['trigger_conditions'] ?? [];
    }

    /**
     * Get examples
     */
    public function getExamples(): array
    {
        return $this->data['examples'] ?? [];
    }

    /**
     * Get success criteria
     */
    public function getSuccessCriteria(): array
    {
        return $this->data['success_criteria'] ?? [];
    }

    /**
     * Check if skill is active
     */
    public function isActive(): bool
    {
        return $this->data['active'] ?? false;
    }

    /**
     * Get usage count
     */
    public function getUsageCount(): int
    {
        return $this->data['usage_count'] ?? 0;
    }
}
