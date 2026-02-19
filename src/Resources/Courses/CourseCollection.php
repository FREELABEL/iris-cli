<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Courses;

/**
 * CourseCollection
 *
 * Collection of Course objects with pagination metadata.
 */
class CourseCollection implements \Countable, \ArrayAccess, \IteratorAggregate
{
    /** @var Course[] */
    public array $items;
    
    public ?int $total;
    public ?int $perPage;
    public ?int $currentPage;
    public ?int $lastPage;

    /**
     * @param Course[] $items
     * @param array $meta Pagination metadata
     */
    public function __construct(array $items, array $meta = [])
    {
        $this->items = $items;
        $this->total = $meta['total'] ?? null;
        $this->perPage = $meta['per_page'] ?? null;
        $this->currentPage = $meta['current_page'] ?? null;
        $this->lastPage = $meta['last_page'] ?? null;
    }

    /**
     * Get all courses in the collection.
     *
     * @return Course[]
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first course in the collection.
     */
    public function first(): ?Course
    {
        return $this->items[0] ?? null;
    }

    /**
     * Get the count of courses.
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
        return $this->currentPage !== null && $this->lastPage !== null && $this->currentPage < $this->lastPage;
    }

    /**
     * Filter courses by criteria.
     *
     * @param callable $callback Filter function
     * @return Course[]
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->items, $callback);
    }

    /**
     * Get only free courses.
     *
     * @return Course[]
     */
    public function onlyFree(): array
    {
        return $this->filter(fn(Course $c) => $c->isFree());
    }

    /**
     * Get only paid courses.
     *
     * @return Course[]
     */
    public function onlyPaid(): array
    {
        return $this->filter(fn(Course $c) => $c->isPaid());
    }

    /**
     * Get courses by difficulty level.
     *
     * @param string $level Difficulty level (beginner, intermediate, advanced, expert)
     * @return Course[]
     */
    public function byDifficulty(string $level): array
    {
        return $this->filter(fn(Course $c) => $c->difficultyLevel === $level);
    }

    /**
     * Map over the courses.
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
            'data' => array_map(fn(Course $c) => $c->toArray(), $this->items),
            'pagination' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
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
