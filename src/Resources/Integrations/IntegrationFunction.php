<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Integrations;

/**
 * IntegrationFunction Model
 *
 * Represents a function that can be called on an integration.
 */
class IntegrationFunction
{
    public string $name;
    public string $description;
    public array $parameters;
    public ?array $returns;
    public bool $requiresAuth;

    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->name = $data['name'] ?? '';
        $this->description = $data['description'] ?? '';
        $this->parameters = $data['parameters'] ?? [];
        $this->returns = isset($data['returns']) && is_array($data['returns'])
            ? $data['returns']
            : null;
        $this->requiresAuth = (bool) ($data['requires_auth'] ?? true);
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function hasParameter(string $paramName): bool
    {
        return isset($this->parameters[$paramName]);
    }

    public function getParameterSchema(): array
    {
        return $this->parameters;
    }
}
