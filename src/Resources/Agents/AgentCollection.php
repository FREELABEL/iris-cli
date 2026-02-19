<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Agents;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Agent Collection
 *
 * A collection of Agent models with pagination support.
 */
class AgentCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<Agent>
     */
    protected array $agents;

    /**
     * Pagination metadata.
     */
    protected array $meta;

    /**
     * Create a new agent collection.
     *
     * @param array<Agent> $agents
     * @param array $meta Pagination metadata
     */
    public function __construct(array $agents, array $meta = [])
    {
        $this->agents = $agents;
        $this->meta = $meta;
    }

    /**
     * Get all agents as array.
     *
     * @return array<Agent>
     */
    public function all(): array
    {
        return $this->agents;
    }

    /**
     * Get first agent.
     */
    public function first(): ?Agent
    {
        return $this->agents[0] ?? null;
    }

    /**
     * Get last agent.
     */
    public function last(): ?Agent
    {
        return $this->agents[count($this->agents) - 1] ?? null;
    }

    /**
     * Find agent by ID.
     */
    public function find(int $id): ?Agent
    {
        foreach ($this->agents as $agent) {
            if ($agent->id === $id) {
                return $agent;
            }
        }
        return null;
    }

    /**
     * Filter agents by callback.
     *
     * @param callable $callback
     * @return self
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->agents, $callback), $this->meta);
    }

    /**
     * Get agents by type.
     */
    public function ofType(string $type): self
    {
        return $this->filter(fn(Agent $agent) => $agent->type === $type);
    }

    /**
     * Get public agents only.
     */
    public function public(): self
    {
        return $this->filter(fn(Agent $agent) => $agent->isPublic);
    }

    /**
     * Get agents with specific integration.
     */
    public function withIntegration(string $integration): self
    {
        return $this->filter(fn(Agent $agent) => $agent->hasIntegration($integration));
    }

    /**
     * Count agents.
     */
    public function count(): int
    {
        return count($this->agents);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->agents);
    }

    /**
     * Get iterator for foreach.
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->agents);
    }

    /**
     * Get pagination metadata.
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * Check if there are more pages.
     */
    public function hasMorePages(): bool
    {
        return ($this->meta['current_page'] ?? 1) < ($this->meta['last_page'] ?? 1);
    }

    /**
     * Get current page number.
     */
    public function currentPage(): int
    {
        return (int) ($this->meta['current_page'] ?? 1);
    }

    /**
     * Get total count.
     */
    public function total(): int
    {
        return (int) ($this->meta['total'] ?? count($this->agents));
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return array_map(fn(Agent $agent) => $agent->toArray(), $this->agents);
    }
}
