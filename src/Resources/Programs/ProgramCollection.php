<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Programs;

/**
 * ProgramCollection
 *
 * Collection of Program objects with pagination metadata.
 */
class ProgramCollection implements \Countable, \ArrayAccess, \IteratorAggregate
{
    /** @var Program[] */
    public array $items;
    
    public ?int $total;
    public ?int $perPage;
    public ?int $currentPage;
    public ?int $lastPage;
    public ?string $nextPageUrl;
    public ?string $prevPageUrl;

    /**
     * @param Program[] $items
     * @param array $meta Pagination metadata
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->total = $meta['total'] ?? null;
        $this->perPage = $meta['per_page'] ?? null;
        $this->currentPage = $meta['current_page'] ?? null;
        $this->lastPage = $meta['last_page'] ?? null;
        $this->nextPageUrl = $meta['next_page_url'] ?? null;
        $this->prevPageUrl = $meta['prev_page_url'] ?? null;
    }

    /**
     * Get all programs in the collection.
     *
     * @return Program[]
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first program in the collection.
     */
    public function first(): ?Program
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get the count of programs.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if there are more pages.
     */
    public function hasMorePages(): bool
    {
        return $this->nextPageUrl !== null;
    }

    /**
     * Filter programs by criteria.
     *
     * @param callable $callback Filter function
     * @return Program[]
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->items, $callback);
    }

    /**
     * Get only active programs.
     *
     * @return Program[]
     */
    public function onlyActive(): array
    {
        return $this->filter(fn(Program $p) => $p->isActive());
    }

    /**
     * Get only free programs.
     *
     * @return Program[]
     */
    public function onlyFree(): array
    {
        return $this->filter(fn(Program $p) => $p->isFree());
    }

    /**
     * Get only paid programs.
     *
     * @return Program[]
     */
    public function onlyPaid(): array
    {
        return $this->filter(fn(Program $p) => $p->isPaid());
    }

    /**
     * Map over the programs.
     *
     * @param callable $callback Map function
     * @return array
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /**
     * Convert collection to array.
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn(Program $p) => $p->toArray(), $this->items),
            'meta' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
                'next_page_url' => $this->nextPageUrl,
                'prev_page_url' => $this->prevPageUrl,
            ],
        ];
    }

    // ArrayAccess implementation
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // IteratorAggregate implementation
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
