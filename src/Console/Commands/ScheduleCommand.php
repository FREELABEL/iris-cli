<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * ScheduleCommand - Manage agent scheduled jobs and view execution history
 *
 * Commands:
 *   iris schedule status                  - Overview of all schedules with stats
 *   iris schedule list [--agent-id=X]     - List scheduled jobs
 *   iris schedule create <agent-id>       - Create a new scheduled job
 *   iris schedule run <job-id>            - Run a job immediately
 *   iris schedule history <job-id>        - View execution history for a job
 *   iris schedule agent-history <agent>   - View all executions for an agent
 *   iris schedule all-history             - View all executions across agents
 *   iris schedule execution <exec-id>     - View full execution details
 *   iris schedule sync <agent-id>         - Sync agent's recurring tasks
 */
class ScheduleCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('schedule')
            ->setDescription('Manage agent scheduled jobs and execution history')
            ->setHelp('Commands: status, list, create, run, reset, reset-all, history, agent-history, all-history, execution, sync')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: status|list|create|run|reset|reset-all|history|agent-history|all-history|execution|sync')
            ->addArgument('id', InputArgument::OPTIONAL, 'Job ID or Agent ID (depending on action)')
            ->addOption('agent-id', 'a', InputOption::VALUE_REQUIRED, 'Filter by agent ID')
            ->addOption('task-name', 't', InputOption::VALUE_REQUIRED, 'Task name (for create)')
            ->addOption('prompt', 'p', InputOption::VALUE_REQUIRED, 'Prompt/task description (for create)')
            ->addOption('time', null, InputOption::VALUE_REQUIRED, 'Scheduled time HH:MM (for create)', '09:00')
            ->addOption('frequency', 'f', InputOption::VALUE_REQUIRED, 'Frequency: daily|weekly|monthly|once', 'daily')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Filter by status: completed|failed')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Limit results', 20)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        try {
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int) $userId;
            }

            $sdkConfig = new Config($configOptions);
            $iris = new IRIS($configOptions);

            switch ($action) {
                case 'status':
                    return $this->showStatus($iris, $input, $io);

                case 'list':
                    return $this->listJobs($iris, $input, $io);

                case 'create':
                    return $this->createJob($iris, $input, $io);

                case 'run':
                    return $this->runJob($iris, $input, $io);

                case 'reset':
                    return $this->resetJob($iris, $input, $io);

                case 'reset-all':
                    return $this->resetAllJobs($iris, $input, $io);

                case 'history':
                    return $this->jobHistory($iris, $input, $io);

                case 'agent-history':
                    return $this->agentHistory($iris, $input, $io);

                case 'all-history':
                    return $this->allHistory($iris, $input, $io);

                case 'execution':
                    return $this->showExecution($iris, $input, $io);

                case 'sync':
                    return $this->syncAgent($iris, $input, $io);

                default:
                    $io->error("Unknown action: {$action}. Use: status|list|create|run|reset|reset-all|history|agent-history|all-history|execution|sync");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function listJobs(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $params = [];

        if ($agentId = $input->getOption('agent-id')) {
            $params['agent_id'] = $agentId;
        }

        $params['agent_jobs_only'] = true;

        $response = $iris->schedules->list($params);
        $jobs = $response['data'] ?? $response ?? [];

        if ($input->getOption('json')) {
            $io->writeln(json_encode($jobs, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        if (empty($jobs)) {
            $io->info('No scheduled jobs found.');
            return Command::SUCCESS;
        }

        $io->title('Scheduled Jobs');

        $table = new Table($io);
        $table->setHeaders(['ID', 'Agent', 'Task', 'Frequency', 'Status', 'Next Run', 'Run Count']);

        foreach ($jobs as $job) {
            $table->addRow([
                $job['id'],
                $job['agent']['name'] ?? $job['agent_id'] ?? '-',
                substr($job['task_name'] ?? '-', 0, 30),
                $job['frequency'] ?? '-',
                $job['status'] ?? '-',
                $job['next_run_at'] ?? '-',
                $job['run_count'] ?? 0,
            ]);
        }

        $table->render();
        $io->text(sprintf('Total: %d jobs', count($jobs)));

        return Command::SUCCESS;
    }

    protected function createJob(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $agentId = $input->getArgument('id');

        if (!$agentId) {
            $io->error('Agent ID is required. Usage: iris schedule create <agent-id>');
            return Command::FAILURE;
        }

        $taskName = $input->getOption('task-name');
        $prompt = $input->getOption('prompt');

        if (!$taskName) {
            $taskName = $io->ask('Task name');
        }

        if (!$prompt) {
            $prompt = $io->ask('Prompt/task description', $taskName);
        }

        $response = $iris->schedules->create([
            'agent_id' => $agentId,
            'task_name' => $taskName,
            'prompt' => $prompt,
            'time' => $input->getOption('time'),
            'frequency' => $input->getOption('frequency'),
        ]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $job = $response['data'] ?? $response;

        $io->success("Scheduled job created!");
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $job['id'] ?? '-'],
                ['Task Name', $job['task_name'] ?? '-'],
                ['Frequency', $job['frequency'] ?? '-'],
                ['Next Run', $job['next_run_at'] ?? '-'],
                ['Status', $job['status'] ?? '-'],
            ]
        );

        return Command::SUCCESS;
    }

    protected function runJob(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $jobId = $input->getArgument('id');

        if (!$jobId) {
            $io->error('Job ID is required. Usage: iris schedule run <job-id>');
            return Command::FAILURE;
        }

        $io->text("Dispatching job #{$jobId} to queue...");

        $response = $iris->schedules->run($jobId);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success($response['message'] ?? 'Job dispatched successfully');
        $io->text("Job ID: " . ($response['job_id'] ?? $jobId));
        $io->text("Status: " . ($response['status'] ?? 'dispatched'));

        $io->note("The job is now running in the background. Use 'iris schedule history {$jobId}' to check results.");

        return Command::SUCCESS;
    }

    protected function jobHistory(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $jobId = $input->getArgument('id');

        if (!$jobId) {
            $io->error('Job ID is required. Usage: iris schedule history <job-id>');
            return Command::FAILURE;
        }

        $params = [
            'limit' => $input->getOption('limit'),
        ];

        if ($status = $input->getOption('status')) {
            $params['status'] = $status;
        }

        $response = $iris->schedules->executions($jobId, $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $executions = $response['data'] ?? [];
        $job = $response['job'] ?? [];

        $io->title("Execution History for Job #{$jobId}");

        if (!empty($job)) {
            $io->table(
                ['Property', 'Value'],
                [
                    ['Task Name', $job['task_name'] ?? '-'],
                    ['Status', $job['status'] ?? '-'],
                    ['Run Count', $job['run_count'] ?? 0],
                    ['Next Run', $job['next_run_at'] ?? '-'],
                ]
            );
        }

        if (empty($executions)) {
            $io->info('No executions found yet.');
            return Command::SUCCESS;
        }

        $table = new Table($io);
        $table->setHeaders(['Run #', 'Status', 'Started', 'Duration', 'Model', 'Tokens', 'Result Link']);

        foreach ($executions as $exec) {
            $duration = isset($exec['duration_seconds'])
                ? number_format($exec['duration_seconds'], 1) . 's'
                : '-';

            $table->addRow([
                $exec['run_number'] ?? '-',
                $this->formatStatus($exec['status'] ?? '-'),
                $exec['started_at'] ?? '-',
                $duration,
                $exec['model_used'] ?? '-',
                $exec['tokens_used'] ?? '-',
                $exec['public_url'] ? $this->shortenUrl($exec['public_url']) : '-',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    protected function agentHistory(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $agentId = $input->getArgument('id');

        if (!$agentId) {
            $io->error('Agent ID is required. Usage: iris schedule agent-history <agent-id>');
            return Command::FAILURE;
        }

        $params = [
            'limit' => $input->getOption('limit'),
        ];

        if ($status = $input->getOption('status')) {
            $params['status'] = $status;
        }

        $response = $iris->schedules->agentExecutions($agentId, $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $executions = $response['data'] ?? [];
        $stats = $response['stats'] ?? [];
        $agent = $response['agent'] ?? [];

        $io->title("Execution History for Agent: " . ($agent['name'] ?? "#{$agentId}"));

        if (!empty($stats)) {
            $io->section('Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Executions', $stats['total_executions'] ?? 0],
                    ['Successful', $stats['successful'] ?? 0],
                    ['Failed', $stats['failed'] ?? 0],
                    ['Total Tokens Used', number_format($stats['total_tokens_used'] ?? 0)],
                ]
            );
        }

        if (empty($executions)) {
            $io->info('No executions found yet.');
            return Command::SUCCESS;
        }

        $io->section('Recent Executions');

        $table = new Table($io);
        $table->setHeaders(['ID', 'Task', 'Run #', 'Status', 'Started', 'Tokens', 'Result Link']);

        foreach ($executions as $exec) {
            $table->addRow([
                $exec['id'] ?? '-',
                substr($exec['task_name'] ?? '-', 0, 25),
                $exec['run_number'] ?? '-',
                $this->formatStatus($exec['status'] ?? '-'),
                $exec['started_at'] ?? '-',
                $exec['tokens_used'] ?? '-',
                isset($exec['public_url']) ? $this->shortenUrl($exec['public_url']) : '-',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'completed' => '<fg=green>✓ completed</>',
            'failed' => '<fg=red>✗ failed</>',
            'running' => '<fg=yellow>⟳ running</>',
            'pending' => '<fg=gray>○ pending</>',
            default => $status,
        };
    }

    protected function shortenUrl(string $url): string
    {
        // Extract just the UUID part for display
        if (preg_match('/([a-f0-9-]{36})$/', $url, $matches)) {
            return '...' . substr($matches[1], 0, 8);
        }

        return strlen($url) > 40 ? substr($url, 0, 37) . '...' : $url;
    }

    /**
     * Show overall schedule status - great for debugging production.
     * If ID provided, show details for a specific job.
     */
    protected function showStatus(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $jobId = $input->getArgument('id');

        // If job ID provided, show specific job details
        if ($jobId) {
            return $this->showJobDetails($iris, $jobId, $input, $io);
        }

        $io->title('📅 Agent Schedule Status');

        // Get all jobs
        $response = $iris->schedules->list(['agent_jobs_only' => true]);
        $jobs = $response['data'] ?? $response ?? [];

        if ($input->getOption('json')) {
            $io->writeln(json_encode([
                'jobs' => $jobs,
                'summary' => $this->calculateJobStats($jobs),
            ], JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        if (empty($jobs)) {
            $io->warning('No scheduled jobs found.');
            return Command::SUCCESS;
        }

        // Calculate stats
        $stats = $this->calculateJobStats($jobs);

        // Summary stats
        $io->section('Overview');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Jobs', $stats['total']],
                ['<fg=green>Scheduled (Active)</>', $stats['scheduled']],
                ['<fg=yellow>Running</>', $stats['running']],
                ['<fg=gray>Completed</>', $stats['completed']],
                ['<fg=red>Failed</>', $stats['failed']],
            ]
        );

        // Next 5 jobs to run
        $io->section('⏰ Next Jobs to Run');
        $nextJobs = array_filter($jobs, fn($j) => ($j['status'] ?? '') === 'scheduled' && !empty($j['next_run_at']));
        usort($nextJobs, fn($a, $b) => ($a['next_run_at'] ?? '') <=> ($b['next_run_at'] ?? ''));
        $nextJobs = array_slice($nextJobs, 0, 5);

        if (empty($nextJobs)) {
            $io->text('<fg=gray>No jobs scheduled to run.</>');
        } else {
            $table = new Table($io);
            $table->setHeaders(['ID', 'Agent', 'Task', 'Next Run', 'Frequency']);
            foreach ($nextJobs as $job) {
                $table->addRow([
                    $job['id'],
                    $job['agent']['name'] ?? $job['agent_id'] ?? '-',
                    substr($job['task_name'] ?? '-', 0, 25),
                    $job['next_run_at'] ?? '-',
                    $job['frequency'] ?? '-',
                ]);
            }
            $table->render();
        }

        // Recently failed jobs
        $failedJobs = array_filter($jobs, fn($j) => ($j['status'] ?? '') === 'failed');
        if (!empty($failedJobs)) {
            $io->section('❌ Failed Jobs (Need Attention)');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Agent', 'Task', 'Last Run', 'Run Count']);
            foreach (array_slice($failedJobs, 0, 5) as $job) {
                $table->addRow([
                    $job['id'],
                    $job['agent']['name'] ?? $job['agent_id'] ?? '-',
                    substr($job['task_name'] ?? '-', 0, 25),
                    $job['last_run_at'] ?? '-',
                    $job['run_count'] ?? 0,
                ]);
            }
            $table->render();
        }

        // Running jobs
        $runningJobs = array_filter($jobs, fn($j) => ($j['status'] ?? '') === 'running');
        if (!empty($runningJobs)) {
            $io->section('🔄 Currently Running');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Agent', 'Task', 'Started']);
            foreach ($runningJobs as $job) {
                $table->addRow([
                    $job['id'],
                    $job['agent']['name'] ?? $job['agent_id'] ?? '-',
                    substr($job['task_name'] ?? '-', 0, 30),
                    $job['last_run_at'] ?? 'Unknown',
                ]);
            }
            $table->render();
        }

        return Command::SUCCESS;
    }

    protected function calculateJobStats(array $jobs): array
    {
        $stats = [
            'total' => count($jobs),
            'scheduled' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];

        foreach ($jobs as $job) {
            $status = $job['status'] ?? 'unknown';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * View all executions across all agents.
     */
    protected function allHistory(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $params = [
            'limit' => $input->getOption('limit'),
        ];

        if ($status = $input->getOption('status')) {
            $params['status'] = $status;
        }

        $response = $iris->schedules->allExecutions($params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        // Handle both wrapped {data, stats} and raw array formats
        $executions = $response['data'] ?? (is_array($response) && !isset($response['data']) ? $response : []);
        $stats = $response['stats'] ?? [];

        $io->title('📊 All Execution History');

        if (!empty($stats)) {
            $io->section('Statistics');
            $successRate = $stats['total_executions'] > 0
                ? round(($stats['successful'] / $stats['total_executions']) * 100, 1)
                : 0;

            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Executions', $stats['total_executions'] ?? 0],
                    ['Successful', '<fg=green>' . ($stats['successful'] ?? 0) . '</>'],
                    ['Failed', '<fg=red>' . ($stats['failed'] ?? 0) . '</>'],
                    ['Success Rate', "{$successRate}%"],
                    ['Total Tokens Used', number_format($stats['total_tokens_used'] ?? 0)],
                ]
            );
        }

        if (empty($executions)) {
            $io->info('No executions found.');
            return Command::SUCCESS;
        }

        $io->section('Recent Executions');

        $table = new Table($io);
        $table->setHeaders(['ID', 'Agent', 'Task', 'Status', 'Started', 'Duration', 'Tokens']);

        foreach ($executions as $exec) {
            $duration = isset($exec['duration_seconds'])
                ? number_format($exec['duration_seconds'], 1) . 's'
                : '-';

            $table->addRow([
                $exec['id'] ?? '-',
                substr($exec['agent_name'] ?? '-', 0, 15),
                substr($exec['task_name'] ?? '-', 0, 20),
                $this->formatStatus($exec['status'] ?? '-'),
                $exec['started_at'] ?? '-',
                $duration,
                $exec['tokens_used'] ?? '-',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    /**
     * Show detailed execution information - essential for debugging.
     */
    protected function showExecution(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $executionId = $input->getArgument('id');

        if (!$executionId) {
            $io->error('Execution ID is required. Usage: iris schedule execution <execution-id>');
            return Command::FAILURE;
        }

        $exec = $iris->schedules->execution($executionId);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($exec, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title("🔍 Execution #{$executionId}");

        // Basic info
        $io->section('Execution Info');
        $io->table(
            ['Property', 'Value'],
            [
                ['ID', $exec['id'] ?? '-'],
                ['Status', $this->formatStatus($exec['status'] ?? '-')],
                ['Task Name', $exec['task_name'] ?? '-'],
                ['Agent', ($exec['agent']['name'] ?? $exec['agent_name'] ?? '-') . ' (#' . ($exec['agent_id'] ?? '-') . ')'],
                ['Run Number', $exec['run_number'] ?? '-'],
                ['Started', $exec['started_at'] ?? '-'],
                ['Completed', $exec['completed_at'] ?? '-'],
                ['Duration', isset($exec['duration_seconds']) ? number_format($exec['duration_seconds'], 2) . 's' : '-'],
                ['Execution Source', $exec['execution_source'] ?? 'local'],
            ]
        );

        // Model & tokens
        $io->section('AI Details');
        $io->table(
            ['Property', 'Value'],
            [
                ['Model Used', $exec['model_used'] ?? '-'],
                ['Tokens Used', $exec['tokens_used'] ?? '-'],
                ['Prompt Tokens', $exec['prompt_tokens'] ?? '-'],
                ['Completion Tokens', $exec['completion_tokens'] ?? '-'],
            ]
        );

        // Prompt
        if (!empty($exec['prompt'])) {
            $io->section('📝 Prompt');
            $io->text($exec['prompt']);
        }

        // Response
        if (!empty($exec['response'])) {
            $io->section('💬 Response');
            $io->text($exec['response']);
        }

        // Error if failed
        if (!empty($exec['error'])) {
            $io->section('❌ Error');
            $io->error($exec['error']);
        }

        // Functions executed
        if (!empty($exec['functions_executed'])) {
            $io->section('🔧 Functions/Tools Executed');
            foreach ($exec['functions_executed'] as $func) {
                $name = is_array($func) ? ($func['name'] ?? json_encode($func)) : $func;
                $io->text("  • {$name}");
            }
        }

        // Public URL
        if (!empty($exec['public_url'])) {
            $io->section('🔗 Public URL');
            $io->text($exec['public_url']);
        }

        // Rating
        if (isset($exec['rating'])) {
            $io->section('⭐ Rating');
            $ratingIcon = $exec['rating'] === 'good' ? '👍' : ($exec['rating'] === 'bad' ? '👎' : '—');
            $io->text("Rating: {$ratingIcon} {$exec['rating']}");
            if (!empty($exec['rating_notes'])) {
                $io->text("Notes: {$exec['rating_notes']}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Sync agent recurring tasks from their configuration.
     */
    protected function syncAgent(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $agentId = $input->getArgument('id');

        if (!$agentId) {
            $io->error('Agent ID is required. Usage: iris schedule sync <agent-id>');
            return Command::FAILURE;
        }

        $io->text("Syncing recurring tasks for agent #{$agentId}...");

        $response = $iris->schedules->syncAgent($agentId);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success($response['message'] ?? 'Agent tasks synced successfully');

        $createdCount = $response['created_jobs'] ?? count($response['jobs'] ?? []);
        $io->text("Created/updated: {$createdCount} job(s)");

        if (!empty($response['jobs'])) {
            $table = new Table($io);
            $table->setHeaders(['ID', 'Task', 'Frequency', 'Next Run']);
            foreach ($response['jobs'] as $job) {
                $table->addRow([
                    $job['id'] ?? '-',
                    $job['task_name'] ?? '-',
                    $job['frequency'] ?? '-',
                    $job['next_run_at'] ?? '-',
                ]);
            }
            $table->render();
        }

        return Command::SUCCESS;
    }

    /**
     * Reset a stuck job back to scheduled status.
     */
    protected function resetJob(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $jobId = $input->getArgument('id');

        if (!$jobId) {
            $io->error('Job ID is required. Usage: iris schedule reset <job-id>');
            return Command::FAILURE;
        }

        $io->text("Resetting job #{$jobId}...");

        try {
            $response = $iris->schedules->reset($jobId);

            if ($input->getOption('json')) {
                $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if ($response['success'] ?? false) {
                $io->success($response['message'] ?? 'Job reset successfully');

                $job = $response['data'] ?? [];
                if (!empty($job)) {
                    $io->table(
                        ['Property', 'Value'],
                        [
                            ['ID', $job['id'] ?? '-'],
                            ['Task Name', $job['task_name'] ?? '-'],
                            ['Status', $job['status'] ?? '-'],
                            ['Next Run', $job['next_run_at'] ?? '-'],
                            ['Run Count', $job['run_count'] ?? 0],
                        ]
                    );
                }
            } else {
                $io->error($response['message'] ?? 'Failed to reset job');
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to reset job: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Reset all stuck jobs (running with next_run_at in past).
     */
    protected function resetAllJobs(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $io->text("Finding and resetting all stuck jobs...");

        try {
            $response = $iris->schedules->resetAll();

            if ($input->getOption('json')) {
                $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            $totalFound = $response['total_found'] ?? 0;
            $resetCount = $response['reset_count'] ?? 0;

            if ($totalFound === 0) {
                $io->success('No stuck jobs found! System is healthy.');
                return Command::SUCCESS;
            }

            $io->success($response['message'] ?? "Reset {$resetCount} job(s)");

            $io->section('Reset Results');
            $results = $response['results'] ?? [];

            if (!empty($results)) {
                $table = new Table($io);
                $table->setHeaders(['ID', 'Task Name', 'Result']);
                foreach ($results as $result) {
                    $status = $result['status'] === 'reset'
                        ? '<fg=green>✓ Reset</>'
                        : '<fg=red>✗ Failed</>';

                    $table->addRow([
                        $result['id'] ?? '-',
                        substr($result['task_name'] ?? '-', 0, 40),
                        $status,
                    ]);
                }
                $table->render();
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to reset jobs: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Show detailed information for a specific job.
     */
    protected function showJobDetails(IRIS $iris, int|string $jobId, InputInterface $input, SymfonyStyle $io): int
    {
        try {
            $job = $iris->schedules->get($jobId);

            if ($input->getOption('json')) {
                $io->writeln(json_encode($job, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            $io->title("📋 Job #{$jobId} Details");

            // Basic info
            $io->section('Job Information');
            $io->table(
                ['Property', 'Value'],
                [
                    ['ID', $job['id'] ?? '-'],
                    ['Task Name', $job['task_name'] ?? '-'],
                    ['Agent', ($job['agent']['name'] ?? '-') . ' (#' . ($job['agent_id'] ?? '-') . ')'],
                    ['Status', $this->formatStatus($job['status'] ?? '-')],
                    ['Frequency', $job['frequency'] ?? '-'],
                    ['Scheduled Time', $job['scheduled_time'] ?? '-'],
                    ['Timezone', $job['timezone'] ?? '-'],
                ]
            );

            // Execution stats
            $io->section('Execution Statistics');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Run Count', $job['run_count'] ?? 0],
                    ['Next Run', $job['next_run_at'] ?? '-'],
                    ['Last Run', $job['last_run_at'] ?? '-'],
                    ['Max Runs', $job['max_runs'] ?? 'Unlimited'],
                    ['Retry Count', $job['retry_count'] ?? 0],
                    ['Max Retries', $job['max_retries'] ?? 3],
                ]
            );

            // Prompt
            if (!empty($job['prompt'])) {
                $io->section('Prompt');
                $io->text($job['prompt']);
            }

            // Last result
            if (!empty($job['last_result'])) {
                $io->section('Last Result');
                $io->writeln(json_encode($job['last_result'], JSON_PRETTY_PRINT));
            }

            // Last error
            if (!empty($job['last_error'])) {
                $io->section('Last Error');
                $io->error($job['last_error']);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to get job details: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
