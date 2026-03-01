<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * CLI command for managing outreach strategies (templates).
 *
 * Usage:
 *   ./bin/iris outreach:strategy list <bloq_id>                         # List all strategies
 *   ./bin/iris outreach:strategy show <bloq_id> <id>                    # Show strategy + steps
 *   ./bin/iris outreach:strategy create <bloq_id>                       # Interactive creation
 *   ./bin/iris outreach:strategy apply <bloq_id> <id> <lead_id>         # Apply to a lead
 *   ./bin/iris outreach:strategy duplicate <bloq_id> <id>               # Clone strategy
 *   ./bin/iris outreach:strategy delete <bloq_id> <id>                  # Delete strategy
 *   ./bin/iris outreach:strategy metadata <bloq_id>                     # Show categories/icons/fields
 */
class OutreachCommand extends Command
{
    private const BASE_PATH = '/api/v1/bloqs';

    private const CATEGORIES = [
        'cold_outreach' => 'Cold Outreach',
        'follow_up'     => 'Follow Up',
        'nurture'       => 'Nurture',
        'high_value'    => 'High Value Leads',
        'quick_touch'   => 'Quick Touch',
        'custom'        => 'Custom',
    ];

    private const ICONS = [
        'fas fa-rocket'      => 'Rocket',
        'fas fa-paper-plane'  => 'Paper Plane',
        'fas fa-bullseye'    => 'Target',
        'fas fa-fire'        => 'Fire',
        'fas fa-bolt'        => 'Lightning',
        'fas fa-star'        => 'Star',
        'fas fa-handshake'   => 'Handshake',
        'fab fa-instagram'   => 'Instagram',
    ];

    private const CHANNELS = [
        'instagram' => 'Instagram DM',
        'email'     => 'Email',
        'sms'       => 'SMS',
        'phone'     => 'Phone Call',
        'linkedin'  => 'LinkedIn',
        'visit'     => 'Physical Visit',
        'other'     => 'Other',
    ];

    private const MERGE_FIELDS = [
        '{name}'          => 'Full name of the lead',
        '{first_name}'    => 'First name of the lead',
        '{company}'       => 'Company name',
        '{email}'         => 'Email address',
        '{phone}'         => 'Phone number',
        '{social_handle}' => 'Social media handle',
        '{instagram}'     => 'Instagram handle',
        '{price_bid}'     => 'Price/bid amount',
        '{notes}'         => 'Lead notes',
    ];

