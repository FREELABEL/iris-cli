<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Leads;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Lead Collection
 *
 * A collection of Lead instances.
 */
class LeadCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<Lead>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<Lead> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<Lead>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?Lead
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

    public function hasMorePages(): bool
    {
        $currentPage = $this->meta['current_page'] ?? 1;
        $lastPage = $this->meta['last_page'] ?? 1;

        return $currentPage < $lastPage;
    }

    public function toArray(): array
    {
        return array_map(fn(Lead $lead) => $lead->toArray(), $this->items);
    }

    /**
     * Filter hot leads (score > 80)
     */
    public function hot(): self
    {
        return new self(
            array_filter($this->items, fn(Lead $lead) => $lead->isHot()),
            $this->meta
        );
    }

    /**
     * Filter leads by stage
     */
    public function byStage(int $stageId): self
    {
        return new self(
            array_filter($this->items, fn(Lead $lead) => $lead->stageId === $stageId),
            $this->meta
        );
    }

    /**
     * Filter leads with email
     */
    public function withEmail(): self
    {
        return new self(
            array_filter($this->items, fn(Lead $lead) => $lead->hasEmail()),
            $this->meta
        );
    }
}
