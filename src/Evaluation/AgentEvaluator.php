<?php

declare(strict_types=1);

namespace IRIS\SDK\Evaluation;

use IRIS\SDK\IRIS;
use IRIS\SDK\Resources\Agents\Agent;

/**
 * AgentEvaluator - Comprehensive testing framework for evaluating IRIS agent performance.
 *
 * @example Basic usage
 * ```php
 * $iris = new IRIS([
 *     'api_key' => 'your-api-key',
 *     'user_id' => 193,
 * ]);
 *
 * $evaluator = new AgentEvaluator($iris);
 *
 * // Run core tests
 * $results = $evaluator->runCoreTests(387);
 *
 * // Generate report
 * echo $evaluator->generateReport($results);
 * ```
 *
 * @example Custom tests
 * ```php
 * $customTest = new EvaluationTest(
 *     'web_search_validation',
 *     'Search for latest AI news',
 *     [
 *         'requires_web_search' => true,
 *         'keywords' => ['AI', 'news', 'recent'],
 *         'min_response_length' => 100,
 *     ]
 * );
 *
 * $result = $evaluator->runTest($agentId, $customTest);
 * ```
 */
class AgentEvaluator
{
    protected IRIS $iris;

    /**
     * Core test scenarios.
     *
     * @var array<string, EvaluationTest>
     */
    protected array $coreTests = [];

    /**
     * Create a new AgentEvaluator instance.
     *
     * @param IRIS $iris IRIS SDK instance
     */
    public function __construct(IRIS $iris)
    {
        $this->iris = $iris;
        $this->initializeCoreTests();
    }

    /**
     * Initialize the built-in core test scenarios.
     */
    protected function initializeCoreTests(): void
    {
        $this->coreTests = [
            'basic_conversation' => new EvaluationTest(
                'basic_conversation',
                'Hello! Please introduce yourself and tell me what you can help me with.',
                [
                    'min_response_length' => 50,
                    'max_response_time_ms' => 15000,
                    'should_introduce_self' => true,
                ],
                'Tests introduction and capabilities description'
            ),

            'web_search_capability' => new EvaluationTest(
                'web_search_capability',
                'What are the latest developments in artificial intelligence this week?',
                [
                    'requires_web_search' => true,
                    'keywords' => ['AI', 'artificial intelligence'],
                    'min_response_length' => 100,
                    'max_response_time_ms' => 25000,
                ],
                'Tests web search functionality'
            ),

            'market_research' => new EvaluationTest(
                'market_research',
                'Can you analyze the current market trends for electric vehicles?',
                [
                    'keywords' => ['EV', 'electric', 'market', 'trend'],
                    'min_response_length' => 150,
                    'max_response_time_ms' => 30000,
                    'should_be_structured' => true,
                ],
                'Tests market research and analysis capabilities'
            ),

            'personalization' => new EvaluationTest(
                'personalization',
                'I work in software development and love hiking on weekends. Can you suggest some activities for me?',
                [
                    'should_personalize' => true,
                    'should_reference_interests' => true,
                    'min_response_length' => 100,
                ],
                'Tests personalization and memory'
            ),

            'complex_reasoning' => new EvaluationTest(
                'complex_reasoning',
                'Help me plan a 5-day vacation to Tokyo, Japan with a budget of $2000. Include flights, accommodation, and activities.',
                [
                    'should_break_down_complex' => true,
                    'should_be_structured' => true,
                    'keywords' => ['Tokyo', 'Japan', 'budget'],
                    'min_response_length' => 200,
                    'max_response_time_ms' => 30000,
                ],
                'Tests complex planning abilities'
            ),

            'tool_integration' => new EvaluationTest(
                'tool_integration',
                'Search for recent news about technology startups and summarize the top 3 stories.',
                [
                    'requires_tool_use' => true,
                    'keywords' => ['startup', 'technology'],
                    'min_response_length' => 150,
                    'max_response_time_ms' => 30000,
                ],
                'Tests external API/tool usage'
            ),

            'error_handling' => new EvaluationTest(
                'error_handling',
                'What is the weather forecast for Atlantis next week?',
                [
                    'min_response_length' => 30,
                    'max_response_time_ms' => 15000,
                    'forbidden_keywords' => ['error', 'failed', 'exception'],
                ],
                'Tests graceful failure handling'
            ),
        ];
    }

    /**
     * Run all core tests for an agent.
     *
     * @param int|string $agentId Agent ID
     * @return array<string, array> Test results keyed by test name
     */
    public function runCoreTests(int|string $agentId): array
    {
        $results = [];

        foreach ($this->coreTests as $name => $test) {
            echo "Running test: {$name}...\n";
            $results[$name] = $this->runTest($agentId, $test);

            $status = $results[$name]['success'] ? '✅' : '❌';
            $score = $results[$name]['evaluation']['score'] ?? 0;
            echo "{$status} {$name}: {$score}%\n\n";
        }

        return $results;
    }

