<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Profiles;

use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * Collection of Profile objects
 */
class ProfileCollection implements IteratorAggregate, Countable
{
    /** @var Profile[] */
    private array $profiles;
    private array $meta;

    /**
     * @param Profile[] $profiles
     * @param array $meta
     */
    public function __construct(array $profiles, array $meta = [])
    {
        $this->profiles = $profiles;
        $this->meta = $meta;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->profiles);
    }

    public function count(): int
    {
        return count($this->profiles);
    }

    /**
     * @return Profile[]
     */
    public function all(): array
    {
        return $this->profiles;
    }

    public function first(): ?Profile
    {
        return $this->profiles[0] ?? null;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return array_map(fn($profile) => $profile->toArray(), $this->profiles);
    }
}
