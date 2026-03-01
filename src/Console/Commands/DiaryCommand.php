<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * DiaryCommand — View and manage agent daily diary entries
 *
 * Commands:
 *   iris diary today <agent_id>                  - View today's unified diary
 *   iris diary today --bloq=217                  - View by bloq ID
 *   iris diary list <agent_id> [--days=14]       - List recent entries
 *   iris diary view <agent_id> <date>            - View a specific day
 *   iris diary add <agent_id> "content"          - Append manual entry
 */
class DiaryCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('diary')
            ->setDescription('View and manage agent daily diary entries')
            ->setHelp('Commands: today, list, view, add')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: today|list|view|add')
            ->addArgument('id', InputArgument::OPTIONAL, 'Agent ID')
            ->addArgument('extra', InputArgument::OPTIONAL, 'Date (for view) or content (for add)')
            ->addOption('bloq', 'b', InputOption::VALUE_REQUIRED, 'Bloq ID (alternative to agent ID)')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Number of days for list', '14')
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
                case 'today':
                    return $this->showToday($iris, $input, $io);
                case 'list':
                    return $this->listRecent($iris, $input, $io);
                case 'view':
                    return $this->viewDate($iris, $input, $io);
                case 'add':
                    return $this->addEntry($iris, $input, $io);
                default:
                    $io->error("Unknown action: {$action}. Use: today|list|view|add");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Show today's unified diary.
     */
    private function showToday(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        [$agentId, $options] = $this->resolveIdAndOptions($input);

        if (! $agentId && empty($options['bloq_id'])) {
            $io->error('Agent ID required. Usage: iris diary today <agent_id>');

            return Command::FAILURE;
        }

        $response = $iris->diary->today($agentId ?? 0, $options);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->renderDayView($io, $response);

        return Command::SUCCESS;
    }

    /**
     * List recent diary entries.
     */
    private function listRecent(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        [$agentId, $options] = $this->resolveIdAndOptions($input);

        if (! $agentId && empty($options['bloq_id'])) {
            $io->error('Agent ID required. Usage: iris diary list <agent_id>');

            return Command::FAILURE;
        }

        $options['days'] = (int) $input->getOption('days');
        $response = $iris->diary->list($agentId ?? 0, $options);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $agentName = $response['agent_name'] ?? 'Agent';
        $bloqName = $response['bloq_name'] ?? 'Unknown';
        $days = $response['days'] ?? 14;

        $io->title("Daily Diary — {$agentName} ({$bloqName})");
        $io->text("Last {$days} days | {$response['total_entries']} entries");
        $io->newLine();

        $entries = $response['entries'] ?? [];

        if (empty($entries)) {
            $io->info('No diary entries found.');

            return Command::SUCCESS;
        }

        $table = new Table($io);
        $table->setHeaders(['Date', 'Diary', 'Heartbeats', 'Summary']);

        foreach ($entries as $entry) {
            $diaryIcon = $entry['has_diary'] ? "yes ({$entry['diary_sections']})" : '-';
            $heartbeatIcon = $entry['has_heartbeats'] ? (string) $entry['heartbeat_count'] : '-';
            $summary = mb_substr($entry['summary'] ?? '', 0, 60);

            $table->addRow([
                $entry['date'],
                $diaryIcon,
                $heartbeatIcon,
                $summary,
            ]);
        }

        $table->render();

        $io->newLine();
        $io->text('Use <info>iris diary view <agent_id> <date></info> to see a specific day.');

        return Command::SUCCESS;
    }

    /**
     * View a specific day's diary.
     */
    private function viewDate(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        [$agentId, $options] = $this->resolveIdAndOptions($input);
        $date = $input->getArgument('extra');

        if (! $date) {
            $io->error('Date required. Usage: iris diary view <agent_id> <YYYY-MM-DD>');

            return Command::FAILURE;
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $io->error("Invalid date format: {$date}. Use YYYY-MM-DD.");

            return Command::FAILURE;
        }

        if (! $agentId && empty($options['bloq_id'])) {
            $io->error('Agent ID required. Usage: iris diary view <agent_id> <date>');

            return Command::FAILURE;
        }

        $response = $iris->diary->show($agentId ?? 0, $date, $options);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->renderDayView($io, $response);

        return Command::SUCCESS;
    }

    /**
     * Add a manual diary entry.
     */
    private function addEntry(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        [$agentId, $options] = $this->resolveIdAndOptions($input);
        $content = $input->getArgument('extra');

        // When --bloq is set, the content may land in the 'id' position
        // e.g. iris diary add --bloq=217 "My content"
        // Symfony maps: id="My content", extra=null
        if (! $content && ! empty($options['bloq_id'])) {
            $rawId = $input->getArgument('id');
            if ($rawId && ! is_numeric($rawId)) {
                $content = $rawId;
                $agentId = null;
            }
        }

        if (! $content) {
            $io->error('Content required.');
            $io->text('Usage: iris diary add <agent_id> "Your diary entry"');
            $io->text('   or: iris diary add --bloq=<id> "Your diary entry"');

            return Command::FAILURE;
        }

        if (! $agentId && empty($options['bloq_id'])) {
            $io->error('Agent ID or --bloq required.');
            $io->text('Usage: iris diary add <agent_id> "content"');
            $io->text('   or: iris diary add --bloq=<id> "content"');

            return Command::FAILURE;
        }

        $response = $iris->diary->add($agentId ?? 0, $content, $options);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if ($response['success'] ?? false) {
            $io->success("Diary entry added at {$response['time']} on {$response['date']}");
            $io->text("Content: {$content}");
        } else {
            $io->error('Failed to add diary entry.');
            if (isset($response['error'])) {
                $io->text("Reason: {$response['error']}");
                if (isset($response['agent_id'])) {
                    $io->text("Agent ID {$response['agent_id']} was not found. Try using --bloq=<id> instead.");
                }
            } else {
                $io->text(json_encode($response, JSON_PRETTY_PRINT));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Render a day's unified diary view.
     */
    private function renderDayView(SymfonyStyle $io, array $response): void
    {
        $date = $response['date'] ?? 'Unknown';
        $agentName = $response['agent_name'] ?? 'Agent';
        $bloqName = $response['bloq_name'] ?? 'Unknown';

        $io->title("Diary — {$date} — {$agentName} ({$bloqName})");

        $timeline = $response['timeline'] ?? [];

        if (empty($timeline)) {
            $io->info('No activity recorded for this day.');

            return;
        }

        foreach ($timeline as $entry) {
            $sourceTag = $entry['source'] === 'diary' ? '<fg=cyan>[diary]</>' : '<fg=yellow>[heartbeat]</>';
            $time = $entry['time'] ?? '';
            $agent = $entry['agent_name'] ?? '';

            $io->writeln("{$sourceTag} <info>{$time}</info> — <comment>{$agent}</comment>");
            $io->writeln($entry['content'] ?? '');
            $io->newLine();
        }

        $diaryCount = count(array_filter($timeline, fn ($e) => $e['source'] === 'diary'));
        $heartbeatCount = count(array_filter($timeline, fn ($e) => $e['source'] === 'heartbeat'));

        $io->text("<fg=gray>{$diaryCount} diary entries | {$heartbeatCount} heartbeat summaries</>");
    }

    /**
     * Resolve agent ID and build options array from input.
     *
     * @return array{0: int|null, 1: array}
     */
    private function resolveIdAndOptions(InputInterface $input): array
    {
        $agentId = $input->getArgument('id') ? (int) $input->getArgument('id') : null;
        $options = [];

        if ($bloqId = $input->getOption('bloq')) {
            $options['bloq_id'] = (int) $bloqId;
        }

        return [$agentId, $options];
    }
}
