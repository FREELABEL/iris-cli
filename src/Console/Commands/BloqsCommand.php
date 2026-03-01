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

/**
 * BloqsCommand — Manage bloqs/projects/boards
 *
 * Commands:
 *   iris bloqs list                        - List all bloqs
 *   iris bloqs search "keyword"            - Search bloqs by title
 *   iris bloqs show <id>                   - Show bloq details
 *   iris bloqs overview                    - Summary counts
 *   iris bloqs archive <id>               - Soft-delete a bloq
 *   iris bloqs merge <source_id> <target>  - Merge source into target
 *
 * Aliases: iris projects ..., iris boards ...
 */
class BloqsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('bloqs')
            ->setAliases(['projects', 'boards'])
            ->setDescription('Manage bloqs, projects, and boards')
            ->setHelp('Commands: list, search, show, overview, archive, merge')
            ->addArgument('action', InputArgument::REQUIRED, 'Action: list|search|show|overview|archive|merge')
            ->addArgument('id', InputArgument::OPTIONAL, 'Bloq ID (for show/archive) or search query (for search) or source ID (for merge)')
            ->addArgument('extra', InputArgument::OPTIONAL, 'Target bloq ID (for merge)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max results', '50')
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
                case 'list':
                case 'ls':
                    return $this->listBloqs($iris, $input, $io);
                case 'search':
                case 'find':
                    return $this->searchBloqs($iris, $input, $io);
                case 'show':
                case 'view':
                case 'get':
                    return $this->showBloq($iris, $input, $io);
                case 'overview':
                case 'stats':
                    return $this->showOverview($iris, $input, $io);
                case 'archive':
                case 'delete':
                case 'rm':
                    return $this->archiveBloq($iris, $input, $io);
                case 'merge':
                    return $this->mergeBloqs($iris, $input, $io);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text('Available actions: list, search, show, overview, archive, merge');

                    return Command::FAILURE;
            }
        } catch (\IRIS\SDK\Exceptions\AuthenticationException $e) {
            $io->error('Authentication failed. Run "iris config" to check your API key.');

            return Command::FAILURE;
        } catch (\IRIS\SDK\Exceptions\IRISException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * List all bloqs.
     */
    private function listBloqs(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $options = [
            'per_page' => (int) $input->getOption('limit'),
        ];

        $collection = $iris->bloqs->list($options);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($collection->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if ($collection->isEmpty()) {
            $io->info('No bloqs found. Create one with: iris sdk:call bloqs.create title="My Project"');

            return Command::SUCCESS;
        }

        $io->title('Bloqs');
        $this->renderBloqTable($io, $collection->all());
        $io->text($collection->count() . ' bloqs found.');

        return Command::SUCCESS;
    }

    /**
     * Search bloqs by keyword.
     */
    private function searchBloqs(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $query = $input->getArgument('id');

        if (! $query) {
            $io->error('Search query required. Usage: iris bloqs search "keyword"');

            return Command::FAILURE;
        }

        $options = [
            'search' => $query,
            'per_page' => (int) $input->getOption('limit'),
        ];

        $collection = $iris->bloqs->list($options);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($collection->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if ($collection->isEmpty()) {
            $io->info("No bloqs matching \"{$query}\". Try a different search term.");

            return Command::SUCCESS;
        }

        $io->title("Search: \"{$query}\"");
        $this->renderBloqTable($io, $collection->all());
        $io->text($collection->count() . ' results.');

        return Command::SUCCESS;
    }

    /**
     * Show detailed bloq view.
     *
     * Note: BloqController::show() is a stub, so we fetch from list()
     * and filter client-side to find the matching bloq by ID.
     */
    private function showBloq(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $id = $input->getArgument('id');

        if (! $id || ! is_numeric($id)) {
            $io->error('Bloq ID required. Usage: iris bloqs show <id>');
            $io->text('Run "iris bloqs list" to see available bloqs.');

            return Command::FAILURE;
        }

        $bloqId = (int) $id;

        // list() returns full nested data; find matching bloq
        $collection = $iris->bloqs->list(['per_page' => 100]);
        $bloq = null;

        foreach ($collection as $b) {
            if ($b->id === $bloqId) {
                $bloq = $b;
                break;
            }
        }

        if (! $bloq) {
            $io->error("Bloq #{$bloqId} not found.");
            $io->text('Run "iris bloqs list" to see available bloqs.');

            return Command::FAILURE;
        }

        if ($input->getOption('json')) {
            $io->writeln(json_encode($bloq->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title($bloq->title);

        if ($bloq->description) {
            $io->text($bloq->description);
            $io->newLine();
        }

        $io->definitionList(
            ['ID' => $bloq->id],
            ['Lists' => $bloq->listCount],
            ['Items' => $bloq->itemCount],
            ['Pinned' => $bloq->isPinned ? 'Yes' : 'No'],
            ['Created' => $bloq->createdAt ?? '-'],
            ['Updated' => $bloq->updatedAt ?? '-'],
        );

        // Show lists from nested data
        if ($bloq->lists && count($bloq->lists) > 0) {
            $io->section('Lists');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Title', 'Items']);

            foreach ($bloq->lists as $list) {
                $listName = $list['title'] ?? $list['name'] ?? '-';
                $itemCount = $list['item_count'] ?? (isset($list['items']) && is_array($list['items']) ? count($list['items']) : 0);

                $table->addRow([
                    $list['id'] ?? '-',
                    $listName,
                    $itemCount,
                ]);
            }

            $table->render();
        } else {
            // Try fetching lists separately
            try {
                $lists = $iris->bloqs->lists($bloqId)->all();

                if ($lists->count() > 0) {
                    $io->section('Lists');
                    $table = new Table($io);
                    $table->setHeaders(['ID', 'Title', 'Items']);

                    foreach ($lists as $list) {
                        $table->addRow([
                            $list->id,
                            $list->title,
                            $list->itemCount,
                        ]);
                    }

                    $table->render();
                }
            } catch (\Exception $e) {
                // Lists unavailable — not critical
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Show overview/summary.
     */
    private function showOverview(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $response = $iris->bloqs->overview();

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io->title('Bloqs Overview');

        $stats = $response['stats'] ?? $response;

        if (isset($stats['total_bloqs'])) {
            $io->definitionList(
                ['Total Bloqs' => $stats['total_bloqs'] ?? 0],
                ['Total Lists' => $stats['total_lists'] ?? 0],
                ['Total Items' => $stats['total_items'] ?? 0],
            );
        }

        $bloqs = $response['bloqs'] ?? $response['data'] ?? [];

        if (! empty($bloqs)) {
            $io->section('All Bloqs');
            $table = new Table($io);
            $table->setHeaders(['ID', 'Title', 'Lists', 'Items', 'Updated']);

            foreach ($bloqs as $b) {
                $table->addRow([
                    $b['id'] ?? '-',
                    mb_substr($b['title'] ?? '', 0, 40),
                    $b['list_count'] ?? $b['lists_count'] ?? 0,
                    $b['item_count'] ?? $b['items_count'] ?? 0,
                    isset($b['updated_at']) ? mb_substr($b['updated_at'], 0, 10) : '-',
                ]);
            }

            $table->render();
        }

        return Command::SUCCESS;
    }

    /**
     * Archive (soft-delete) a bloq.
     */
    private function archiveBloq(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $id = $input->getArgument('id');

        if (! $id || ! is_numeric($id)) {
            $io->error('Bloq ID required. Usage: iris bloqs archive <id>');
            $io->text('Run "iris bloqs list" to see available bloqs.');

            return Command::FAILURE;
        }

        $bloqId = (int) $id;
        $bloq = $this->findBloqById($iris, $bloqId);

        if (! $bloq) {
            $io->error("Bloq #{$bloqId} not found.");
            $io->text('Run "iris bloqs list" to see available bloqs.');

            return Command::FAILURE;
        }

        $io->warning("This will archive \"{$bloq->title}\" (#{$bloq->id}) and all its lists/items.");

        if (! $io->confirm('Continue?', false)) {
            $io->text('Cancelled.');

            return Command::SUCCESS;
        }

        $iris->bloqs->delete($bloqId);
        $io->success("Archived \"{$bloq->title}\". Data preserved — contact admin to restore.");

        return Command::SUCCESS;
    }

    /**
     * Merge all lists/items from source bloq into target bloq.
     */
    private function mergeBloqs(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $sourceId = $input->getArgument('id');
        $targetId = $input->getArgument('extra');

        if (! $sourceId || ! is_numeric($sourceId)) {
            $io->error('Source bloq ID required. Usage: iris bloqs merge <source_id> <target_id>');

            return Command::FAILURE;
        }

        if (! $targetId || ! is_numeric($targetId)) {
            $io->error('Target bloq ID required. Usage: iris bloqs merge <source_id> <target_id>');

            return Command::FAILURE;
        }

        $sourceId = (int) $sourceId;
        $targetId = (int) $targetId;

        if ($sourceId === $targetId) {
            $io->error('Source and target must be different bloqs.');

            return Command::FAILURE;
        }

        // Fetch both bloqs via list (show endpoint is a stub)
        $source = $this->findBloqById($iris, $sourceId);
        if (! $source) {
            $io->error("Source bloq #{$sourceId} not found.");

            return Command::FAILURE;
        }

        $target = $this->findBloqById($iris, $targetId);
        if (! $target) {
            $io->error("Target bloq #{$targetId} not found.");

            return Command::FAILURE;
        }

        $io->title("Merge \"{$source->title}\" into \"{$target->title}\"");
        $io->warning("This copies all lists/items from #{$sourceId} into #{$targetId}, then archives #{$sourceId}.");

        if (! $io->confirm('Continue?', false)) {
            $io->text('Cancelled.');

            return Command::SUCCESS;
        }

        // Get source lists
        $lists = $iris->bloqs->lists($sourceId)->all();
        $totalLists = 0;
        $totalItems = 0;

        foreach ($lists as $list) {
            // Get items from source list
            $items = $iris->bloqs->items($list->id)->list();

            // Create matching list in target
            $newList = $iris->bloqs->lists($targetId)->create([
                'title' => $list->title,
                'type' => $list->type ?? 'default',
            ]);

            $itemCount = 0;
            foreach ($items as $item) {
                $data = [
                    'title' => $item->title,
                ];

                if ($item->content) {
                    $data['content'] = $item->content;
                }

                if ($item->type) {
                    $data['type'] = $item->type;
                }

                $iris->bloqs->items($newList->id)->create($data);
                $itemCount++;
            }

            $totalItems += $itemCount;
            $totalLists++;
            $io->text("  Merged list \"{$list->title}\" ({$itemCount} items)");
        }

        // Archive source
        $iris->bloqs->delete($sourceId);

        $io->newLine();
        $io->success("Merged {$totalLists} lists, {$totalItems} items into \"{$target->title}\". Source archived.");

        return Command::SUCCESS;
    }

    /**
     * Find a bloq by ID from the list endpoint.
     */
    private function findBloqById(IRIS $iris, int $bloqId): ?\IRIS\SDK\Resources\Bloqs\Bloq
    {
        $collection = $iris->bloqs->list(['per_page' => 100]);

        foreach ($collection as $b) {
            if ($b->id === $bloqId) {
                return $b;
            }
        }

        return null;
    }

    /**
     * Render a table of bloqs.
     */
    private function renderBloqTable(SymfonyStyle $io, array $bloqs): void
    {
        $table = new Table($io);
        $table->setHeaders(['ID', 'Title', 'Lists', 'Items', 'Pinned', 'Updated']);

        foreach ($bloqs as $bloq) {
            $table->addRow([
                $bloq->id,
                mb_substr($bloq->title, 0, 45),
                $bloq->listCount,
                $bloq->itemCount,
                $bloq->isPinned ? 'Yes' : '-',
                $bloq->updatedAt ? mb_substr($bloq->updatedAt, 0, 10) : '-',
            ]);
        }

        $table->render();
    }
}
