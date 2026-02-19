<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Services;

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Service Collection
 *
 * A collection of Service models.
 */
class ServiceCollection implements IteratorAggregate, Countable
{
    /** @var Service[] */
    private array $services;
    private array $meta;

    /**
     * @param Service[] $services
     * @param array $meta
     */
    public function __construct(array $services, array $meta = [])
    {
        $this->services = $services;
        $this->meta = $meta;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->services);
    }

    public function count(): int
    {
        return count($this->services);
    }

    /**
     * @return Service[]
     */
    public function all(): array
    {
        return $this->services;
    }

    public function first(): ?Service
    {
        return $this->services[0] ?? null;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn($service) => $service->toArray(), $this->services);
    }
}
