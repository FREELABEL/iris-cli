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
 * CLI command for managing V6 Automations (Goal-Driven Workflows).
 *
 * Usage:
 *   iris automation create --name="Email Campaign" --agent-id=55 --goal="..." --outcomes='[...]'
 *   iris automation execute 16
 *   iris automation status c3337ce1-81f0-4785-adb8-a82347c563ae
 *   iris automation monitor c3337ce1-81f0-4785-adb8-a82347c563ae
 *   iris automation list
 *   iris automation runs
 */
class AutomationCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('automation')
            ->setDescription('Manage V6 Automations (goal-driven workflows)')
            ->setHelp(<<<'HELP'
V6 Automations - Define WHAT you want, not HOW to do it.

Automations are goal-driven workflows where you specify:
- A clear goal
- Expected outcomes (email sent, file created, etc.)
- Success criteria

The V6 ReAct engine autonomously executes by selecting tools and delivering outcomes.

<info>Commands:</info>
  automation create         Create a new automation
  automation execute        Execute an automation
  automation status         Get automation run status
  automation monitor        Monitor automation with live updates
  automation list           List all automations
  automation runs           List automation runs
  automation cancel         Cancel a running automation

<info>Examples:</info>
  # Create automation
  iris automation create \\
    --name="Daily Email Update" \\
    --agent-id=55 \\
    --goal="Send daily email to client@example.com with project status" \\
    --outcomes='[{"type":"email","description":"Email sent","destination":{"to":"client@example.com"}}]'

  # Execute automation
  iris automation execute 16

  # Monitor with live updates
  iris automation monitor c3337ce1-81f0-4785-adb8-a82347c563ae

  # Check status
  iris automation status c3337ce1-81f0-4785-adb8-a82347c563ae --verbose

  # List all automations
  iris automation list --agent-id=55

  # List runs
  iris automation runs --automation-id=16 --status=completed
HELP
            )
            ->addArgument('action', InputArgument::REQUIRED, 'Action: create, execute, status, monitor, list, runs, cancel, delete')
            ->addArgument('id', InputArgument::OPTIONAL, 'Automation ID or Run ID')
            // Create options
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Automation name')
            ->addOption('agent-id', null, InputOption::VALUE_REQUIRED, 'Agent ID to execute automation')
            ->addOption('goal', null, InputOption::VALUE_REQUIRED, 'Goal description')
            ->addOption('outcomes', null, InputOption::VALUE_REQUIRED, 'Outcomes JSON array')
            ->addOption('success-criteria', null, InputOption::VALUE_REQUIRED, 'Success criteria JSON array')
            ->addOption('max-iterations', null, InputOption::VALUE_REQUIRED, 'Max iterations (default: 10)', '10')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Automation description')
            // Execute options
            ->addOption('inputs', null, InputOption::VALUE_REQUIRED, 'Execution inputs JSON object')
            ->addOption('wait', null, InputOption::VALUE_NONE, 'Wait for completion after execution')
            // List/filter options
            ->addOption('automation-id', null, InputOption::VALUE_REQUIRED, 'Filter by automation ID')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status: pending, running, completed, failed')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Page number', '1')
            // Monitor options
            ->addOption('interval', null, InputOption::VALUE_REQUIRED, 'Polling interval in seconds', '2')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Timeout in seconds', '300')
            // Output options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Detailed output')
            // Auth options
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        try {
            // Load credentials
            $store = new CredentialStore();
            $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
            $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

            if (!$apiKey || !$userId) {
                $io->error('Missing credentials. Set IRIS_API_KEY and IRIS_USER_ID or run: iris setup');
                return Command::FAILURE;
            }

            // Initialize IRIS SDK
            $iris = new IRIS([
                'api_key' => $apiKey,
                'user_id' => (int)$userId,
            ]);

            // Dispatch to action handler
            return match ($action) {
                'create' => $this->createAutomation($iris, $input, $output, $io),
                'execute', 'run' => $this->executeAutomation($iris, $input, $output, $io),
                'status', 'get' => $this->getStatus($iris, $input, $output, $io),
                'monitor', 'watch' => $this->monitorAutomation($iris, $input, $output, $io),
                'list', 'ls' => $this->listAutomations($iris, $input, $output, $io),
                'runs', 'history' => $this->listRuns($iris, $input, $output, $io),
                'cancel', 'stop' => $this->cancelAutomation($iris, $input, $output, $io),
                'delete', 'rm' => $this->deleteAutomation($iris, $input, $output, $io),
                default => $this->unknownAction($action, $io),
            };

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function createAutomation(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $name = $input->getOption('name');
        $agentId = $input->getOption('agent-id');
        $goal = $input->getOption('goal');
        $outcomesJson = $input->getOption('outcomes');

        // Validate required fields
        if (!$name || !$agentId || !$goal || !$outcomesJson) {
            $io->error('Missing required options: --name, --agent-id, --goal, --outcomes');
            $io->text([
                'Example:',
                '  iris automation create \\',
                '    --name="Email Campaign" \\',
                '    --agent-id=55 \\',
                '    --goal="Send personalized emails to leads" \\',
                '    --outcomes=\'[{"type":"email","description":"Emails sent to all leads"}]\'',
            ]);
            return Command::FAILURE;
        }

        // Parse outcomes JSON
        $outcomes = json_decode($outcomesJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid JSON in --outcomes: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        // Parse success criteria if provided
        $successCriteria = [];
        if ($criteriaJson = $input->getOption('success-criteria')) {
            $successCriteria = json_decode($criteriaJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON in --success-criteria: ' . json_last_error_msg());
                return Command::FAILURE;
            }
        }

        $io->title('Creating V6 Automation');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Name', $name],
                ['Agent ID', $agentId],
                ['Goal', $goal],
                ['Outcomes', count($outcomes) . ' outcome(s)'],
                ['Success Criteria', count($successCriteria) . ' criteria'],
                ['Max Iterations', $input->getOption('max-iterations')],
            ]
        );

        try {
            $automation = $iris->automations->create([
                'name' => $name,
                'agent_id' => (int)$agentId,
                'goal' => $goal,
                'outcomes' => $outcomes,
                'success_criteria' => $successCriteria,
                'max_iterations' => (int)$input->getOption('max-iterations'),
                'description' => $input->getOption('description'),
            ]);

            if ($input->getOption('json')) {
                $output->writeln(json_encode($automation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            $io->success('Automation created successfully!');
            $io->definitionList(
                ['Automation ID' => $automation['id'] ?? $automation['data']['id'] ?? 'N/A'],
                ['Name' => $automation['name'] ?? 'N/A'],
                ['Agent ID' => $automation['agent_id'] ?? 'N/A']
            );

            $io->note([
                "Execute: iris automation execute {$automation['id']}",
                "View: iris automation list",
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to create automation: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function executeAutomation(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $automationId = $input->getArgument('id');

        if (!$automationId) {
            $io->error('Please provide automation ID');
            $io->text('Usage: iris automation execute <automation-id>');
            return Command::FAILURE;
        }

        // Parse inputs if provided
        $inputs = [];
        if ($inputsJson = $input->getOption('inputs')) {
            $inputs = json_decode($inputsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON in --inputs: ' . json_last_error_msg());
                return Command::FAILURE;
            }
        }

        $io->text("Executing automation #{$automationId}...");
        $io->newLine();

        try {
            $run = $iris->automations->execute((int)$automationId, $inputs);

            if ($input->getOption('json')) {
                $output->writeln(json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            $io->success('Automation execution started!');
            $io->definitionList(
                ['Run ID' => $run['run_id'] ?? 'N/A'],
                ['Automation ID' => $run['workflow_id'] ?? 'N/A'],
                ['Status' => $run['status'] ?? 'N/A'],
                ['Progress' => ($run['progress'] ?? 0) . '%']
            );

            // Wait for completion if --wait flag
            if ($input->getOption('wait')) {
                $io->newLine();
                $io->text('Waiting for completion...');
                $io->newLine();

                return $this->monitorAutomationInternal(
                    $iris,
                    $run['run_id'],
                    (int)$input->getOption('timeout'),
                    (int)$input->getOption('interval'),
                    $input->getOption('json'),
                    $output,
                    $io
                );
            }

            $io->note([
                "Monitor: iris automation monitor {$run['run_id']}",
                "Status: iris automation status {$run['run_id']}",
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to execute automation: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function getStatus(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $runId = $input->getArgument('id');

        if (!$runId) {
            $io->error('Please provide run ID');
            $io->text('Usage: iris automation status <run-id>');
            return Command::FAILURE;
        }

        try {
            $status = $iris->automations->status($runId);

            if ($input->getOption('json')) {
                $output->writeln(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Status header with color
            $statusColor = match ($status['status']) {
                'completed' => 'green',
                'running' => 'yellow',
                'failed' => 'red',
                default => 'gray',
            };

            $io->title("Automation Run: {$runId}");
            $io->writeln("<fg={$statusColor};options=bold>● {$status['status']}</>");
            $io->newLine();

            // Basic info
            $io->definitionList(
                ['Run ID' => $status['run_id'] ?? 'N/A'],
                ['Automation' => $status['workflow_name'] ?? 'N/A'],
                ['Status' => $status['status'] ?? 'N/A'],
                ['Progress' => ($status['progress'] ?? 0) . '%'],
                ['Started' => $status['started_at'] ?? 'N/A'],
                ['Completed' => $status['completed_at'] ?? 'N/A']
            );

            // Show results if completed
            if ($status['status'] === 'completed' && !empty($status['results'])) {
                $results = $status['results'];

                $io->section('Results');
                $io->definitionList(
                    ['Iterations' => $results['iterations'] ?? 'N/A'],
                    ['Tools Used' => implode(', ', $results['tools_used'] ?? [])]
                );

                if (!empty($results['content'])) {
                    $io->section('Content');
                    $io->text($results['content']);
                }

                if (!empty($results['outcomes_delivered'])) {
                    $io->section('Outcomes Delivered');
                    foreach ($results['outcomes_delivered'] as $outcome) {
                        $io->writeln("✓ {$outcome['description']}");
                        if (!empty($outcome['data'])) {
                            foreach ($outcome['data'] as $key => $value) {
                                $io->writeln("  {$key}: {$value}");
                            }
                        }
                    }
                }

                // Verbose: Show tool results
                if ($input->getOption('detailed') && !empty($results['tool_results'])) {
                    $io->section('Tool Results (Detailed)');
                    foreach ($results['tool_results'] as $i => $toolResult) {
                        $io->writeln("<fg=cyan>Tool Call #" . ($i + 1) . ":</>");
                        $io->writeln(json_encode($toolResult, JSON_PRETTY_PRINT));
                        $io->newLine();
                    }
                }
            }

            // Show error if failed
            if ($status['status'] === 'failed' && !empty($status['error'])) {
                $io->section('Error');
                $io->error($status['error']);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to get status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function monitorAutomation(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $runId = $input->getArgument('id');

        if (!$runId) {
            $io->error('Please provide run ID');
            $io->text('Usage: iris automation monitor <run-id>');
            return Command::FAILURE;
        }

        return $this->monitorAutomationInternal(
            $iris,
            $runId,
            (int)$input->getOption('timeout'),
            (int)$input->getOption('interval'),
            $input->getOption('json'),
            $output,
            $io
        );
    }

    private function monitorAutomationInternal(
        IRIS $iris,
        string $runId,
        int $timeout,
        int $interval,
        bool $jsonOutput,
        OutputInterface $output,
        SymfonyStyle $io
    ): int {
        $io->text("Monitoring automation run: {$runId}");
        $io->text("Press Ctrl+C to stop monitoring");
        $io->newLine();

        try {
            $status = $iris->automations->waitForCompletion(
                $runId,
                timeoutSeconds: $timeout,
                intervalSeconds: $interval,
                onProgress: function ($status) use ($output, $io, $jsonOutput) {
                    if ($jsonOutput) {
                        $output->writeln(json_encode($status, JSON_PRETTY_PRINT));
                        return;
                    }

                    $progress = $status['progress'] ?? 0;
                    $statusText = $status['status'] ?? 'unknown';
                    $timestamp = date('H:i:s');

                    $io->writeln("[{$timestamp}] Status: {$statusText} - Progress: {$progress}%");
                }
            );

            $io->newLine();

            if ($status['status'] === 'completed') {
                $io->success('Automation completed successfully!');
            } elseif ($status['status'] === 'failed') {
                $io->error('Automation failed');
            }

            // Show final results
            if (!$jsonOutput) {
                $io->definitionList(
                    ['Final Status' => $status['status']],
                    ['Iterations' => $status['results']['iterations'] ?? 'N/A'],
                    ['Outcomes' => count($status['results']['outcomes_delivered'] ?? [])]
                );
            }

            return $status['status'] === 'completed' ? Command::SUCCESS : Command::FAILURE;

        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function listAutomations(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $filters = [];

        if ($agentId = $input->getOption('agent-id')) {
            $filters['agent_id'] = (int)$agentId;
        }

        if ($page = $input->getOption('page')) {
            $filters['page'] = (int)$page;
        }

        try {
            $result = $iris->automations->list($filters);

            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            $automations = $result['data'] ?? [];

            if (empty($automations)) {
                $io->warning('No automations found');
                $io->text('Create one with: iris automation create --help');
                return Command::SUCCESS;
            }

            $io->title('V6 Automations');

            $rows = [];
            foreach ($automations as $automation) {
                $rows[] = [
                    $automation['id'] ?? 'N/A',
                    $automation['name'] ?? 'N/A',
                    $automation['agent_id'] ?? 'N/A',
                    substr($automation['agent_config']['goal'] ?? '', 0, 50) . '...',
                    count($automation['agent_config']['outcomes'] ?? []),
                ];
            }

            $io->table(
                ['ID', 'Name', 'Agent', 'Goal', 'Outcomes'],
                $rows
            );

            if (isset($result['pagination'])) {
                $p = $result['pagination'];
                $io->text("Page {$p['current_page']} of {$p['last_page']} ({$p['total']} total)");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to list automations: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function listRuns(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $filters = [];

        if ($automationId = $input->getOption('automation-id')) {
            $filters['automation_id'] = (int)$automationId;
        }

        if ($status = $input->getOption('status')) {
            $filters['status'] = $status;
        }

        if ($page = $input->getOption('page')) {
            $filters['page'] = (int)$page;
        }

        try {
            $result = $iris->automations->runs($filters);

            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            $runs = $result['data'] ?? [];

            if (empty($runs)) {
                $io->warning('No automation runs found');
                return Command::SUCCESS;
            }

            $io->title('Automation Runs');

            $rows = [];
            foreach ($runs as $run) {
                $statusColor = match ($run['status']) {
                    'completed' => 'green',
                    'running' => 'yellow',
                    'failed' => 'red',
                    default => 'gray',
                };

                $rows[] = [
                    substr($run['run_id'] ?? 'N/A', 0, 8) . '...',
                    $run['workflow_name'] ?? 'N/A',
                    "<fg={$statusColor}>{$run['status']}</>",
                    ($run['progress'] ?? 0) . '%',
                    $run['started_at'] ?? 'N/A',
                ];
            }

            $io->table(
                ['Run ID', 'Automation', 'Status', 'Progress', 'Started'],
                $rows
            );

            if (isset($result['pagination'])) {
                $p = $result['pagination'];
                $io->text("Page {$p['current_page']} of {$p['last_page']} ({$p['total']} total)");
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to list runs: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function cancelAutomation(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $runId = $input->getArgument('id');

        if (!$runId) {
            $io->error('Please provide run ID');
            $io->text('Usage: iris automation cancel <run-id>');
            return Command::FAILURE;
        }

        if (!$io->confirm("Cancel automation run {$runId}?", false)) {
            $io->text('Cancelled');
            return Command::SUCCESS;
        }

        try {
            $iris->automations->cancel($runId);
            $io->success("Automation run {$runId} cancelled successfully");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to cancel: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function deleteAutomation(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $automationId = $input->getArgument('id');

        if (!$automationId) {
            $io->error('Please provide automation ID');
            $io->text('Usage: iris automation delete <automation-id>');
            return Command::FAILURE;
        }

        if (!$io->confirm("Delete automation #{$automationId}? This cannot be undone.", false)) {
            $io->text('Cancelled');
            return Command::SUCCESS;
        }

        try {
            $iris->automations->delete((int)$automationId);
            $io->success("Automation #{$automationId} deleted successfully");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to delete: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function unknownAction(string $action, SymfonyStyle $io): int
    {
        $io->error("Unknown action: {$action}");
        $io->text([
            'Available actions:',
            '  create      Create new automation',
            '  execute     Execute automation',
            '  status      Get run status',
            '  monitor     Monitor with live updates',
            '  list        List automations',
            '  runs        List automation runs',
            '  cancel      Cancel running automation',
            '  delete      Delete automation',
        ]);
        return Command::FAILURE;
    }
}
