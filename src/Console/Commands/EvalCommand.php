<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;
use IRIS\SDK\Evaluation\AgentEvaluator;
use IRIS\SDK\Evaluation\EvaluationTest;

/**
 * CLI command for agent evaluation.
 *
 * Usage:
 *   iris eval 387              # Run core tests for agent 387
 *   iris eval 387 --type=custom # Run custom tests
 *   iris eval 387 --type=comparison # Compare with/without web search
 *   iris eval --list           # List available core tests
 */
class EvalCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('eval')
            ->setDescription('Evaluate agent performance with comprehensive test scenarios')
            ->setHelp(<<<'HELP'
Run comprehensive evaluation tests against an agent to assess performance,
capabilities, and configuration effectiveness.

Test Types:
  core       - Run all 7 built-in test scenarios (default)
  custom     - Run custom evaluation scenarios
  comparison - Compare performance with/without web search

Examples:
  iris eval 387                      # Run core tests for agent 387
  iris eval 387 --type=custom       # Run custom tests
  iris eval 387 --type=comparison   # Compare with/without web search
  iris eval --list                   # List available core tests
HELP
            )
            ->addArgument('agent_id', InputArgument::OPTIONAL, 'Agent ID to evaluate')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Test type: core, custom, comparison', 'core')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available core tests')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON')
            ->addOption('save', 's', InputOption::VALUE_OPTIONAL, 'Save results to file (auto-generated name if no value)', false)
            ->addOption('update-agent', 'u', InputOption::VALUE_NONE, 'Update agent metadata with evaluation scores')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            // Build config options
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int) $userId;
            }

            $sdkConfig = new Config($configOptions);
            $iris = new IRIS($configOptions);
            $evaluator = new AgentEvaluator($iris);

            // Handle --list flag
            if ($input->getOption('list')) {
                $this->listCoreTests($evaluator, $io);
                return Command::SUCCESS;
            }

            // Require agent_id for evaluation
            $agentId = $input->getArgument('agent_id');
            if (!$agentId) {
                $io->error('Agent ID is required. Usage: iris eval <agent_id>');
                return Command::FAILURE;
            }

            $agentId = (int) $agentId;
            $testType = $input->getOption('type');

            $io->title('Agent Evaluation');
            $io->text([
                "Agent ID: {$agentId}",
                "Test Type: {$testType}",
                '',
            ]);

            $results = match ($testType) {
                'core' => $this->runCoreTests($evaluator, $agentId, $io),
                'custom' => $this->runCustomTests($evaluator, $agentId, $io),
                'comparison' => $this->runComparisonTests($evaluator, $iris, $agentId, $io),
                default => throw new \InvalidArgumentException("Unknown test type: {$testType}"),
            };

            // Output results
            if ($input->getOption('json')) {
                $output->writeln(json_encode($results, JSON_PRETTY_PRINT));
            } else {
                $report = $evaluator->generateReport($results);
                $output->writeln($report);
            }

            // Save results if requested
            $save = $input->getOption('save');
            if ($save !== false) {
                $filename = $save ?: "agent-eval-{$testType}-{$agentId}-" . date('Y-m-d-H-i-s') . '.json';
                file_put_contents($filename, json_encode($results, JSON_PRETTY_PRINT));
                $io->success("Results saved to: {$filename}");
            }

            // Calculate summary metrics
            $totalTests = count($results);
            $passed = count(array_filter($results, fn($r) => $r['success'] ?? false));
            $passRate = $totalTests > 0 ? round(($passed / $totalTests) * 100) : 0;
            $totalScore = array_sum(array_map(fn($r) => $r['evaluation']['score'] ?? 0, $results));
            $avgScore = $totalTests > 0 ? round($totalScore / $totalTests) : 0;

            // Update agent with evaluation scores if requested
            if ($input->getOption('update-agent')) {
                $this->updateAgentWithScores($iris, $agentId, $results, $avgScore, $passed, $totalTests, $passRate, $testType, $io);
            }

            $io->newLine();
            $statusIcon = $passRate >= 70 ? '🟢' : ($passRate >= 50 ? '🟡' : '🔴');
            $statusText = $passRate >= 70 ? 'GOOD' : ($passRate >= 50 ? 'OK' : 'NEEDS WORK');
            $io->text([
                "Pass Rate: {$passRate}% ({$passed}/{$totalTests})",
                "Status: {$statusIcon} {$statusText}",
            ]);

            return $passRate >= 50 ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function listCoreTests(AgentEvaluator $evaluator, SymfonyStyle $io): void
    {
        $io->title('Available Core Tests');

        $tests = [];
        foreach ($evaluator->getCoreTestNames() as $name) {
            $test = $evaluator->getCoreTest($name);
            $tests[] = [
                $name,
                $test->description,
            ];
        }

        $io->table(['Test Name', 'Description'], $tests);
        $io->text('Run with: iris eval <agent_id>');
    }

    private function runCoreTests(AgentEvaluator $evaluator, int $agentId, SymfonyStyle $io): array
    {
        $io->section('Running Core Functionality Tests');
        return $evaluator->runCoreTests($agentId);
    }

    private function runCustomTests(AgentEvaluator $evaluator, int $agentId, SymfonyStyle $io): array
    {
        $io->section('Running Custom Tests');

        $customTests = [
            new EvaluationTest(
                'web_search',
                'Search for latest AI news and developments',
                [
                    'requires_web_search' => true,
                    'keywords' => ['AI', 'news', 'recent'],
                    'min_response_length' => 100,
                ]
            ),
            new EvaluationTest(
                'personalization',
                'Remember I like technology. Give me a personalized tech update.',
                [
                    'should_personalize' => true,
                    'should_reference_interests' => true,
                    'min_response_length' => 100,
                ]
            ),
            new EvaluationTest(
                'complex_planning',
                'Help me plan a 5-day vacation to Hawaii with budget considerations',
                [
                    'should_break_down_complex' => true,
                    'should_be_structured' => true,
                    'min_response_length' => 200,
                ]
            ),
        ];

        $results = [];
        foreach ($customTests as $test) {
            $io->text("Running: {$test->name}...");
            $results[$test->name] = $evaluator->runTest($agentId, $test);

            $status = $results[$test->name]['success'] ? '✅' : '❌';
            $score = $results[$test->name]['evaluation']['score'] ?? 0;
            $io->text("{$status} {$test->name}: {$score}%");
        }

        return $results;
    }

    private function runComparisonTests(AgentEvaluator $evaluator, IRIS $iris, int $agentId, SymfonyStyle $io): array
    {
        $io->section('Running Comparative Tests (with/without web search)');

        // Get current state
        $agent = $iris->agents->get($agentId);
        $originalWebSearch = $agent->settings['enabledFunctions']['deepResearch'] ?? false;

        $results = [];

        // Test with web search enabled
        $io->text('Test 1: Web Search ENABLED');
        $iris->agents->patch($agentId, [
            'settings' => ['enabledFunctions' => ['deepResearch' => true]],
        ]);

        $testEnabled = new EvaluationTest(
            'web_search_enabled',
            'What are the latest developments in quantum computing?',
            [
                'requires_web_search' => true,
                'min_response_length' => 100,
                'max_response_time_ms' => 20000,
            ]
        );
        $results['web_search_enabled'] = $evaluator->runTest($agentId, $testEnabled);
        $io->text("Score: {$results['web_search_enabled']['evaluation']['score']}%");

        // Test with web search disabled
        $io->text('Test 2: Web Search DISABLED');
        $iris->agents->patch($agentId, [
            'settings' => ['enabledFunctions' => ['deepResearch' => false]],
        ]);

        $testDisabled = new EvaluationTest(
            'web_search_disabled',
            'What are the latest developments in quantum computing?',
            [
                'requires_web_search' => true,
                'min_response_length' => 100,
                'max_response_time_ms' => 20000,
            ]
        );
        $results['web_search_disabled'] = $evaluator->runTest($agentId, $testDisabled);
        $io->text("Score: {$results['web_search_disabled']['evaluation']['score']}%");

        // Restore original
        $iris->agents->patch($agentId, [
            'settings' => ['enabledFunctions' => ['deepResearch' => $originalWebSearch]],
        ]);

        $io->text('Restored original settings');

        return $results;
    }

    /**
     * Update agent metadata with evaluation scores.
     */
    private function updateAgentWithScores(
        IRIS $iris,
        int $agentId,
        array $results,
        float $avgScore,
        int $passed,
        int $totalTests,
        int $passRate,
        string $testType,
        SymfonyStyle $io
    ): void {
        $io->newLine();
        $io->text('📝 Updating agent with evaluation scores...');

        // Determine status
        $status = match (true) {
            $avgScore >= 80 => 'excellent',
            $avgScore >= 60 => 'good',
            $avgScore >= 40 => 'needs_improvement',
            default => 'major_issues'
        };

        // Determine certification badge
        $badge = match (true) {
            $avgScore >= 80 => 'gold',
            $avgScore >= 70 => 'silver',
            $avgScore >= 60 => 'bronze',
            default => 'none'
        };

        $badgeIcon = match ($badge) {
            'gold' => '🏆',
            'silver' => '🥈',
            'bronze' => '🥉',
            default => '⚪'
        };

        // Calculate total duration from results
        $totalDurationMs = array_sum(array_map(
            fn($r) => $r['evaluation']['response_time_ms'] ?? 0,
            $results
        ));

        // Build evaluation metadata
        $evaluationData = [
            'last_evaluated_at' => date('Y-m-d H:i:s'),
            'average_score' => $avgScore,
            'tests_passed' => $passed,
            'tests_total' => $totalTests,
            'pass_rate' => $passRate,
            'status' => $status,
            'certification_badge' => $badge,
            'test_type' => $testType,
            'test_names' => array_keys($results),
        ];

        try {
            // 1. Store evaluation results in backend database
            $io->text('  → Storing evaluation in database...');
            try {
                $storeResult = $iris->agents->monitor($agentId)->storeEvaluation([
                    'test_type' => $testType,
                    'average_score' => $avgScore,
                    'tests_passed' => $passed,
                    'tests_total' => $totalTests,
                    'pass_rate' => $passRate,
                    'certification_badge' => $badge,
                    'evaluation_status' => $status,
                    'test_results' => $results,
                    'test_names' => array_keys($results),
                    'total_duration_ms' => $totalDurationMs,
                    'avg_duration_ms' => $totalTests > 0 ? round($totalDurationMs / $totalTests, 2) : 0,
                    'sdk_version' => IRIS::VERSION ?? '1.0.0',
                    'metadata' => [
                        'php_version' => PHP_VERSION,
                        'timestamp' => date('c'),
                    ],
                ]);
                $io->text("  ✓ Evaluation stored (ID: {$storeResult['evaluation_id']})");
            } catch (\Exception $e) {
                $io->warning("  ⚠ Could not store to database: " . $e->getMessage());
            }

            // 2. Update agent settings with evaluation summary
            $io->text('  → Updating agent settings...');
            $agent = $iris->agents->get($agentId);
            $currentSettings = $agent->settings ?? [];
            $currentSettings['evaluation'] = $evaluationData;

            $iris->agents->patch($agentId, [
                'settings' => $currentSettings,
            ]);

            $io->success("✅ Agent metadata updated successfully!");
            $io->definitionList(
                ['Certification Badge' => "{$badgeIcon} {$badge}"],
                ['Status' => $status],
                ['Average Score' => "{$avgScore}%"],
                ['Pass Rate' => "{$passRate}% ({$passed}/{$totalTests})"],
                ['Last Evaluated' => $evaluationData['last_evaluated_at']]
            );
        } catch (\Exception $e) {
            $io->error("Failed to update agent metadata: " . $e->getMessage());
        }
    }
}
