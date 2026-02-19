<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Chat;

/**
 * V6 Chat Response
 *
 * Represents the response from a V6 ReAct Loop workflow.
 * Includes iteration tracking, doom loop detection, and tool execution details.
 */
class V6ChatResponse
{
    /**
     * The final response content.
     */
    public string $content;

    /**
     * Response status: 'completed', 'failed', 'paused', 'doom_loop', 'max_iterations'
     */
    public string $status;

    /**
     * Number of ReAct iterations executed.
     */
    public int $iterations;

    /**
     * Maximum iterations allowed.
     */
    public int $maxIterations;

    /**
     * List of tools that were used.
     */
    public array $toolsUsed;

    /**
     * Detailed tool execution results.
     */
    public array $toolResults;

    /**
     * Whether a doom loop was detected.
     */
    public bool $doomLoopDetected;

    /**
     * The tool that triggered the doom loop (if any).
     */
    public ?string $doomLoopTool;

    /**
     * AI model used.
     */
    public ?string $model;

    /**
     * Total tokens used across all iterations.
     */
    public int $totalTokens;

    /**
     * Workflow execution ID.
     */
    public ?string $workflowId;

    /**
     * Thread ID for conversation continuity.
     */
    public ?string $threadId;

    /**
     * Execution time in milliseconds.
     */
    public int $executionTimeMs;

    /**
     * RAG context was used.
     */
    public bool $usedRag;

    /**
     * RAG sources if used.
     */
    public array $sources;

    /**
     * Raw response data.
     */
    protected array $attributes;

    public function __construct(array $data)
    {
        $this->attributes = $data;

        // Core response
        $this->content = $data['content'] ?? $data['response'] ?? $data['summary'] ?? '';
        $this->status = $data['status'] ?? 'completed';

        // ReAct loop metrics
        $this->iterations = (int) ($data['iterations'] ?? $data['iteration'] ?? 1);
        $this->maxIterations = (int) ($data['max_iterations'] ?? 10);
        $this->toolsUsed = $data['tools_used'] ?? [];
        $this->toolResults = $data['tool_results'] ?? $data['execution_results'] ?? [];

        // Doom loop detection
        $this->doomLoopDetected = (bool) ($data['doom_loop_detected'] ?? false);
        $this->doomLoopTool = $data['doom_loop_tool'] ?? null;

        // Token usage
        $this->model = $data['model'] ?? null;
        $this->totalTokens = (int) ($data['total_tokens'] ?? 0);

        // Identifiers
        $this->workflowId = $data['workflow_id'] ?? null;
        $this->threadId = $data['thread_id'] ?? null;

        // Timing
        $this->executionTimeMs = (int) ($data['execution_time_ms'] ?? 0);

        // RAG
        $this->usedRag = (bool) ($data['used_rag'] ?? false);
        $this->sources = $data['sources'] ?? [];
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isDoomLoop(): bool
    {
        return $this->doomLoopDetected || $this->status === 'doom_loop';
    }

    public function isMaxIterations(): bool
    {
        return $this->status === 'max_iterations' || $this->iterations >= $this->maxIterations;
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function toArray(): array
    {
        return [
            'content' => $this->content,
            'status' => $this->status,
            'iterations' => $this->iterations,
            'max_iterations' => $this->maxIterations,
            'tools_used' => $this->toolsUsed,
            'tool_results' => $this->toolResults,
            'doom_loop_detected' => $this->doomLoopDetected,
            'doom_loop_tool' => $this->doomLoopTool,
            'model' => $this->model,
            'total_tokens' => $this->totalTokens,
            'workflow_id' => $this->workflowId,
            'thread_id' => $this->threadId,
            'execution_time_ms' => $this->executionTimeMs,
            'used_rag' => $this->usedRag,
            'sources' => $this->sources,
        ];
    }

    /**
     * Get a summary of the execution for display.
     */
    public function getSummary(): string
    {
        $parts = [];
        $parts[] = "Status: {$this->status}";
        $parts[] = "Iterations: {$this->iterations}/{$this->maxIterations}";

        if (count($this->toolsUsed) > 0) {
            $parts[] = 'Tools: ' . implode(', ', $this->toolsUsed);
        }

        if ($this->doomLoopDetected) {
            $parts[] = "Doom Loop: {$this->doomLoopTool}";
        }

        if ($this->executionTimeMs > 0) {
            $parts[] = sprintf('Time: %.2fs', $this->executionTimeMs / 1000);
        }

        return implode(' | ', $parts);
    }
}
