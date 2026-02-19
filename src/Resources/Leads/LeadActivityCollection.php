<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * LeadActivity Collection
 *
 * A collection of LeadActivity instances.
 */
class LeadActivityCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<LeadActivity>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<LeadActivity> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<LeadActivity>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?LeadActivity
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
        return array_map(fn(LeadActivity $activity) => $activity->toArray(), $this->items);
    }

    /**
     * Filter by activity type
     */
    public function byType(string $type): self
    {
        return new self(
            array_filter($this->items, fn(LeadActivity $activity) => $activity->type === $type),
            $this->meta
        );
    }

    /**
     * Get only AI messages
     */
    public function aiMessages(): self
    {
        return $this->byType('ai_message');
    }

    /**
     * Get only emails
     */
    public function emails(): self
    {
        return new self(
            array_filter($this->items, fn(LeadActivity $activity) => $activity->isEmail()),
            $this->meta
        );
    }
}
