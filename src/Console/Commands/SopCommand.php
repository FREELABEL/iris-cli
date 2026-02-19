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
 * SopCommand - Manage SOPs (Standard Operating Procedures) for job opportunities
 *
 * Commands:
 *   iris sop list [request-id]                    - List SOPs for a request (or list requests with SOP counts)
 *   iris sop attach <request-id>                  - Attach a BloqItem as SOP
 *   iris sop update <sop-id> --request-id=X       - Update an SOP item
 *   iris sop detach <sop-id> --request-id=X       - Detach an SOP
 *   iris sop sync <request-id>                    - Sync/replace all SOPs
 *   iris sop types                                - List available SOP types
 */
class SopCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('sop')
            ->setDescription('Manage SOPs (Standard Operating Procedures) for job opportunities')
            ->setHelp(<<<'HELP'
Manage SOP/training materials attached to Custom Requests (job opportunities).

Commands:
  sop list                          List recent requests with SOP counts
  sop list <request-id>             List SOPs for a specific request
  sop attach <request-id>           Attach a BloqItem as SOP
  sop update <sop-id>               Update an SOP item
  sop detach <sop-id>               Remove an SOP
  sop sync <request-id>             Replace all SOPs with new set
  sop types                         List available SOP types

Examples:
  ./bin/iris sop list
  ./bin/iris sop list 359
  ./bin/iris sop attach 359 --bloq-item-id=123 --type=training --label="Day 1 Orientation"
  ./bin/iris sop update 1 --request-id=359 --type=onboarding --required
  ./bin/iris sop detach 1 --request-id=359
  ./bin/iris sop types

SOP Types:
  • sop         - Standard Operating Procedure
  • training    - Training Material
  • checklist   - Checklist
  • onboarding  - Onboarding Guide
  • resource    - Resource Document
HELP
            )
            ->addArgument('action', InputArgument::REQUIRED, 'Action: list|attach|update|detach|sync|types')
            ->addArgument('id', InputArgument::OPTIONAL, 'Request ID or SOP ID (depending on action)')
            ->addOption('request-id', 'r', InputOption::VALUE_REQUIRED, 'Request ID (for update/detach)')
            ->addOption('bloq-item-id', 'b', InputOption::VALUE_REQUIRED, 'BloqItem ID to attach')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'SOP type: sop|training|checklist|onboarding|resource', 'sop')
            ->addOption('label', 'l', InputOption::VALUE_REQUIRED, 'Custom label for the SOP')
            ->addOption('required', null, InputOption::VALUE_NONE, 'Mark SOP as required')
            ->addOption('sort-order', 's', InputOption::VALUE_REQUIRED, 'Sort order for display')
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
                    return $this->listSops($iris, $input, $io);

                case 'attach':
                    return $this->attachSop($iris, $input, $io);

                case 'update':
                    return $this->updateSop($iris, $input, $io);

                case 'detach':
                    return $this->detachSop($iris, $input, $io);

                case 'sync':
                    return $this->syncSops($iris, $input, $io);

                case 'types':
                    return $this->listTypes($io, $input);

                default:
                    $io->error("Unknown action: {$action}");
                    $io->text('Available actions: list, attach, update, detach, sync, types');
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * List SOPs for a request, or list requests with SOP counts
     */
    protected function listSops(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $requestId = $input->getArgument('id');

        if (!$requestId) {
            // List requests with SOP counts
            $io->title('Custom Requests with SOP Information');

            $response = $iris->getHttpClient()->get('/api/v1/services/requests/simplified', [
                'limit' => 20,
            ]);

            $requests = $response['data'] ?? [];

            if ($input->getOption('json')) {
                $io->writeln(json_encode($requests, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            if (empty($requests)) {
                $io->info('No requests found.');
                return Command::SUCCESS;
            }

            $table = new Table($io);
            $table->setHeaders(['ID', 'Title', 'Status', 'Created']);

            foreach ($requests as $request) {
                $table->addRow([
                    $request['id'] ?? '-',
                    substr($request['title'] ?? 'Untitled', 0, 45),
                    $request['status_text'] ?? '-',
                    isset($request['created_at']) ? substr($request['created_at'], 0, 10) : '-',
                ]);
            }

            $table->render();
            $io->newLine();
            $io->text('Use: ./bin/iris sop list <request-id> to see SOPs for a specific request');

            return Command::SUCCESS;
        }

        // List SOPs for specific request
        $io->title("SOPs for Request #{$requestId}");

        $response = $iris->getHttpClient()->get("/api/v1/services/requests/{$requestId}/sops");

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $response['data'] ?? $response;
        $sops = $data['sops'] ?? [];
        $counts = $data['counts'] ?? [];

        if (empty($sops)) {
            $io->warning('No SOPs attached to this request.');
            $io->text("Use: ./bin/iris sop attach {$requestId} --bloq-item-id=<ID> to add one");
            return Command::SUCCESS;
        }

        $table = new Table($io);
        $table->setHeaders(['SOP ID', 'Type', 'Label', 'BloqItem ID', 'BloqItem Title', 'Required', 'Order']);

        foreach ($sops as $sop) {
            $table->addRow([
                $sop['id'] ?? '-',
                $sop['type'] ?? '-',
                $sop['label'] ?? '-',
                $sop['bloq_item_id'] ?? '-',
                substr($sop['bloq_item']['title'] ?? 'Untitled', 0, 25),
                ($sop['is_required'] ?? false) ? '<fg=green>Yes</>' : '<fg=gray>No</>',
                $sop['sort_order'] ?? '-',
            ]);
        }

        $table->render();

        // Show counts
        $io->newLine();
        $io->section('Summary');
        $io->text("Total SOPs: " . ($counts['total'] ?? count($sops)));
        $io->text("Required: " . ($counts['required'] ?? 0));

        if (!empty($counts['by_type'])) {
            $io->newLine();
            $io->text('By Type:');
            foreach ($counts['by_type'] as $type => $count) {
                $io->text("  • {$type}: {$count}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Attach a BloqItem as SOP
     */
    protected function attachSop(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $requestId = $input->getArgument('id');
        $bloqItemId = $input->getOption('bloq-item-id');

        if (!$requestId) {
            $io->error('Request ID is required. Usage: iris sop attach <request-id> --bloq-item-id=X');
            return Command::FAILURE;
        }

        if (!$bloqItemId) {
            $io->error('--bloq-item-id is required');
            return Command::FAILURE;
        }

        $params = [
            'bloq_item_id' => (int) $bloqItemId,
            'type' => $input->getOption('type') ?: 'sop',
        ];

        if ($label = $input->getOption('label')) {
            $params['label'] = $label;
        }

        if ($input->getOption('required')) {
            $params['is_required'] = true;
        }

        if ($sortOrder = $input->getOption('sort-order')) {
            $params['sort_order'] = (int) $sortOrder;
        }

        $io->text("Attaching BloqItem #{$bloqItemId} as SOP to Request #{$requestId}...");

        $response = $iris->getHttpClient()->post("/api/v1/services/requests/{$requestId}/sops", $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $response['data'] ?? $response;

        if (isset($data['sop'])) {
            $sop = $data['sop'];
            $io->success('SOP attached successfully!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['SOP ID', $sop['id'] ?? '-'],
                    ['Type', $sop['type'] ?? '-'],
                    ['Label', $sop['label'] ?? $sop['bloq_item']['title'] ?? '-'],
                    ['BloqItem ID', $sop['bloq_item_id'] ?? '-'],
                    ['Required', ($sop['is_required'] ?? false) ? 'Yes' : 'No'],
                    ['Sort Order', $sop['sort_order'] ?? '-'],
                ]
            );
        } else {
            $io->success($data['message'] ?? 'SOP attached');
        }

        return Command::SUCCESS;
    }

    /**
     * Update an SOP item
     */
    protected function updateSop(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $sopId = $input->getArgument('id');
        $requestId = $input->getOption('request-id');

        if (!$sopId) {
            $io->error('SOP ID is required. Usage: iris sop update <sop-id> --request-id=X');
            return Command::FAILURE;
        }

        if (!$requestId) {
            $io->error('--request-id is required');
            return Command::FAILURE;
        }

        $params = [];

        if ($type = $input->getOption('type')) {
            $params['type'] = $type;
        }

        if ($label = $input->getOption('label')) {
            $params['label'] = $label;
        }

        if ($input->getOption('required')) {
            $params['is_required'] = true;
        }

        if ($sortOrder = $input->getOption('sort-order')) {
            $params['sort_order'] = (int) $sortOrder;
        }

        if (empty($params)) {
            $io->warning('No updates provided. Use --type, --label, --required, or --sort-order');
            return Command::FAILURE;
        }

        $io->text("Updating SOP #{$sopId}...");

        $response = $iris->getHttpClient()->put("/api/v1/services/requests/{$requestId}/sops/{$sopId}", $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $response['data'] ?? $response;
        $io->success($data['message'] ?? 'SOP updated successfully');

        if (isset($data['sop'])) {
            $sop = $data['sop'];
            $io->table(
                ['Property', 'Value'],
                [
                    ['SOP ID', $sop['id'] ?? '-'],
                    ['Type', $sop['type'] ?? '-'],
                    ['Label', $sop['label'] ?? '-'],
                    ['Required', ($sop['is_required'] ?? false) ? 'Yes' : 'No'],
                    ['Sort Order', $sop['sort_order'] ?? '-'],
                ]
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Detach an SOP
     */
    protected function detachSop(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $sopId = $input->getArgument('id');
        $requestId = $input->getOption('request-id');

        if (!$sopId) {
            $io->error('SOP ID is required. Usage: iris sop detach <sop-id> --request-id=X');
            return Command::FAILURE;
        }

        if (!$requestId) {
            $io->error('--request-id is required');
            return Command::FAILURE;
        }

        if (!$io->confirm("Are you sure you want to detach SOP #{$sopId}?", false)) {
            $io->text('Cancelled.');
            return Command::SUCCESS;
        }

        $io->text("Detaching SOP #{$sopId}...");

        $response = $iris->getHttpClient()->delete("/api/v1/services/requests/{$requestId}/sops/{$sopId}");

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $response['data'] ?? $response;
        $io->success($data['message'] ?? 'SOP detached successfully');

        return Command::SUCCESS;
    }

    /**
     * Sync (replace) all SOPs for a request
     */
    protected function syncSops(IRIS $iris, InputInterface $input, SymfonyStyle $io): int
    {
        $requestId = $input->getArgument('id');

        if (!$requestId) {
            $io->error('Request ID is required. Usage: iris sop sync <request-id>');
            return Command::FAILURE;
        }

        $io->title("Sync SOPs for Request #{$requestId}");
        $io->warning('This will REPLACE all existing SOPs with new ones.');

        if (!$io->confirm('Do you want to continue?', false)) {
            $io->text('Cancelled.');
            return Command::SUCCESS;
        }

        // Collect SOP data interactively
        $sops = [];
        $io->text('Enter BloqItem IDs to attach (empty to finish):');

        while (true) {
            $bloqItemId = $io->ask('BloqItem ID (or press Enter to finish)');

            if (empty($bloqItemId)) {
                break;
            }

            $type = $io->choice('SOP Type', ['sop', 'training', 'checklist', 'onboarding', 'resource'], 'sop');
            $label = $io->ask('Custom label (optional)');
            $required = $io->confirm('Is this required?', false);

            $sopData = [
                'bloq_item_id' => (int) $bloqItemId,
                'type' => $type,
                'is_required' => $required,
            ];

            if ($label) {
                $sopData['label'] = $label;
            }

            $sops[] = $sopData;
            $io->text("<fg=green>Added BloqItem #{$bloqItemId} as {$type}</>");
        }

        if (empty($sops)) {
            $io->warning('No SOPs added. Aborting sync.');
            return Command::SUCCESS;
        }

        if (!$io->confirm("Sync request with " . count($sops) . " SOPs?", true)) {
            $io->text('Cancelled.');
            return Command::SUCCESS;
        }

        $io->text('Syncing SOPs...');

        $response = $iris->getHttpClient()->post("/api/v1/services/requests/{$requestId}/sops/sync", [
            'sops' => $sops,
        ]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $response['data'] ?? $response;
        $io->success($data['message'] ?? 'SOPs synced successfully');
        $io->text("Total SOPs: " . count($data['sops'] ?? $sops));

        return Command::SUCCESS;
    }

    /**
     * List available SOP types
     */
    protected function listTypes(SymfonyStyle $io, InputInterface $input): int
    {
        $types = [
            ['sop', 'Standard Operating Procedure', 'Step-by-step operational guides'],
            ['training', 'Training Material', 'Educational content for skill development'],
            ['checklist', 'Checklist', 'Task completion checklists'],
            ['onboarding', 'Onboarding Guide', 'New hire orientation materials'],
            ['resource', 'Resource Document', 'Reference materials and documentation'],
        ];

        if ($input->getOption('json')) {
            $io->writeln(json_encode(array_map(fn($t) => [
                'type' => $t[0],
                'label' => $t[1],
                'description' => $t[2],
            ], $types), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title('Available SOP Types');

        $table = new Table($io);
        $table->setHeaders(['Type', 'Label', 'Description']);

        foreach ($types as $type) {
            $table->addRow($type);
        }

        $table->render();

        $io->newLine();
        $io->text('Use --type=<type> when attaching SOPs');
        $io->text('Example: ./bin/iris sop attach 359 --bloq-item-id=123 --type=training');

        return Command::SUCCESS;
    }
}
