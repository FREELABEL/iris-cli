<?php

declare(strict_types=1);

namespace IRIS\SDK\Resources\Chat;

/**
 * Stream Event
 *
 * Represents a single event in the V6 ReAct Loop stream.
 * Events are sent via SSE as the workflow executes.
 */
class StreamEvent
{
    public const TYPE_THINKING = 'thinking';
    public const TYPE_TOOL_CALL = 'tool_call';
    public const TYPE_TOOL_RESULT = 'tool_result';
    public const TYPE_TEXT = 'text';
    public const TYPE_ITERATION = 'iteration';
    public const TYPE_DONE = 'done';
    public const TYPE_ERROR = 'error';
    public const TYPE_DOOM_LOOP = 'doom_loop';

    /**
     * Event type.
     */
    public string $type;

    /**
     * Event content (for text/thinking events).
     */
    public ?string $content;

    /**
     * Tool name (for tool_call/tool_result events).
     */
    public ?string $tool;

    /**
     * Tool description (for tool_call events).
     */
    public ?string $description;

    /**
     * Tool arguments (for tool_call events).
     */
    public ?array $arguments;

    /**
     * Tool result data (for tool_result events).
     */
    public mixed $result;

    /**
     * Current iteration number.
     */
    public int $iteration;

    /**
     * Error message (for error events).
     */
    public ?string $error;

    /**
     * Tools used so far (for done events).
     */
    public array $toolsUsed;

    /**
     * Final status (for done events).
     */
    public ?string $status;

    /**
     * Raw event data.
     */
    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->type = $data['type'] ?? 'unknown';
        $this->content = $data['content'] ?? null;
        $this->tool = $data['tool'] ?? $data['tool_name'] ?? null;
        $this->description = $data['description'] ?? null;
        $this->arguments = $data['arguments'] ?? $data['params'] ?? null;
        $this->result = $data['result'] ?? $data['data'] ?? null;
        $this->iteration = (int) ($data['iteration'] ?? 0);
        $this->error = $data['error'] ?? $data['message'] ?? null;
        $this->toolsUsed = $data['tools_used'] ?? [];
        $this->status = $data['status'] ?? null;
    }

    public function isThinking(): bool
    {
        return $this->type === self::TYPE_THINKING;
    }

    public function isToolCall(): bool
    {
        return $this->type === self::TYPE_TOOL_CALL;
    }

    public function isToolResult(): bool
    {
        return $this->type === self::TYPE_TOOL_RESULT;
    }

    public function isText(): bool
    {
        return $this->type === self::TYPE_TEXT;
    }

    public function isDone(): bool
    {
        return $this->type === self::TYPE_DONE;
    }

    public function isError(): bool
    {
        return $this->type === self::TYPE_ERROR;
    }

    public function isDoomLoop(): bool
    {
        return $this->type === self::TYPE_DOOM_LOOP;
    }

    public function isIteration(): bool
    {
        return $this->type === self::TYPE_ITERATION;
    }

    public function getRaw(): array
    {
        return $this->data;
    }

    /**
     * Create from SSE data line.
     */
    public static function fromSSE(string $data): self
    {
        $decoded = json_decode($data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new self([
                'type' => 'text',
                'content' => $data,
            ]);
        }

        return new self($decoded);
    }

    /**
     * Get display string for CLI output.
     */
    public function toDisplayString(): string
    {
        return match ($this->type) {
            self::TYPE_THINKING => "🤔 {$this->content}",
            self::TYPE_TOOL_CALL => "🔧 Calling {$this->tool}" . ($this->description ? ": {$this->description}" : ''),
            self::TYPE_TOOL_RESULT => "✅ {$this->tool} completed",
            self::TYPE_TEXT => $this->content ?? '',
            self::TYPE_ITERATION => "🔄 Iteration {$this->iteration}",
            self::TYPE_DONE => "📊 Done: {$this->status} | Iterations: {$this->iteration} | Tools: " . count($this->toolsUsed),
            self::TYPE_ERROR => "❌ Error: {$this->error}",
            self::TYPE_DOOM_LOOP => "⚠️ Doom loop detected: {$this->tool}",
            default => json_encode($this->data),
        };
    }
}