    /**
     * Run a single evaluation test.
     *
     * @param int|string $agentId Agent ID
     * @param EvaluationTest $test The test to run
     * @return array Test result
     */
    public function runTest(int|string $agentId, EvaluationTest $test): array
    {
        $startTime = microtime(true);
        $result = [
            'test_name' => $test->name,
            'description' => $test->description,
            'prompt' => $test->prompt,
            'success' => false,
            'response' => null,
            'response_time_ms' => 0,
            'response_length' => 0,
            'evaluation' => [
                'score' => 0,
                'checks_passed' => 0,
                'checks_total' => 0,
                'details' => [],
            ],
            'error' => null,
        ];

        try {
            // Send chat message to agent
            $response = $this->iris->agents->chat($agentId, [
                ['role' => 'user', 'content' => $test->prompt],
            ]);

            $endTime = microtime(true);
            $responseTimeMs = (int) (($endTime - $startTime) * 1000);

            $responseText = $response->content ?? $response->message ?? '';

            $result['response'] = $responseText;
            $result['response_time_ms'] = $responseTimeMs;
            $result['response_length'] = strlen($responseText);

            // Evaluate the response
            $result['evaluation'] = $this->evaluateResponse($responseText, $responseTimeMs, $test);
            $result['success'] = $result['evaluation']['score'] >= 50;

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['response_time_ms'] = (int) ((microtime(true) - $startTime) * 1000);
        }

        return $result;
    }

    /**
     * Evaluate a response against test expectations.
     *
     * @param string $response The agent's response
     * @param int $responseTimeMs Response time in milliseconds
     * @param EvaluationTest $test The test with expectations
     * @return array Evaluation results
     */
    protected function evaluateResponse(string $response, int $responseTimeMs, EvaluationTest $test): array
    {
        $expectations = $test->getExpectationsWithDefaults();
        $checks = [];
        $passed = 0;
        $total = 0;

        // Check response length
        if (isset($expectations['min_response_length'])) {
            $total++;
            $lengthOk = strlen($response) >= $expectations['min_response_length'];
            $checks['min_response_length'] = [
                'passed' => $lengthOk,
                'expected' => ">= {$expectations['min_response_length']}",
                'actual' => strlen($response),
            ];
            if ($lengthOk) $passed++;
        }

        if (isset($expectations['max_response_length']) && $expectations['max_response_length'] !== null) {
            $total++;
            $lengthOk = strlen($response) <= $expectations['max_response_length'];
            $checks['max_response_length'] = [
                'passed' => $lengthOk,
                'expected' => "<= {$expectations['max_response_length']}",
                'actual' => strlen($response),
            ];
            if ($lengthOk) $passed++;
        }

        // Check response time
        if (isset($expectations['max_response_time_ms'])) {
            $total++;
            $timeOk = $responseTimeMs <= $expectations['max_response_time_ms'];
            $checks['response_time'] = [
                'passed' => $timeOk,
                'expected' => "<= {$expectations['max_response_time_ms']}ms",
                'actual' => "{$responseTimeMs}ms",
            ];
            if ($timeOk) $passed++;
        }

        // Check required keywords
        if (!empty($expectations['keywords'])) {
            $total++;
            $responseLower = strtolower($response);
            $keywordsFound = [];
            $keywordsMissing = [];

            foreach ($expectations['keywords'] as $keyword) {
                if (stripos($response, $keyword) !== false) {
                    $keywordsFound[] = $keyword;
                } else {
                    $keywordsMissing[] = $keyword;
                }
            }

            // Consider it passed if at least half of keywords are found
            $keywordRatio = count($keywordsFound) / count($expectations['keywords']);
            $keywordsOk = $keywordRatio >= 0.5;

            $checks['keywords'] = [
                'passed' => $keywordsOk,
                'expected' => implode(', ', $expectations['keywords']),
                'found' => implode(', ', $keywordsFound),
                'missing' => implode(', ', $keywordsMissing),
                'ratio' => round($keywordRatio * 100) . '%',
            ];
            if ($keywordsOk) $passed++;
        }

        // Check forbidden keywords
        if (!empty($expectations['forbidden_keywords'])) {
            $total++;
            $forbiddenFound = [];

            foreach ($expectations['forbidden_keywords'] as $keyword) {
                if (stripos($response, $keyword) !== false) {
                    $forbiddenFound[] = $keyword;
                }
            }

            $forbiddenOk = empty($forbiddenFound);
            $checks['forbidden_keywords'] = [
                'passed' => $forbiddenOk,
                'forbidden' => implode(', ', $expectations['forbidden_keywords']),
                'found' => implode(', ', $forbiddenFound),
            ];
            if ($forbiddenOk) $passed++;
        }

        // Check structured response (looks for bullet points, numbered lists, or headers)
        if (!empty($expectations['should_be_structured'])) {
            $total++;
            $hasStructure = preg_match('/(\d+\.|[-*•]|\n#{1,3}\s|:\n)/m', $response);
            $checks['structured'] = [
                'passed' => (bool) $hasStructure,
                'expected' => 'Structured format (bullets, numbers, or headers)',
                'actual' => $hasStructure ? 'Found structure markers' : 'No structure markers found',
            ];
            if ($hasStructure) $passed++;
        }

        // Check self introduction
        if (!empty($expectations['should_introduce_self'])) {
            $total++;
            $hasIntro = preg_match('/\b(I am|I\'m|my name|assistant|help you)\b/i', $response);
            $checks['self_introduction'] = [
                'passed' => (bool) $hasIntro,
                'expected' => 'Self introduction',
                'actual' => $hasIntro ? 'Found introduction' : 'No introduction found',
            ];
            if ($hasIntro) $passed++;
        }

        // Calculate score
        $score = $total > 0 ? round(($passed / $total) * 100) : 100;

        return [
            'score' => $score,
            'checks_passed' => $passed,
            'checks_total' => $total,
            'details' => $checks,
        ];
    }

