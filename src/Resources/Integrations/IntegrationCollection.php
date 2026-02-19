<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Integrations;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Integration Collection
 *
 * A collection of Integration instances.
 */
class IntegrationCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<Integration>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<Integration> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<Integration>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?Integration
    {
        return $this->items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn(Integration $integration) => $integration->toArray(), $this->items);
    }

    /**
     * Filter connected integrations
     */
    public function connected(): self
    {
        return new self(
            array_filter($this->items, fn(Integration $integration) => $integration->isConnected()),
            $this->meta
        );
    }

    /**
     * Filter by type
     */
    public function byType(string $type): self
    {
        return new self(
            array_filter($this->items, fn(Integration $integration) => $integration->type === $type),
            $this->meta
        );
    }

    /**
     * Find integration by type
     */
    public function findByType(string $type): ?Integration
    {
        foreach ($this->items as $integration) {
            if ($integration->type === $type) {
                return $integration;
            }
        }

        return null;
    }

    /**
     * Filter integrations by status.
     *
     * @param string $status Status to filter by
     * @return IntegrationCollection
     */
    public function filterByStatus(string $status): IntegrationCollection
    {
        return new self(
            array_filter($this->items, fn(Integration $integration) => $integration->status === $status),
            $this->meta
        );
    }

    /**
     * Filter integrations by category.
     *
     * @param string $category Category to filter by
     * @return IntegrationCollection
     */
    public function filterByCategory(string $category): IntegrationCollection
    {
        return new self(
            array_filter($this->items, fn(Integration $integration) => $integration->category === $category),
            $this->meta
        );
    }
}
