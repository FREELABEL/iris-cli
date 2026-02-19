<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Workflows;

/**
 * Workflow Result
 *
 * The final result of a completed workflow.
 */
class WorkflowResult
{
    /**
     * Main content/response.
     */
    public string $content;

    /**
     * Generated files (if any).
     */
    public array $files;

    /**
     * Execution summary.
     */
    public array $summary;

    /**
     * Total execution time in milliseconds.
     */
    public ?int $executionTime;

    /**
     * Token usage statistics.
     */
    public array $tokenUsage;

    /**
     * Step results.
     */
    public array $stepResults;

    /**
     * Metadata.
     */
    public array $metadata;

    /**
     * Raw attributes.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        $this->content = $data['content'] ?? $data['response'] ?? $data['message'] ?? '';
        $this->files = $data['files'] ?? $data['file_urls'] ?? [];
        $this->summary = $data['summary'] ?? [];
        $this->executionTime = $data['execution_time'] ?? $data['duration'] ?? null;
        $this->tokenUsage = $data['token_usage'] ?? $data['usage'] ?? [];
        $this->stepResults = $data['step_results'] ?? [];
        $this->metadata = $data['metadata'] ?? [];
    }

    /**
     * Get content as string.
     */
    public function __toString(): string
    {
        return $this->content;
    }

    /**
     * Check if result has content.
     */
    public function hasContent(): bool
    {
        return !empty(trim($this->content));
    }

    /**
     * Check if result has files.
     */
    public function hasFiles(): bool
    {
        return !empty($this->files);
    }

    /**
     * Get file URLs.
     *
     * @return array<string>
     */
    public function getFileUrls(): array
    {
        return array_map(function ($file) {
            return is_array($file) ? ($file['url'] ?? '') : $file;
        }, $this->files);
    }

    /**
     * Get total tokens used.
     */
    public function getTotalTokens(): int
    {
        return (int) ($this->tokenUsage['total_tokens'] ?? 0);
    }

    /**
     * Get execution time in seconds.
     */
    public function getExecutionTimeSeconds(): float
    {
        if ($this->executionTime === null) {
            return 0.0;
        }
        return $this->executionTime / 1000;
    }

    /**
     * Get raw attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'files' => $this->files,
            'summary' => $this->summary,
            'execution_time' => $this->executionTime,
            'token_usage' => $this->tokenUsage,
            'step_results' => $this->stepResults,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
