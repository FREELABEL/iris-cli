<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * LeadTask Collection
 *
 * A collection of LeadTask instances.
 */
class LeadTaskCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<LeadTask>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<LeadTask> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<LeadTask>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?LeadTask
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
        return array_map(fn(LeadTask $task) => $task->toArray(), $this->items);
    }

    /**
     * Get completed tasks
     */
    public function completed(): self
    {
        return new self(
            array_filter($this->items, fn(LeadTask $task) => $task->isCompleted()),
            $this->meta
        );
    }

    /**
     * Get pending tasks
     */
    public function pending(): self
    {
        return new self(
            array_filter($this->items, fn(LeadTask $task) => $task->isPending()),
            $this->meta
        );
    }

    /**
     * Get overdue tasks
     */
    public function overdue(): self
    {
        return new self(
            array_filter($this->items, fn(LeadTask $task) => $task->isOverdue()),
            $this->meta
        );
    }

    /**
     * Sort by position
     */
    public function sortByPosition(): self
    {
        $items = $this->items;
        usort($items, fn(LeadTask $a, LeadTask $b) => $a->position <=> $b->position);
        
        return new self($items, $this->meta);
    }
}
