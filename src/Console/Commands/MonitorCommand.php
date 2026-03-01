<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;

/**
 * MonitorCommand — Platform health monitoring and heartbeat diagnostics
 *
 * Commands:
 *   iris monitor overview              - Platform-wide health dashboard
 *   iris monitor agent <agent_id>      - Deep-dive diagnostics for one agent
 *   iris monitor loops                 - Loop detection (duplicates, rapid-fire, stuck)
 *   iris monitor kill <agent_id>       - Emergency kill switch
 */
class MonitorCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('monitor')
            ->setDescription('Platform health monitoring and heartbeat diagnostics')
            ->setHelp('Commands: overview, agent, loops, kill')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: overview|agent|loops|kill')
            ->addArgument('id', InputArgument::OPTIONAL, 'Agent ID (for agent/kill actions)')
            ->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Time window in hours', 24)
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

            $iris = new IRIS($configOptions);

            switch ($action) {
                case 'overview':
                case 'status':
                case 'dashboard':
                    return $this->showOverview($iris, $input, $io);
                case 'agent':
                case 'inspect':
                    return $this->showAgent($iris, $input, $io);
                case 'loops':
                case 'detect':
                    return $this->showLoops($iris, $input, $io);
                case 'kill':
                case 'emergency':
                    return $this->killAgent($iris, $input, $io);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text('Available actions: overview, agent, loops, kill');

                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Platform-wide health dashboard.
     */
    private function showOverview(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $hours = (int) $input->getOption('hours');
        $response = $iris->monitor->overview($hours);
        $data = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title("Platform Monitor (last {$hours}h)");

        // Jobs by status
        $jobs = $data['jobs'] ?? [];
        $byStatus = $jobs['by_status'] ?? [];
        if (! empty($byStatus)) {
            $io->section('Jobs');
            $rows = [];
            foreach ($byStatus as $status => $count) {
                $rows[] = [$this->formatStatus($status), $count];
            }
            $rows[] = new TableSeparator();
            $rows[] = ['<options=bold>Total</>', $jobs['total'] ?? array_sum($byStatus)];
            $io->table(['Status', 'Count'], $rows);
        }

        // Heartbeat agents
        $heartbeatAgents = $data['heartbeat_agents'] ?? [];
        if (! empty($heartbeatAgents)) {
            $io->section('Heartbeat Agents');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Agent', 'Mode', 'Last Heartbeat']);
            foreach ($heartbeatAgents as $agent) {
                $lastHb = $agent['last_heartbeat_at'] ?? null;
                $table->addRow([
                    $agent['id'] ?? '-',
                    mb_substr($agent['name'] ?? '-', 0, 30),
                    $agent['heartbeat_mode'] ?? '-',
                    $lastHb ? mb_substr($lastHb, 0, 16) : '<fg=gray>never</>',
                ]);
            }
            $table->render();
        }

        // Top agents by token burn
        $topAgents = $data['top_agents'] ?? [];
        if (! empty($topAgents)) {
            $io->section('Top Agents (Token Burn)');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Agent', 'Executions', 'Completed', 'Failed', 'Tokens']);
            foreach (array_slice($topAgents, 0, 10) as $agent) {
                $table->addRow([
                    $agent['agent_id'] ?? '-',
                    mb_substr($agent['name'] ?? '-', 0, 25),
                    $agent['total_executions'] ?? 0,
                    '<fg=green>' . ($agent['completed'] ?? 0) . '</>',
                    ($agent['failed'] ?? 0) > 0
                        ? '<fg=red>' . $agent['failed'] . '</>'
                        : '0',
                    number_format($agent['total_tokens'] ?? 0),
                ]);
            }
            $table->render();
        }

        // Alerts
        $alerts = $data['alerts'] ?? [];
        if (! empty($alerts)) {
            $io->section('Alerts');
            foreach ($alerts as $alert) {
                $severity = $alert['severity'] ?? 'info';
                $icon = match ($severity) {
                    'critical' => '<fg=red>CRITICAL</>',
                    'warning' => '<fg=yellow>WARNING</>',
                    default => '<fg=gray>INFO</>',
                };
                $io->text("  {$icon} [{$alert['type']}] {$alert['message']} (agent #{$alert['agent_id']})");
            }
        } else {
            $io->text('<fg=green>No alerts.</>');
        }

        // Active now
        $activeNow = $data['active_now'] ?? [];
        $running = $activeNow['running_jobs'] ?? [];
        $dueSoon = $activeNow['due_within_30min'] ?? [];

        if (! empty($running)) {
            $io->section('Currently Running');
            $table = new Table($io);
            $table->setHeaders(['Job ID', 'Agent', 'Task']);
            foreach ($running as $job) {
                $table->addRow([
                    $job['id'] ?? '-',
                    mb_substr($job['agent_name'] ?? '-', 0, 25),
                    mb_substr($job['task_name'] ?? '-', 0, 25),
                ]);
            }
            $table->render();
        }

        if (! empty($dueSoon)) {
            $io->section('Due Within 30 Minutes');
            $table = new Table($io);
            $table->setHeaders(['Job ID', 'Agent', 'Next Run']);
            foreach ($dueSoon as $job) {
                $table->addRow([
                    $job['id'] ?? '-',
                    mb_substr($job['agent_name'] ?? '-', 0, 25),
                    $job['next_run_at'] ?? '-',
                ]);
            }
            $table->render();
        }

        return Command::SUCCESS;
    }

    /**
     * Deep-dive diagnostics for a specific agent.
     */
    private function showAgent(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $agentId = $input->getArgument('id');
        if (! $agentId) {
            $io->error('Agent ID required. Usage: iris monitor agent <agent_id>');

            return Command::FAILURE;
        }

        $hours = (int) $input->getOption('hours');
        if ($hours < 1) {
            $hours = 48;
        }
        $response = $iris->monitor->agent((int) $agentId, $hours);
        $data = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $agent = $data['agent'] ?? [];
        $io->title('Agent #' . $agentId . ': ' . ($agent['name'] ?? 'Unknown'));

        // Agent profile
        $io->section('Profile');
        $io->table(
            ['Property', 'Value'],
            [
                ['Heartbeat Mode', $agent['heartbeat_mode'] ?? '<fg=gray>off</>'],
                ['Last Heartbeat', $agent['last_heartbeat_at'] ?? '<fg=gray>never</>'],
            ]
        );

        // Stats summary
        $stats = $data['stats'] ?? [];
        if (! empty($stats)) {
            $io->section('Stats (last ' . $hours . 'h)');
            $failCount = $stats['failed'] ?? 0;
            $totalExec = $stats['total_executions'] ?? 0;
            $successRate = $totalExec > 0
                ? round((($stats['completed'] ?? 0) / $totalExec) * 100, 1) . '%'
                : '-';

            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total Executions', $totalExec],
                    ['Completed', '<fg=green>' . ($stats['completed'] ?? 0) . '</>'],
                    ['Failed', $failCount > 0 ? '<fg=red>' . $failCount . '</>' : '0'],
                    ['Success Rate', $successRate],
                    ['Total Tokens', number_format($stats['total_tokens'] ?? 0)],
                    ['Active Jobs', $stats['active_jobs'] ?? 0],
                ]
            );
        }

        // Rapid-fire detection
        $rapidFire = $data['rapid_fire'] ?? [];
        if (! empty($rapidFire) && ($rapidFire['rapid_fire_count'] ?? 0) > 0) {
            $io->section('Rapid-Fire Detection');
            $io->warning('Agent is executing faster than expected!');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Rapid-Fire Count', $rapidFire['rapid_fire_count']],
                    ['Avg Gap (seconds)', $rapidFire['avg_gap_seconds'] ?? '-'],
                    ['Min Gap (seconds)', $rapidFire['min_gap_seconds'] ?? '-'],
                ]
            );
        }

        // Scheduled jobs
        $jobs = $data['jobs'] ?? [];
        if (! empty($jobs)) {
            $io->section('Scheduled Jobs');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Task', 'Frequency', 'Status', 'Runs', 'Last Run', 'Next Run']);
            foreach (array_slice($jobs, 0, 10) as $job) {
                $table->addRow([
                    $job['id'] ?? '-',
                    mb_substr($job['task_name'] ?? '-', 0, 20),
                    $job['frequency'] ?? '-',
                    $this->formatStatus($job['status'] ?? '-'),
                    $job['run_count'] ?? 0,
                    $job['last_run_at'] ? mb_substr($job['last_run_at'], 0, 16) : '-',
                    $job['next_run_at'] ? mb_substr($job['next_run_at'], 0, 16) : '-',
                ]);
            }
            $table->render();
            if (count($jobs) > 10) {
                $io->text('<fg=gray>... and ' . (count($jobs) - 10) . ' more jobs</>');
            }
        }

        // Recent executions
        $executions = $data['executions'] ?? [];
        if (! empty($executions)) {
            $io->section('Recent Executions');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Status', 'Model', 'Tokens', 'Duration', 'Error', 'At']);
            foreach (array_slice($executions, 0, 15) as $exec) {
                $duration = isset($exec['duration_ms'])
                    ? number_format($exec['duration_ms'] / 1000, 1) . 's'
                    : '-';

                $error = $exec['error_message'] ?? null;
                $errorDisplay = $error ? '<fg=red>' . mb_substr($error, 0, 30) . '</>' : '-';

                $table->addRow([
                    $exec['id'] ?? '-',
                    $this->formatStatus($exec['status'] ?? '-'),
                    $exec['model_used'] ?? '-',
                    $exec['tokens_used'] ?? '-',
                    $duration,
                    $errorDisplay,
                    isset($exec['created_at']) ? mb_substr($exec['created_at'], 0, 16) : '-',
                ]);
            }
            $table->render();
            if (count($executions) > 15) {
                $io->text('<fg=gray>... and ' . (count($executions) - 15) . ' more executions</>');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Loop detection dashboard.
     */
    private function showLoops(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $hours = (int) $input->getOption('hours');
        $response = $iris->monitor->loops($hours);
        $data = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title("Loop Detection (last {$hours}h)");

        $hasIssues = false;

        // Duplicate tasks
        $duplicates = $data['duplicate_tasks'] ?? [];
        if (! empty($duplicates)) {
            $hasIssues = true;
            $io->section('Duplicate Tasks');
            $table = new Table($io);
            $table->setHeaders(['Agent ID', 'Agent', 'Task', 'Copies']);
            foreach ($duplicates as $dup) {
                $table->addRow([
                    $dup['agent_id'] ?? '-',
                    mb_substr($dup['agent_name'] ?? '-', 0, 25),
                    mb_substr($dup['task_name'] ?? '-', 0, 25),
                    '<fg=yellow>' . ($dup['copies'] ?? 0) . '</>',
                ]);
            }
            $table->render();
        }

        // High run count
        $highRuns = $data['high_run_count'] ?? [];
        if (! empty($highRuns)) {
            $hasIssues = true;
            $io->section('High Run Count (Possible Loops)');
            $table = new Table($io);
            $table->setHeaders(['Job ID', 'Agent', 'Task', 'Runs', 'Status', 'Last Run']);
            foreach ($highRuns as $job) {
                $table->addRow([
                    $job['id'] ?? '-',
                    mb_substr($job['agent_name'] ?? '-', 0, 20),
                    mb_substr($job['task_name'] ?? '-', 0, 20),
                    '<fg=red>' . ($job['run_count'] ?? 0) . '</>',
                    $this->formatStatus($job['status'] ?? '-'),
                    isset($job['last_run_at']) ? mb_substr($job['last_run_at'], 0, 16) : '-',
                ]);
            }
            $table->render();
        }

        // Rapid-fire agents
        $rapidFire = $data['rapid_fire_agents'] ?? [];
        if (! empty($rapidFire)) {
            $hasIssues = true;
            $io->section('Rapid-Fire Agents');
            $table = new Table($io);
            $table->setHeaders(['Agent ID', 'Agent', 'Executions', 'Rapid-Fire', 'Avg Gap (s)', 'Min Gap (s)']);
            foreach ($rapidFire as $agent) {
                $table->addRow([
                    $agent['agent_id'] ?? '-',
                    mb_substr($agent['agent_name'] ?? '-', 0, 25),
                    $agent['total_executions'] ?? 0,
                    '<fg=red>' . ($agent['rapid_fire_count'] ?? 0) . '</>',
                    $agent['avg_gap_seconds'] ?? '-',
                    $agent['min_gap_seconds'] ?? '-',
                ]);
            }
            $table->render();
        }

        // Stuck running jobs
        $stuck = $data['stuck_running'] ?? [];
        if (! empty($stuck)) {
            $hasIssues = true;
            $io->section('Stuck Running Jobs');
            $table = new Table($io);
            $table->setHeaders(['Job ID', 'Agent', 'Task', 'Last Run']);
            foreach ($stuck as $job) {
                $table->addRow([
                    $job['id'] ?? '-',
                    mb_substr($job['agent_name'] ?? '-', 0, 25),
                    mb_substr($job['task_name'] ?? '-', 0, 25),
                    isset($job['last_run_at']) ? mb_substr($job['last_run_at'], 0, 16) : '-',
                ]);
            }
            $table->render();
        }

        if (! $hasIssues) {
            $io->success('No loops or anomalies detected. System is healthy.');
        }

        return Command::SUCCESS;
    }

    /**
     * Emergency kill switch — disable heartbeat and pause all jobs.
     */
    private function killAgent(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $agentId = $input->getArgument('id');
        if (! $agentId) {
            $io->error('Agent ID required. Usage: iris monitor kill <agent_id>');

            return Command::FAILURE;
        }

        $agentId = (int) $agentId;

        if (! $io->confirm("EMERGENCY KILL: Disable heartbeat and pause ALL jobs for agent #{$agentId}?", false)) {
            $io->text('Cancelled.');

            return Command::SUCCESS;
        }

        $response = $iris->monitor->kill($agentId);
        $data = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->success("Agent #{$agentId} killed.");
        $io->table(
            ['Property', 'Value'],
            [
                ['Agent', $data['agent_name'] ?? "#{$agentId}"],
                ['Jobs Paused', $data['jobs_paused'] ?? 0],
                ['Tasks Cancelled', $data['tasks_cancelled'] ?? 0],
                ['Heartbeat Disabled', ($data['heartbeat_disabled'] ?? false) ? 'Yes' : 'No'],
            ]
        );

        $io->note('To re-enable, update the agent\'s heartbeat_mode in the dashboard.');

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'completed' => '<fg=green>completed</>',
            'failed' => '<fg=red>failed</>',
            'running' => '<fg=yellow>running</>',
            'scheduled' => '<fg=cyan>scheduled</>',
            'paused' => '<fg=gray>paused</>',
            'cancelled' => '<fg=gray>cancelled</>',
            default => $status,
        };
    }
}
