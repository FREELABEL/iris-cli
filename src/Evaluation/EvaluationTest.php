<?php

declare(strict_types=1);

namespace IRIS\SDK\Evaluation;

/**
 * EvaluationTest - Defines a single test scenario for agent evaluation.
 *
 * @example
 * ```php
 * // Create a test for web search capability
 * $test = new EvaluationTest(
 *     'web_search_validation',
 *     'Search for latest AI news',
 *     [
 *         'requires_web_search' => true,
 *         'keywords' => ['AI', 'news', 'recent'],
 *         'min_response_length' => 100,
 *         'max_response_time_ms' => 25000,
 *     ]
 * );
 * ```
 */
class EvaluationTest
{
    /**
     * Test name/identifier
     */
    public string $name;

    /**
     * The prompt to send to the agent
     */
    public string $prompt;

    /**
     * Test expectations/criteria
     */
    public array $expectations;

    /**
     * Test description (optional)
     */
    public string $description;

    /**
     * Create a new evaluation test.
     *
     * @param string $name Test name/identifier
     * @param string $prompt The prompt to send to the agent
     * @param array{
     *     keywords?: array<string>,
     *     min_response_length?: int,
     *     max_response_length?: int,
     *     max_response_time_ms?: int,
     *     requires_web_search?: bool,
     *     requires_tool_use?: bool,
     *     should_personalize?: bool,
     *     should_reference_interests?: bool,
     *     should_break_down_complex?: bool,
     *     should_be_structured?: bool,
     *     should_introduce_self?: bool,
     *     forbidden_keywords?: array<string>,
     *     custom_validators?: array<callable>,
     * } $expectations Test expectations
     * @param string $description Optional description
     */
    public function __construct(
        string $name,
        string $prompt,
        array $expectations = [],
        string $description = ''
    ) {
        $this->name = $name;
        $this->prompt = $prompt;
        $this->expectations = $expectations;
        $this->description = $description ?: "Test: {$name}";
    }

    /**
     * Get default expectations merged with provided ones.
     *
     * @return array
     */
    public function getExpectationsWithDefaults(): array
    {
        return array_merge([
            'keywords' => [],
            'min_response_length' => 10,
            'max_response_length' => null,
            'max_response_time_ms' => 30000,
            'requires_web_search' => false,
            'requires_tool_use' => false,
            'should_personalize' => false,
            'should_reference_interests' => false,
            'should_break_down_complex' => false,
            'should_be_structured' => false,
            'should_introduce_self' => false,
            'forbidden_keywords' => [],
            'custom_validators' => [],
        ], $this->expectations);
    }

    /**
     * Convert to array representation.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'prompt' => $this->prompt,
            'expectations' => $this->expectations,
            'description' => $this->description,
        ];
    }

    /**
     * Create from array representation.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? 'unnamed_test',
            $data['prompt'] ?? '',
            $data['expectations'] ?? [],
            $data['description'] ?? ''
        );
    }
}
