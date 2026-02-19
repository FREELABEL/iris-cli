<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * BloqItem Collection
 *
 * A collection of BloqItem instances.
 */
class BloqItemCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<BloqItem>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<BloqItem> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<BloqItem>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?BloqItem
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
        return array_map(fn(BloqItem $item) => $item->toArray(), $this->items);
    }

    /**
     * Sort items by position
     */
    public function sortByPosition(): self
    {
        $items = $this->items;
        usort($items, fn(BloqItem $a, BloqItem $b) => $a->position <=> $b->position);
        
        return new self($items, $this->meta);
    }

    /**
     * Filter public items
     */
    public function publicOnly(): self
    {
        return new self(
            array_filter($this->items, fn(BloqItem $item) => $item->isPublic),
            $this->meta
        );
    }

    /**
     * Filter private items
     */
    public function privateOnly(): self
    {
        return new self(
            array_filter($this->items, fn(BloqItem $item) => !$item->isPublic),
            $this->meta
        );
    }
}
