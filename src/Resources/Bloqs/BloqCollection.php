<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Bloq Collection
 *
 * A collection of Bloq instances.
 */
class BloqCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<Bloq>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<Bloq> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<Bloq>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?Bloq
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
        return array_map(fn(Bloq $bloq) => $bloq->toArray(), $this->items);
    }

    /**
     * Filter bloqs by pinned status
     */
    public function pinned(): self
    {
        return new self(
            array_filter($this->items, fn(Bloq $bloq) => $bloq->isPinned),
            $this->meta
        );
    }

    /**
     * Filter bloqs by unpinned status
     */
    public function unpinned(): self
    {
        return new self(
            array_filter($this->items, fn(Bloq $bloq) => !$bloq->isPinned),
            $this->meta
        );
    }
}