    protected function configure(): void
    {
        $this
            ->setName('outreach:strategy')
            ->setDescription('Manage outreach strategy templates for bloqs')
            ->setHelp(<<<'HELP'
Manage multi-step outreach strategy templates attached to bloqs.

Usage:
  outreach:strategy list <bloq_id>                           List all strategies
  outreach:strategy show <bloq_id> <id>                      Show strategy details + steps
  outreach:strategy create <bloq_id>                         Interactive strategy builder
  outreach:strategy apply <bloq_id> <id> <lead_id>           Apply strategy to a lead
  outreach:strategy duplicate <bloq_id> <id>                 Duplicate a strategy
  outreach:strategy delete <bloq_id> <id>                    Delete a strategy
  outreach:strategy metadata <bloq_id>                       Show categories, icons, merge fields

Examples:
  outreach:strategy list 40
  outreach:strategy list 40 --category=cold_outreach
  outreach:strategy show 40 5
  outreach:strategy create 40 --name="Instagram-First Outreach" --category=cold_outreach
  outreach:strategy apply 40 5 412
  outreach:strategy apply 40 5 412 --clear-existing
  outreach:strategy duplicate 40 5 --new-name="Instagram V2"
  outreach:strategy delete 40 5
  outreach:strategy metadata 40

Merge Fields (available in step scripts):
  {name}           Full name of the lead
  {first_name}     First name
  {company}        Company name
  {email}          Email address
  {phone}          Phone number
  {instagram}      Instagram handle

Environment:
  outreach:strategy list 40 --env=production                 Target production API
  outreach:strategy list 40 --env=local                      Target local API

Related Commands:
  outreach:campaign list <bloq_id>                           Manage campaigns (coming soon)
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list|show|create|apply|duplicate|delete|metadata', 'list')
            ->addArgument('bloq_id', InputArgument::OPTIONAL, 'Bloq ID')
            ->addArgument('id', InputArgument::OPTIONAL, 'Strategy template ID')
            ->addArgument('lead_id', InputArgument::OPTIONAL, 'Lead ID (for apply)')
            // Common options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment: local or production')
            // Create/filter options
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Strategy name')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'Strategy description')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Category: cold_outreach|follow_up|nurture|high_value|quick_touch|custom')
            ->addOption('icon', null, InputOption::VALUE_REQUIRED, 'Icon (Font Awesome class)')
            // Duplicate option
            ->addOption('new-name', null, InputOption::VALUE_REQUIRED, 'New name for duplication')
            // Apply option
            ->addOption('clear-existing', null, InputOption::VALUE_NONE, 'Clear existing lead steps when applying');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');
        $bloqId = $input->getArgument('bloq_id');

        // Handle --env flag
        $env = $input->getOption('env');
        if ($env) {
            putenv("IRIS_ENV={$env}");
            $_ENV['IRIS_ENV'] = $env;
        }

        // Validate bloq_id for all actions
        if (!$bloqId) {
            $io->error('Bloq ID is required. Usage: outreach:strategy <action> <bloq_id>');
            return Command::FAILURE;
        }

        // Get API credentials
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID');

        if (!$apiKey || !$userId) {
            try {
                $tempConfig = new Config([]);
                if (!$apiKey && isset($tempConfig->apiKey)) {
                    $apiKey = $tempConfig->apiKey;
                }
                if (!$userId && isset($tempConfig->userId)) {
                    $userId = $tempConfig->userId;
                }
            } catch (\Exception $e) {
                // Config will throw if api_key not found
            }
        }

        if (!$apiKey || !$userId) {
            $io->error([
                'Missing API credentials.',
                '',
                'Set environment variables or run: php bin/iris setup',
                '  IRIS_API_KEY=your-api-key',
                '  IRIS_USER_ID=your-user-id',
            ]);
            return Command::FAILURE;
        }

        $iris = new IRIS([
            'api_key' => $apiKey,
            'user_id' => (int) $userId,
        ]);

        try {
            switch ($action) {
                case 'list':
                    return $this->listStrategies($iris, $io, $input, (int) $bloqId);

                case 'show':
                    return $this->showStrategy($iris, $io, $input, (int) $bloqId);

                case 'create':
                    return $this->createStrategy($iris, $io, $input, (int) $bloqId);

                case 'apply':
                    return $this->applyStrategy($iris, $io, $input, (int) $bloqId);

                case 'duplicate':
                    return $this->duplicateStrategy($iris, $io, $input, (int) $bloqId);

                case 'delete':
                    return $this->deleteStrategy($iris, $io, $input, (int) $bloqId);

                case 'metadata':
                    return $this->showMetadata($iris, $io, $input, (int) $bloqId);

                default:
                    $io->error("Unknown action: {$action}. Use: list, show, create, apply, duplicate, delete, metadata");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    // ─── List ────────────────────────────────────────────────────────────

    private function listStrategies(IRIS $iris, SymfonyStyle $io, InputInterface $input, int $bloqId): int
    {
        $http = $iris->getHttpClient();
        $query = [];

        $category = $input->getOption('category');
        if ($category) {
            $query['category'] = $category;
        }

        $response = $http->get(self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates", $query);
        $templates = $response['data']['templates'] ?? $response['data'] ?? [];

        if ($input->getOption('json')) {
            $io->writeln(json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if (empty($templates)) {
            $io->info("No strategies found for bloq #{$bloqId}.");
            $io->note("Create one: php bin/iris outreach:strategy create {$bloqId}");
            return Command::SUCCESS;
        }

        $env = $input->getOption('env') ?: (getenv('IRIS_ENV') ?: 'production');
        $io->title("Outreach Strategies [Bloq #{$bloqId}] [{$env}]");

        $rows = [];
        foreach ($templates as $template) {
            $stepCount = $template['step_count'] ?? count($template['steps'] ?? []);
            $categoryLabel = self::CATEGORIES[$template['category'] ?? ''] ?? ($template['category'] ?? '-');
            $rows[] = [
                $template['id'],
                $template['short_code'] ?? '-',
                $template['name'],
                $categoryLabel,
                $stepCount,
                ($template['is_default'] ?? false) ? '<fg=green>Yes</>' : 'No',
                $template['usage_count'] ?? 0,
            ];
        }

        $io->table(
            ['ID', 'Code', 'Name', 'Category', 'Steps', 'Default', 'Used'],
            $rows
        );

        return Command::SUCCESS;
    }

    // ─── Show ────────────────────────────────────────────────────────────

    private function showStrategy(IRIS $iris, SymfonyStyle $io, InputInterface $input, int $bloqId): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Strategy ID is required. Usage: outreach:strategy show <bloq_id> <id>');
            return Command::FAILURE;
        }

        $http = $iris->getHttpClient();
        $response = $http->get(self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates/{$id}");
        $template = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $categoryLabel = self::CATEGORIES[$template['category'] ?? ''] ?? ($template['category'] ?? '-');
        $iconLabel = self::ICONS[$template['icon'] ?? ''] ?? ($template['icon'] ?? '-');

        $io->title("Strategy: {$template['name']}");
        $io->definitionList(
            ['ID' => $template['id']],
            ['Short Code' => $template['short_code'] ?? '-'],
            ['Category' => $categoryLabel],
            ['Icon' => $iconLabel],
            ['Default' => ($template['is_default'] ?? false) ? 'Yes' : 'No'],
            ['Usage Count' => $template['usage_count'] ?? 0],
            ['Description' => $template['description'] ?? '(none)']
        );

        // Show steps
        $steps = $template['steps'] ?? [];
        if (empty($steps)) {
            $io->warning('No steps defined.');
            return Command::SUCCESS;
        }

        $io->section("Steps ({$this->pluralize(count($steps), 'step')})");

        foreach ($steps as $i => $step) {
            $channelLabel = self::CHANNELS[$step['type'] ?? ''] ?? ($step['type'] ?? '-');
            $num = $i + 1;
            $io->text("<fg=cyan>Step {$num}:</> <fg=white>{$step['title']}</> <fg=gray>[{$channelLabel}]</>");

            if (!empty($step['instructions'])) {
                $io->text("  <fg=gray>Script:</> " . $this->truncate($step['instructions'], 120));
            }
            if (($step['delay_hours'] ?? 0) > 0) {
                $io->text("  <fg=gray>Delay:</> {$step['delay_hours']}h");
            }
            if ($step['auto_execute'] ?? false) {
                $io->text("  <fg=gray>Auto-execute:</> <fg=green>Yes</>");
            }
            $io->newLine();
        }

        // Show variation prompt if set
        if (!empty($template['variation_prompt'])) {
            $io->section('Variation Prompt');
            $io->text($this->truncate($template['variation_prompt'], 200));
        }

        $io->note([
            "Apply to a lead: php bin/iris outreach:strategy apply {$bloqId} {$template['id']} <lead_id>",
            "Duplicate: php bin/iris outreach:strategy duplicate {$bloqId} {$template['id']}",
        ]);

        return Command::SUCCESS;
    }

    // ─── Create ──────────────────────────────────────────────────────────

    private function createStrategy(IRIS $iris, SymfonyStyle $io, InputInterface $input, int $bloqId): int
    {
        $io->title('Create Outreach Strategy');
        $helper = $this->getHelper('question');

        // 1. Name
        $name = $input->getOption('name') ?? $io->ask('Strategy name', 'New Outreach Strategy');

        // 2. Category
        $category = $input->getOption('category');
        if (!$category) {
            $categoryLabels = array_values(self::CATEGORIES);
            $categoryKeys = array_keys(self::CATEGORIES);
            $question = new ChoiceQuestion('Category', $categoryLabels, 0);
            $selectedLabel = $helper->ask($input, $io, $question);
            $category = $categoryKeys[array_search($selectedLabel, $categoryLabels)];
        }

        // 3. Icon
        $icon = $input->getOption('icon');
        if (!$icon) {
            $iconLabels = array_values(self::ICONS);
            $iconKeys = array_keys(self::ICONS);
            $question = new ChoiceQuestion('Icon', $iconLabels, 0);
            $selectedLabel = $helper->ask($input, $io, $question);
            $icon = $iconKeys[array_search($selectedLabel, $iconLabels)];
        }

        // 4. Description
        $description = $input->getOption('description') ?? $io->ask('Description (optional)', '');

        // 5. Steps
        $io->section('Add Steps');
        $io->text('<fg=gray>Available merge fields: ' . implode(' ', array_keys(self::MERGE_FIELDS)) . '</>');
        $io->newLine();

        $steps = [];
        $addMore = true;
        $stepNum = 1;

        while ($addMore) {
            $io->text("<fg=cyan>--- Step {$stepNum} ---</>");

            // Step title
            $stepTitle = $io->ask("Step {$stepNum} title", "Step {$stepNum}");

            // Channel
            $channelLabels = array_values(self::CHANNELS);
            $channelKeys = array_keys(self::CHANNELS);
            $question = new ChoiceQuestion('Channel', $channelLabels, 0);
            $selectedLabel = $helper->ask($input, $io, $question);
            $stepType = $channelKeys[array_search($selectedLabel, $channelLabels)];

            // Script/instructions
            $instructions = $io->ask('Script / instructions (use merge fields like {name}, {company})', '');

            // Delay
            $delayHours = (int) $io->ask('Delay (hours after previous step)', '0');

            // Auto-execute
            $autoExecute = $io->confirm('Auto-execute this step?', false);

            $steps[] = [
                'title'        => $stepTitle,
                'type'         => $stepType,
                'instructions' => $instructions,
                'delay_hours'  => $delayHours,
                'auto_execute' => $autoExecute,
            ];

            $stepNum++;
            $addMore = $io->confirm('Add another step?', true);
        }

        if (empty($steps)) {
            $io->error('At least one step is required.');
            return Command::FAILURE;
        }

        // 6. POST to API
        $http = $iris->getHttpClient();
        $payload = [
            'name'        => $name,
            'category'    => $category,
            'icon'        => $icon,
            'description' => $description ?: null,
            'is_default'  => false,
            'steps'       => $steps,
        ];

        $response = $http->post(self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates", $payload);
        $template = $response['data'] ?? $response;

        $io->success("Strategy created!");
        $io->definitionList(
            ['ID' => $template['id'] ?? '?'],
            ['Short Code' => $template['short_code'] ?? '-'],
            ['Name' => $template['name'] ?? $name],
            ['Steps' => count($steps)]
        );

        $templateId = $template['id'] ?? '?';
        $io->note([
            "View: php bin/iris outreach:strategy show {$bloqId} {$templateId}",
            "Apply to a lead: php bin/iris outreach:strategy apply {$bloqId} {$templateId} <lead_id>",
        ]);

        return Command::SUCCESS;
    }

    // ─── Apply ───────────────────────────────────────────────────────────

    private function applyStrategy(IRIS $iris, SymfonyStyle $io, InputInterface $input, int $bloqId): int
    {
        $id = $input->getArgument('id');
        $leadId = $input->getArgument('lead_id');

        if (!$id || !$leadId) {
            $io->error('Strategy ID and Lead ID are required. Usage: outreach:strategy apply <bloq_id> <id> <lead_id>');
            return Command::FAILURE;
        }

        $http = $iris->getHttpClient();
        $payload = [
            'lead_id'        => (int) $leadId,
            'clear_existing' => $input->getOption('clear-existing'),
        ];

        $response = $http->post(
            self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates/{$id}/apply",
            $payload
        );

        $data = $response['data'] ?? $response;
        $stepCount = $data['step_count'] ?? count($data['created_steps'] ?? []);
        $strategyName = $data['strategy_template']['name'] ?? "#{$id}";

        if ($input->getOption('json')) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->success("Applied \"{$strategyName}\" to lead #{$leadId} ({$this->pluralize($stepCount, 'step')} created)");

        // Show created steps summary
        $createdSteps = $data['created_steps'] ?? [];
        if (!empty($createdSteps)) {
            $rows = [];
            foreach ($createdSteps as $step) {
                $channelLabel = self::CHANNELS[$step['type'] ?? ''] ?? ($step['type'] ?? '-');
                $rows[] = [
                    $step['id'] ?? '?',
                    $step['title'] ?? '-',
                    $channelLabel,
                    ($step['delay_hours'] ?? 0) > 0 ? "{$step['delay_hours']}h" : 'Immediate',
                ];
            }
            $io->table(['Step ID', 'Title', 'Channel', 'Delay'], $rows);
        }

        return Command::SUCCESS;
    }

    // ─── Duplicate ───────────────────────────────────────────────────────

    private function duplicateStrategy(IRIS $iris, SymfonyStyle $io, InputInterface $input, int $bloqId): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Strategy ID is required. Usage: outreach:strategy duplicate <bloq_id> <id>');
            return Command::FAILURE;
        }

        $newName = $input->getOption('new-name') ?? $io->ask('New name for the duplicate', '');

        $http = $iris->getHttpClient();
        $payload = [];
        if ($newName) {
            $payload['name'] = $newName;
        }

        $response = $http->post(
            self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates/{$id}/duplicate",
            $payload
        );

        $template = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->success("Strategy duplicated!");
        $io->definitionList(
            ['New ID' => $template['id'] ?? '?'],
            ['Short Code' => $template['short_code'] ?? '-'],
            ['Name' => $template['name'] ?? $newName ?: '(copy)']
        );

        return Command::SUCCESS;
    }

    // ─── Delete ──────────────────────────────────────────────────────────

    private function deleteStrategy(IRIS $iris, SymfonyStyle $io, InputInterface $input, int $bloqId): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Strategy ID is required. Usage: outreach:strategy delete <bloq_id> <id>');
            return Command::FAILURE;
        }

        // Fetch name for confirmation
        $http = $iris->getHttpClient();
        $response = $http->get(self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates/{$id}");
        $template = $response['data'] ?? $response;
        $name = $template['name'] ?? "#{$id}";

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "Are you sure you want to delete strategy \"{$name}\"? [y/N] ",
            false
        );

        if (!$helper->ask($input, $io, $question)) {
            $io->info('Cancelled.');
            return Command::SUCCESS;
        }

        $http->delete(self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates/{$id}");

        $io->success("Strategy \"{$name}\" deleted.");
        return Command::SUCCESS;
    }

    // ─── Metadata ────────────────────────────────────────────────────────

    private function showMetadata(IRIS $iris, SymfonyStyle $io, InputInterface $input, int $bloqId): int
    {
        $http = $iris->getHttpClient();
        $response = $http->get(self::BASE_PATH . "/{$bloqId}/outreach-strategy-templates/metadata");
        $data = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->title("Outreach Strategy Metadata [Bloq #{$bloqId}]");

        // Categories
        $io->section('Categories');
        $categories = $data['categories'] ?? array_keys(self::CATEGORIES);
        foreach ($categories as $cat) {
            $label = self::CATEGORIES[$cat] ?? $cat;
            $io->text("  <fg=cyan>{$cat}</> — {$label}");
        }

        // Icons
        $io->section('Icons');
        $icons = $data['icons'] ?? array_keys(self::ICONS);
        foreach ($icons as $iconClass) {
            $label = self::ICONS[$iconClass] ?? $iconClass;
            $io->text("  <fg=cyan>{$iconClass}</> — {$label}");
        }

        // Step Types
        $io->section('Step Types (Channels)');
        $stepTypes = $data['step_types'] ?? array_keys(self::CHANNELS);
        foreach ($stepTypes as $key => $type) {
            if (is_array($type)) {
                // API returned [{name: ..., label: ...}] format
                $io->text("  <fg=cyan>{$type['name']}</> — " . ($type['label'] ?? $type['name']));
            } else {
                $label = self::CHANNELS[$type] ?? (is_string($key) ? $type : $type);
                $io->text("  <fg=cyan>{$type}</> — {$label}");
            }
        }

        // Merge Fields
        $io->section('Merge Fields');
        $mergeFields = $data['merge_fields'] ?? self::MERGE_FIELDS;
        if (is_array($mergeFields)) {
            foreach ($mergeFields as $key => $desc) {
                if (is_string($key)) {
                    $io->text("  <fg=yellow>{$key}</> — {$desc}");
                } else {
                    // API might return flat array of field names
                    $descFromConst = self::MERGE_FIELDS[$desc] ?? '';
                    $io->text("  <fg=yellow>{$desc}</>" . ($descFromConst ? " — {$descFromConst}" : ''));
                }
            }
        }

        return Command::SUCCESS;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function truncate(string $text, int $maxLength): string
    {
        $text = str_replace(["\n", "\r"], ' ', $text);
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, 0, $maxLength - 3) . '...';
    }

    private function pluralize(int $count, string $singular, string $plural = ''): string
    {
        if (!$plural) {
            $plural = $singular . 's';
        }
        return "{$count} " . ($count === 1 ? $singular : $plural);
    }
}