    /**
     * Generate a formatted report from test results.
     *
     * @param array<string, array> $results Test results
     * @return string Formatted report
     */
    public function generateReport(array $results): string
    {
        $report = "\n" . str_repeat('=', 60) . "\n";
        $report .= "📊 AGENT EVALUATION REPORT\n";
        $report .= str_repeat('=', 60) . "\n\n";

        $totalTests = count($results);
        $passedTests = 0;
        $totalScore = 0;

        foreach ($results as $name => $result) {
            $status = ($result['success'] ?? false) ? '✅' : '❌';
            $score = $result['evaluation']['score'] ?? 0;
            $totalScore += $score;

            if ($result['success'] ?? false) {
                $passedTests++;
            }

            $report .= "{$status} {$name}\n";
            $report .= "   Score: {$score}% ({$result['evaluation']['checks_passed']}/{$result['evaluation']['checks_total']} checks passed)\n";
            $report .= "   Response Time: {$result['response_time_ms']}ms\n";
            $report .= "   Response Length: {$result['response_length']} chars\n";

            if (!empty($result['error'])) {
                $report .= "   ⚠️ Error: {$result['error']}\n";
            }

            // Show preview of response
            if (!empty($result['response'])) {
                $preview = substr($result['response'], 0, 100);
                $preview = str_replace("\n", " ", $preview);
                if (strlen($result['response']) > 100) {
                    $preview .= '...';
                }
                $report .= "   Preview: {$preview}\n";
            }

            // Show failed checks
            if (!empty($result['evaluation']['details'])) {
                $failedChecks = array_filter($result['evaluation']['details'], fn($c) => !$c['passed']);
                if (!empty($failedChecks)) {
                    $report .= "   Failed checks:\n";
                    foreach ($failedChecks as $checkName => $check) {
                        $expected = $check['expected'] ?? 'N/A';
                        $actual = $check['actual'] ?? ($check['found'] ?? 'N/A');
                        $report .= "     - {$checkName}: expected {$expected}, got {$actual}\n";
                    }
                }
            }

            $report .= "\n";
        }

        $report .= str_repeat('-', 60) . "\n";
        $report .= "📈 SUMMARY\n";
        $report .= str_repeat('-', 60) . "\n";

        $avgScore = $totalTests > 0 ? round($totalScore / $totalTests) : 0;
        $passRate = $totalTests > 0 ? round(($passedTests / $totalTests) * 100) : 0;

        $report .= "Tests Run: {$totalTests}\n";
        $report .= "Tests Passed: {$passedTests}/{$totalTests} ({$passRate}%)\n";
        $report .= "Average Score: {$avgScore}%\n";

        // Overall status
        if ($avgScore >= 80) {
            $report .= "Status: 🟢 EXCELLENT\n";
        } elseif ($avgScore >= 60) {
            $report .= "Status: 🟡 GOOD\n";
        } elseif ($avgScore >= 40) {
            $report .= "Status: 🟠 NEEDS IMPROVEMENT\n";
        } else {
            $report .= "Status: 🔴 MAJOR ISSUES\n";
        }

        $report .= str_repeat('=', 60) . "\n";

        return $report;
    }

    /**
     * Get a core test by name.
     *
     * @param string $name Test name
     * @return EvaluationTest|null
     */
    public function getCoreTest(string $name): ?EvaluationTest
    {
        return $this->coreTests[$name] ?? null;
    }

    /**
     * Get all core test names.
     *
     * @return array<string>
     */
    public function getCoreTestNames(): array
    {
        return array_keys($this->coreTests);
    }

    /**
     * Add a custom core test.
     *
     * @param string $name Test name
     * @param EvaluationTest $test Test instance
     * @return self
     */
    public function addCoreTest(string $name, EvaluationTest $test): self
    {
        $this->coreTests[$name] = $test;
        return $this;
    }

    /**
     * Get the IRIS SDK instance.
     *
     * @return IRIS
     */
    public function getIris(): IRIS
    {
        return $this->iris;
    }
}
