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
use IRIS\SDK\Auth\CredentialStore;

/**
 * Test and evaluate V6 Automations end-to-end.
 *
 * Usage:
 *   iris automation:test --quick           # Quick smoke test
 *   iris automation:test --full            # Full integration test
 *   iris automation:test --create-only     # Test creation only
 *   iris automation:test --automation-id=16 # Test specific automation
 */
class AutomationTestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('automation:test')
            ->setDescription('Test and evaluate V6 Automations')
            ->setHelp(<<<'HELP'
Test and evaluate V6 Automations end-to-end.

This command runs comprehensive tests to verify:
- Automation creation
- Execution with V6 engine
- Status tracking
- Outcome delivery
- Error handling

<info>Test Modes:</info>
  --quick          Quick smoke test (create + execute, no wait)
  --full           Full integration test (create + execute + monitor)
  --create-only    Test creation only (no execution)
  --existing       Test with existing automation

<info>Examples:</info>
  # Quick test
  iris automation:test --quick

  # Full test with monitoring
  iris automation:test --full

  # Test existing automation
  iris automation:test --automation-id=16

  # Test creation only
  iris automation:test --create-only

  # Test with specific agent
  iris automation:test --full --agent-id=55
HELP
            )
            ->addArgument('mode', InputArgument::OPTIONAL, 'Test mode: quick, full, validate', 'quick')
            ->addOption('automation-id', null, InputOption::VALUE_REQUIRED, 'Test existing automation ID')
            ->addOption('agent-id', null, InputOption::VALUE_REQUIRED, 'Agent ID to use for test', '55')
            ->addOption('quick', null, InputOption::VALUE_NONE, 'Quick smoke test')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Full integration test')
            ->addOption('create-only', null, InputOption::VALUE_NONE, 'Test creation only')
            ->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Do not delete test automation after')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Execution timeout in seconds', '60')
            ->addOption('test-email', null, InputOption::VALUE_REQUIRED, 'Email address for test', 'test@freelabel.net')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Determine test mode
        $mode = $this->getTestMode($input);
        
        try {
            // Load credentials
            $store = new CredentialStore();
            $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
            $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

            if (!$apiKey || !$userId) {
                $io->error('Missing credentials. Set IRIS_API_KEY and IRIS_USER_ID or run: iris setup');
                return Command::FAILURE;
            }

            // Initialize SDK
            $iris = new IRIS([
                'api_key' => $apiKey,
                'user_id' => (int)$userId,
            ]);

            if (!$input->getOption('json')) {
                $io->title("V6 Automation Test - Mode: {$mode}");
            }

            // Run appropriate test
            $results = match ($mode) {
                'quick' => $this->runQuickTest($iris, $input, $io),
                'full' => $this->runFullTest($iris, $input, $io),
                'create-only' => $this->runCreateOnlyTest($iris, $input, $io),
                'existing' => $this->runExistingTest($iris, $input, $io),
                'validate' => $this->runValidationTest($iris, $input, $io),
                default => throw new \InvalidArgumentException("Unknown test mode: {$mode}"),
            };

            // Output results
            if ($input->getOption('json')) {
                $output->writeln(json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->displayResults($results, $io);
            }

            return $results['success'] ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            if ($input->getOption('json')) {
                $output->writeln(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ], JSON_PRETTY_PRINT));
            } else {
                $io->error('Test failed: ' . $e->getMessage());
                if ($output->isVerbose()) {
                    $io->text($e->getTraceAsString());
                }
            }
            return Command::FAILURE;
        }
    }

    private function getTestMode(InputInterface $input): string
    {
        if ($input->getOption('quick')) return 'quick';
        if ($input->getOption('full')) return 'full';
        if ($input->getOption('create-only')) return 'create-only';
        if ($input->getOption('automation-id')) return 'existing';
        
        return $input->getArgument('mode') ?: 'quick';
    }

    private function runQuickTest(IRIS $iris, InputInterface $input, SymfonyStyle $io): array
    {
        $startTime = microtime(true);
        $results = [
            'test_mode' => 'quick',
            'success' => true,
            'steps' => [],
        ];

        // Step 1: Create test automation
        $io->section('1. Creating test automation');
        try {
            $automation = $this->createTestAutomation($iris, $input);
            $results['steps']['create'] = [
                'success' => true,
                'automation_id' => $automation['id'],
                'name' => $automation['name'],
            ];
            $io->writeln("✓ Created automation ID: {$automation['id']}");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['create'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
            $io->error('Failed to create automation');
            return $results;
        }

        // Step 2: Execute automation
        $io->section('2. Executing automation');
        try {
            $run = $iris->automations->execute($automation['id']);
            $results['steps']['execute'] = [
                'success' => true,
                'run_id' => $run['run_id'],
                'status' => $run['status'],
            ];
            $io->writeln("✓ Execution started: {$run['run_id']}");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['execute'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
            $io->error('Failed to execute automation');
        }

        // Step 3: Get status
        $io->section('3. Checking status');
        try {
            $status = $iris->automations->status($run['run_id']);
            $results['steps']['status'] = [
                'success' => true,
                'status' => $status['status'],
                'progress' => $status['progress'],
            ];
            $io->writeln("✓ Status: {$status['status']} ({$status['progress']}%)");
        } catch (\Exception $e) {
            $results['steps']['status'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Cleanup
        if (!$input->getOption('no-cleanup')) {
            try {
                $iris->automations->delete($automation['id']);
                $io->writeln("\n✓ Cleaned up test automation");
            } catch (\Exception $e) {
                $io->warning("Failed to cleanup: {$e->getMessage()}");
            }
        }

        $results['duration'] = round(microtime(true) - $startTime, 2);
        return $results;
    }

    private function runFullTest(IRIS $iris, InputInterface $input, SymfonyStyle $io): array
    {
        $startTime = microtime(true);
        $results = [
            'test_mode' => 'full',
            'success' => true,
            'steps' => [],
        ];

        // Step 1: Create
        $io->section('1. Creating test automation');
        try {
            $automation = $this->createTestAutomation($iris, $input);
            $results['steps']['create'] = [
                'success' => true,
                'automation_id' => $automation['id'],
            ];
            $io->writeln("✓ Created automation ID: {$automation['id']}");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['create'] = ['success' => false, 'error' => $e->getMessage()];
            return $results;
        }

        // Step 2: List automations
        $io->section('2. Listing automations');
        try {
            $automations = $iris->automations->list();
            $found = false;
            foreach ($automations['data'] as $auto) {
                if ($auto['id'] === $automation['id']) {
                    $found = true;
                    break;
                }
            }
            $results['steps']['list'] = ['success' => $found];
            $io->writeln($found ? "✓ Found in list" : "✗ Not found in list");
        } catch (\Exception $e) {
            $results['steps']['list'] = ['success' => false, 'error' => $e->getMessage()];
        }

        // Step 3: Execute
        $io->section('3. Executing automation');
        try {
            $run = $iris->automations->execute($automation['id']);
            $results['steps']['execute'] = [
                'success' => true,
                'run_id' => $run['run_id'],
            ];
            $io->writeln("✓ Execution started: {$run['run_id']}");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['execute'] = ['success' => false, 'error' => $e->getMessage()];
            return $results;
        }

        // Step 4: Monitor to completion
        $io->section('4. Monitoring execution');
        try {
            $timeout = (int)$input->getOption('timeout');
            $io->writeln("Waiting up to {$timeout} seconds...");
            
            $finalStatus = $iris->automations->waitForCompletion(
                $run['run_id'],
                timeoutSeconds: $timeout,
                intervalSeconds: 2,
                onProgress: function($status) use ($io) {
                    $io->writeln("[" . date('H:i:s') . "] {$status['status']} - {$status['progress']}%");
                }
            );

            $success = $finalStatus['status'] === 'completed';
            $results['steps']['monitor'] = [
                'success' => $success,
                'final_status' => $finalStatus['status'],
                'progress' => $finalStatus['progress'],
                'iterations' => $finalStatus['results']['iterations'] ?? null,
                'outcomes_delivered' => $finalStatus['results']['outcomes_delivered'] ?? [],
            ];

            if ($success) {
                $io->success('Automation completed successfully!');
            } else {
                $io->error("Automation ended with status: {$finalStatus['status']}");
                $results['success'] = false;
            }

        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['monitor'] = ['success' => false, 'error' => $e->getMessage()];
            $io->error("Monitoring failed: {$e->getMessage()}");
        }

        // Step 5: Verify outcomes
        if (isset($run['run_id'])) {
            $io->section('5. Verifying outcomes');
            try {
                $outcomes = $iris->automations->getOutcomes($run['run_id']);
                $results['steps']['outcomes'] = [
                    'success' => !empty($outcomes),
                    'count' => count($outcomes),
                    'outcomes' => $outcomes,
                ];
                $io->writeln("✓ Delivered " . count($outcomes) . " outcome(s)");
            } catch (\Exception $e) {
                $results['steps']['outcomes'] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // Cleanup
        if (!$input->getOption('no-cleanup')) {
            try {
                $iris->automations->delete($automation['id']);
                $io->writeln("\n✓ Cleaned up test automation");
            } catch (\Exception $e) {
                $io->warning("Failed to cleanup: {$e->getMessage()}");
            }
        }

        $results['duration'] = round(microtime(true) - $startTime, 2);
        return $results;
    }

    private function runCreateOnlyTest(IRIS $iris, InputInterface $input, SymfonyStyle $io): array
    {
        $results = [
            'test_mode' => 'create-only',
            'success' => true,
            'steps' => [],
        ];

        $io->section('Creating test automation');
        try {
            $automation = $this->createTestAutomation($iris, $input);
            $results['steps']['create'] = [
                'success' => true,
                'automation_id' => $automation['id'],
                'name' => $automation['name'],
                'agent_id' => $automation['agent_id'],
                'execution_mode' => $automation['execution_mode'],
            ];
            $io->success("Created automation ID: {$automation['id']}");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['create'] = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
            $io->error('Failed to create automation');
        }

        return $results;
    }

    private function runExistingTest(IRIS $iris, InputInterface $input, SymfonyStyle $io): array
    {
        $automationId = (int)$input->getOption('automation-id');
        $results = [
            'test_mode' => 'existing',
            'automation_id' => $automationId,
            'success' => true,
            'steps' => [],
        ];

        // Step 1: Get automation
        $io->section("1. Getting automation #{$automationId}");
        try {
            $automation = $iris->automations->get($automationId);
            $results['steps']['get'] = [
                'success' => true,
                'name' => $automation['name'],
                'agent_id' => $automation['agent_id'],
            ];
            $io->writeln("✓ Found: {$automation['name']}");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['get'] = ['success' => false, 'error' => $e->getMessage()];
            return $results;
        }

        // Step 2: Execute
        $io->section('2. Executing automation');
        try {
            $run = $iris->automations->execute($automationId);
            $results['steps']['execute'] = [
                'success' => true,
                'run_id' => $run['run_id'],
            ];
            $io->writeln("✓ Execution started: {$run['run_id']}");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['execute'] = ['success' => false, 'error' => $e->getMessage()];
            return $results;
        }

        // Step 3: Monitor
        $io->section('3. Monitoring execution');
        try {
            $timeout = (int)$input->getOption('timeout');
            $finalStatus = $iris->automations->waitForCompletion(
                $run['run_id'],
                timeoutSeconds: $timeout,
                intervalSeconds: 2,
                onProgress: function($status) use ($io) {
                    $io->writeln("[" . date('H:i:s') . "] {$status['status']} - {$status['progress']}%");
                }
            );

            $success = $finalStatus['status'] === 'completed';
            $results['steps']['monitor'] = [
                'success' => $success,
                'final_status' => $finalStatus['status'],
                'outcomes_delivered' => count($finalStatus['results']['outcomes_delivered'] ?? []),
            ];

            if ($success) {
                $io->success('Test completed successfully!');
            } else {
                $io->error("Test failed with status: {$finalStatus['status']}");
                $results['success'] = false;
            }

        } catch (\Exception $e) {
            $results['success'] = false;
            $results['steps']['monitor'] = ['success' => false, 'error' => $e->getMessage()];
        }

        return $results;
    }

    private function runValidationTest(IRIS $iris, InputInterface $input, SymfonyStyle $io): array
    {
        $results = [
            'test_mode' => 'validate',
            'success' => true,
            'validations' => [],
        ];

        $io->section('Running validation tests');

        // Test 1: Valid config
        $validConfig = [
            'name' => 'Valid Test',
            'agent_id' => 55,
            'goal' => 'Test goal',
            'outcomes' => [['type' => 'email', 'description' => 'Test']],
        ];
        $validation = $iris->automations->validate($validConfig);
        $results['validations']['valid_config'] = [
            'expected' => true,
            'actual' => $validation['valid'],
            'passed' => $validation['valid'] === true,
        ];
        $io->writeln($validation['valid'] ? "✓ Valid config test passed" : "✗ Valid config test failed");

        // Test 2: Missing name
        $invalidConfig = [
            'agent_id' => 55,
            'goal' => 'Test',
            'outcomes' => [],
        ];
        $validation = $iris->automations->validate($invalidConfig);
        $results['validations']['missing_name'] = [
            'expected' => false,
            'actual' => $validation['valid'],
            'passed' => $validation['valid'] === false,
        ];
        $io->writeln(!$validation['valid'] ? "✓ Missing name validation passed" : "✗ Missing name validation failed");

        // Test 3: Invalid outcomes
        $invalidOutcomes = [
            'name' => 'Test',
            'agent_id' => 55,
            'goal' => 'Test',
            'outcomes' => [['type' => 'email']],  // Missing description
        ];
        $validation = $iris->automations->validate($invalidOutcomes);
        $results['validations']['invalid_outcomes'] = [
            'expected' => false,
            'actual' => $validation['valid'],
            'passed' => $validation['valid'] === false,
        ];
        $io->writeln(!$validation['valid'] ? "✓ Invalid outcomes validation passed" : "✗ Invalid outcomes validation failed");

        // Check overall success
        $allPassed = true;
        foreach ($results['validations'] as $validation) {
            if (!$validation['passed']) {
                $allPassed = false;
                break;
            }
        }
        $results['success'] = $allPassed;

        return $results;
    }

    private function createTestAutomation(IRIS $iris, InputInterface $input): array
    {
        $agentId = (int)$input->getOption('agent-id');
        $testEmail = $input->getOption('test-email');

        return $iris->automations->create([
            'name' => 'SDK Test Automation - ' . date('Y-m-d H:i:s'),
            'description' => 'Automated test created by automation:test command',
            'agent_id' => $agentId,
            'goal' => "Use the callIntegration tool with integration=\"gmail\" and action=\"send_email\" to send a test email to {$testEmail}. The email should confirm that the V6 automation system is working correctly.",
            'outcomes' => [
                [
                    'type' => 'email',
                    'description' => 'Test email sent via Gmail',
                    'destination' => [
                        'to' => $testEmail,
                        'subject' => 'V6 Automation SDK Test - ' . date('Y-m-d H:i:s'),
                    ],
                ],
            ],
            'success_criteria' => [
                'Email delivered successfully',
                'callIntegration tool returned success=true',
            ],
            'max_iterations' => 10,
        ]);
    }

    private function displayResults(array $results, SymfonyStyle $io): void
    {
        $io->newLine();
        $io->section('Test Results');

        // Overall status
        if ($results['success']) {
            $io->success('All tests passed! ✓');
        } else {
            $io->error('Some tests failed ✗');
        }

        // Test details
        if (isset($results['steps'])) {
            $io->writeln("\nStep Results:");
            foreach ($results['steps'] as $step => $data) {
                $status = $data['success'] ? '✓' : '✗';
                $io->writeln("  {$status} {$step}");
                
                if (!$data['success'] && isset($data['error'])) {
                    $io->writeln("    Error: {$data['error']}");
                }
            }
        }

        if (isset($results['validations'])) {
            $io->writeln("\nValidation Results:");
            foreach ($results['validations'] as $name => $data) {
                $status = $data['passed'] ? '✓' : '✗';
                $io->writeln("  {$status} {$name}");
            }
        }

        // Duration
        if (isset($results['duration'])) {
            $io->writeln("\nDuration: {$results['duration']}s");
        }
    }
}
