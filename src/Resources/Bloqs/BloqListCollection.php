<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Bloqs;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * BloqList Collection
 *
 * A collection of BloqList instances.
 */
class BloqListCollection implements IteratorAggregate, Countable
{
    /**
     * @var array<BloqList>
     */
    protected array $items;
    protected array $meta;

    /**
     * @param array<BloqList> $items
     * @param array $meta
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->meta = $meta;
    }

    /**
     * @return array<BloqList>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?BloqList
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
        return array_map(fn(BloqList $list) => $list->toArray(), $this->items);
    }

    /**
     * Sort lists by position
     */
    public function sortByPosition(): self
    {
        $items = $this->items;
        usort($items, fn(BloqList $a, BloqList $b) => $a->position <=> $b->position);
        
        return new self($items, $this->meta);
    }
}
