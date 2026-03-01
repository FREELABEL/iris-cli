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
 * CLI command for managing outreach campaigns.
 *
 * Usage:
 *   ./bin/iris outreach:campaign list                              # List all campaigns
 *   ./bin/iris outreach:campaign show <id>                         # Show campaign details
 *   ./bin/iris outreach:campaign create --bloq=40                  # Create campaign
 *   ./bin/iris outreach:campaign start <id>                        # Start campaign
 *   ./bin/iris outreach:campaign pause <id>                        # Pause campaign
 *   ./bin/iris outreach:campaign resume <id>                       # Resume campaign
 *   ./bin/iris outreach:campaign cancel <id>                       # Cancel campaign
 *   ./bin/iris outreach:campaign schedule <id> --at="2026-03-05"   # Schedule campaign
 *   ./bin/iris outreach:campaign analytics <id>                    # View analytics
 *   ./bin/iris outreach:campaign recipients <id>                   # View recipients
 *   ./bin/iris outreach:campaign duplicate <id>                    # Duplicate campaign
 *   ./bin/iris outreach:campaign delete <id>                       # Delete campaign
 */
class OutreachCampaignCommand extends Command
{
    private const BASE_PATH = '/api/v1/outreach-campaigns';

    private const STATUSES = [
        'draft'     => 'Draft',
        'scheduled' => 'Scheduled',
        'active'    => 'Active',
        'paused'    => 'Paused',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    private const TYPES = [
        'one_time'  => 'One-Time',
        'recurring' => 'Recurring',
        'drip'      => 'Drip',
        'broadcast' => 'Broadcast',
    ];

    private const CHANNELS = [
        'email'         => 'Email',
        'sms'           => 'SMS',
        'instagram_dm'  => 'Instagram DM',
        'linkedin'      => 'LinkedIn',
        'multi_channel' => 'Multi-Channel',
    ];

    protected function configure(): void
    {
        $this
            ->setName('outreach:campaign')
            ->setDescription('Manage outreach campaigns')
            ->setHelp(<<<'HELP'
Manage outreach campaigns — create, start, pause, resume, cancel, and track analytics.

Usage:
  outreach:campaign list                              List all campaigns
  outreach:campaign show <id>                         Show campaign details + metrics
  outreach:campaign create --bloq=40                  Interactive campaign builder
  outreach:campaign start <id>                        Start a draft/scheduled campaign
  outreach:campaign pause <id>                        Pause an active campaign
  outreach:campaign resume <id>                       Resume a paused campaign
  outreach:campaign cancel <id>                       Cancel a campaign
  outreach:campaign schedule <id> --at="2026-03-05"   Schedule for future execution
  outreach:campaign analytics <id>                    View performance analytics
  outreach:campaign recipients <id>                   View campaign recipients
  outreach:campaign duplicate <id>                    Duplicate a campaign
  outreach:campaign delete <id>                       Delete a draft campaign

Examples:
  outreach:campaign list --bloq=40 --status=active
  outreach:campaign create --bloq=40 --name="Q1 Push" --type=broadcast --channel=email
  outreach:campaign start 12
  outreach:campaign analytics 12
  outreach:campaign recipients 12 --status=pending
  outreach:campaign schedule 12 --at="2026-03-05 10:00"
  outreach:campaign duplicate 12 --new-name="Q2 Push"

Environment:
  outreach:campaign list --env=production             Target production API
  outreach:campaign list --env=local                  Target local API

Related Commands:
  outreach:strategy list <bloq_id>                    Manage strategy templates
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list|show|create|start|pause|resume|cancel|schedule|analytics|duplicate|delete|recipients', 'list')
            ->addArgument('id', InputArgument::OPTIONAL, 'Campaign ID')
            // Common options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment: local or production')
            // Filter options
            ->addOption('bloq', null, InputOption::VALUE_REQUIRED, 'Bloq ID (for list filter + create)')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Status filter: draft|scheduled|active|paused|completed|cancelled')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Campaign type filter: one_time|recurring|drip|broadcast')
            // Create options
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Campaign name')
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Broadcast channel: email|sms|instagram_dm|linkedin|multi_channel')
            ->addOption('strategy', null, InputOption::VALUE_REQUIRED, 'Strategy template ID')
            ->addOption('agent', null, InputOption::VALUE_REQUIRED, 'Agent ID')
            // Schedule option
            ->addOption('at', null, InputOption::VALUE_REQUIRED, 'Schedule datetime (for schedule action)')
            // Duplicate option
            ->addOption('new-name', null, InputOption::VALUE_REQUIRED, 'New name for duplication');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action');

        // Handle --env flag
        $env = $input->getOption('env');
        if ($env) {
            putenv("IRIS_ENV={$env}");
            $_ENV['IRIS_ENV'] = $env;
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
                    return $this->listCampaigns($iris, $io, $input);
                case 'show':
                    return $this->showCampaign($iris, $io, $input);
                case 'create':
                    return $this->createCampaign($iris, $io, $input);
                case 'start':
                    return $this->actionCampaign($iris, $io, $input, 'start');
                case 'pause':
                    return $this->actionCampaign($iris, $io, $input, 'pause');
                case 'resume':
                    return $this->actionCampaign($iris, $io, $input, 'resume');
                case 'cancel':
                    return $this->actionCampaign($iris, $io, $input, 'cancel');
                case 'schedule':
                    return $this->scheduleCampaign($iris, $io, $input);
                case 'analytics':
                    return $this->showAnalytics($iris, $io, $input);
                case 'duplicate':
                    return $this->duplicateCampaign($iris, $io, $input);
                case 'delete':
                    return $this->deleteCampaign($iris, $io, $input);
                case 'recipients':
                    return $this->showRecipients($iris, $io, $input);
                default:
                    $io->error("Unknown action: {$action}. Use: list, show, create, start, pause, resume, cancel, schedule, analytics, duplicate, delete, recipients");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    // ─── List ────────────────────────────────────────────────────────────

    private function listCampaigns(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $http = $iris->getHttpClient();
        $query = [];

        if ($bloq = $input->getOption('bloq')) {
            $query['bloq_id'] = $bloq;
        }
        if ($status = $input->getOption('status')) {
            $query['status'] = $status;
        }
        if ($type = $input->getOption('type')) {
            $query['campaign_type'] = $type;
        }

        $response = $http->get(self::BASE_PATH, $query);
        $campaigns = $response['campaigns'] ?? [];

        if ($input->getOption('json')) {
            $io->writeln(json_encode($campaigns, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if (empty($campaigns)) {
            $io->info('No campaigns found.');
            $io->note('Create one: php bin/iris outreach:campaign create --bloq=<bloq_id>');
            return Command::SUCCESS;
        }

        $env = $input->getOption('env') ?: (getenv('IRIS_ENV') ?: 'production');
        $io->title("Outreach Campaigns [{$env}]");

        $rows = [];
        foreach ($campaigns as $c) {
            $rows[] = [
                $c['id'],
                $c['name'] ?? '-',
                $this->formatStatus($c['status'] ?? 'draft'),
                self::TYPES[$c['campaign_type'] ?? ''] ?? ($c['campaign_type'] ?? '-'),
                $c['total_recipients'] ?? 0,
                $c['sent_count'] ?? 0,
                isset($c['progress_percentage']) ? round($c['progress_percentage']) . '%' : '0%',
            ];
        }

        $io->table(
            ['ID', 'Name', 'Status', 'Type', 'Recipients', 'Sent', 'Progress'],
            $rows
        );

        return Command::SUCCESS;
    }

    // ─── Show ────────────────────────────────────────────────────────────

    private function showCampaign(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Campaign ID is required. Usage: outreach:campaign show <id>');
            return Command::FAILURE;
        }

        $http = $iris->getHttpClient();
        $response = $http->get(self::BASE_PATH . "/{$id}");
        $campaign = $response['campaign'] ?? $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->title("Campaign: {$campaign['name']}");

        $io->definitionList(
            ['ID' => $campaign['id']],
            ['Status' => $this->formatStatusPlain($campaign['status'] ?? 'draft')],
            ['Type' => self::TYPES[$campaign['campaign_type'] ?? ''] ?? ($campaign['campaign_type'] ?? '-')],
            ['Channel' => self::CHANNELS[$campaign['broadcast_channel'] ?? ''] ?? ($campaign['broadcast_channel'] ?? '-')],
            ['Bloq ID' => $campaign['bloq_id'] ?? '-'],
            ['Agent' => $campaign['agent']['name'] ?? ($campaign['agent_id'] ?? '-')],
            ['Strategy' => $campaign['strategy_template']['name'] ?? ($campaign['strategy_template_id'] ?? '-')],
            ['Description' => $campaign['description'] ?? '(none)']
        );

        // Metrics summary
        $io->section('Metrics');
        $io->definitionList(
            ['Recipients' => $campaign['total_recipients'] ?? 0],
            ['Sent' => $campaign['sent_count'] ?? 0],
            ['Delivered' => $campaign['delivered_count'] ?? 0],
            ['Opened' => $campaign['opened_count'] ?? 0],
            ['Clicked' => $campaign['clicked_count'] ?? 0],
            ['Replied' => $campaign['replied_count'] ?? 0],
            ['Bounced' => $campaign['bounced_count'] ?? 0],
            ['Failed' => $campaign['failed_count'] ?? 0]
        );

        // Progress
        $progress = $campaign['progress_percentage'] ?? 0;
        $io->text("Progress: " . $this->progressBar($progress));

        // Timestamps
        $io->section('Timeline');
        $timestamps = [];
        if (!empty($campaign['scheduled_at'])) {
            $timestamps[] = ['Scheduled' => $campaign['scheduled_at']];
        }
        if (!empty($campaign['started_at'])) {
            $timestamps[] = ['Started' => $campaign['started_at']];
        }
        if (!empty($campaign['paused_at'])) {
            $timestamps[] = ['Paused' => $campaign['paused_at']];
        }
        if (!empty($campaign['completed_at'])) {
            $timestamps[] = ['Completed' => $campaign['completed_at']];
        }
        $timestamps[] = ['Created' => $campaign['created_at'] ?? '-'];
        $io->definitionList(...$timestamps);

        // Helpful next actions
        $status = $campaign['status'] ?? 'draft';
        $hints = [];
        if ($status === 'draft') {
            $hints[] = "Start: php bin/iris outreach:campaign start {$campaign['id']}";
            $hints[] = "Schedule: php bin/iris outreach:campaign schedule {$campaign['id']} --at=\"2026-03-05 10:00\"";
        }
        if ($status === 'active') {
            $hints[] = "Pause: php bin/iris outreach:campaign pause {$campaign['id']}";
            $hints[] = "Analytics: php bin/iris outreach:campaign analytics {$campaign['id']}";
        }
        if ($status === 'paused') {
            $hints[] = "Resume: php bin/iris outreach:campaign resume {$campaign['id']}";
        }
        $hints[] = "Recipients: php bin/iris outreach:campaign recipients {$campaign['id']}";

        if (!empty($hints)) {
            $io->note($hints);
        }

        return Command::SUCCESS;
    }

    // ─── Create ──────────────────────────────────────────────────────────

    private function createCampaign(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('Create Outreach Campaign');
        $helper = $this->getHelper('question');

        // 1. Bloq ID
        $bloqId = $input->getOption('bloq');
        if (!$bloqId) {
            $bloqId = $io->ask('Bloq ID (required)');
        }
        if (!$bloqId) {
            $io->error('Bloq ID is required. Use --bloq=<id>');
            return Command::FAILURE;
        }

        // 2. Name
        $name = $input->getOption('name') ?? $io->ask('Campaign name', 'New Campaign');

        // 3. Campaign type
        $type = $input->getOption('type');
        if (!$type) {
            $typeLabels = array_values(self::TYPES);
            $typeKeys = array_keys(self::TYPES);
            $question = new ChoiceQuestion('Campaign type', $typeLabels, 3); // default: Broadcast
            $selectedLabel = $helper->ask($input, $io, $question);
            $type = $typeKeys[array_search($selectedLabel, $typeLabels)];
        }

        // 4. Broadcast channel
        $channel = $input->getOption('channel');
        if (!$channel) {
            $channelLabels = array_values(self::CHANNELS);
            $channelKeys = array_keys(self::CHANNELS);
            $question = new ChoiceQuestion('Channel', $channelLabels, 0); // default: Email
            $selectedLabel = $helper->ask($input, $io, $question);
            $channel = $channelKeys[array_search($selectedLabel, $channelLabels)];
        }

        // 5. Strategy template (optional)
        $strategyId = $input->getOption('strategy');
        if (!$strategyId) {
            $strategyId = $io->ask('Strategy template ID (optional, press Enter to skip)', '');
            $strategyId = $strategyId ?: null;
        }

        // 6. Agent (optional)
        $agentId = $input->getOption('agent');
        if (!$agentId) {
            $agentId = $io->ask('Agent ID (optional, press Enter to skip)', '');
            $agentId = $agentId ?: null;
        }

        // 7. Broadcast content (for email/sms)
        $subject = null;
        $message = null;
        if (in_array($channel, ['email', 'multi_channel'])) {
            $subject = $io->ask('Email subject (optional)', '');
            $subject = $subject ?: null;
        }
        $message = $io->ask('Broadcast message (optional)', '');
        $message = $message ?: null;

        // 8. POST to API
        $http = $iris->getHttpClient();
        $payload = [
            'bloq_id'              => (int) $bloqId,
            'name'                 => $name,
            'campaign_type'        => $type,
            'broadcast_channel'    => $channel,
            'strategy_template_id' => $strategyId ? (int) $strategyId : null,
            'agent_id'             => $agentId ? (int) $agentId : null,
            'broadcast_subject'    => $subject,
            'broadcast_message'    => $message,
        ];

        $response = $http->post(self::BASE_PATH, $payload);
        $campaign = $response['campaign'] ?? $response['data'] ?? $response;

        $io->success("Campaign created!");
        $io->definitionList(
            ['ID' => $campaign['id'] ?? '?'],
            ['Name' => $campaign['name'] ?? $name],
            ['Status' => 'Draft'],
            ['Type' => self::TYPES[$type] ?? $type]
        );

        $campaignId = $campaign['id'] ?? '?';
        $io->note([
            "View: php bin/iris outreach:campaign show {$campaignId}",
            "Start now: php bin/iris outreach:campaign start {$campaignId}",
            "Schedule: php bin/iris outreach:campaign schedule {$campaignId} --at=\"2026-03-05 10:00\"",
        ]);

        return Command::SUCCESS;
    }

    // ─── Start / Pause / Resume / Cancel ─────────────────────────────────

    private function actionCampaign(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $action): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error("Campaign ID is required. Usage: outreach:campaign {$action} <id>");
            return Command::FAILURE;
        }

        $http = $iris->getHttpClient();

        // Confirmation for destructive actions
        if ($action === 'cancel') {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Are you sure you want to cancel campaign #{$id}? [y/N] ",
                false
            );
            if (!$helper->ask($input, $io, $question)) {
                $io->info('Cancelled.');
                return Command::SUCCESS;
            }
        }

        $response = $http->post(self::BASE_PATH . "/{$id}/{$action}");
        $campaign = $response['campaign'] ?? $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $message = $response['message'] ?? "Campaign {$action}ed";
        $io->success($message);

        if (is_array($campaign) && isset($campaign['name'])) {
            $io->text("Campaign: <fg=white>{$campaign['name']}</>");
            $io->text("Status: " . $this->formatStatus($campaign['status'] ?? $action));
            if (isset($campaign['total_recipients'])) {
                $io->text("Recipients: {$campaign['total_recipients']}");
            }
        }

        return Command::SUCCESS;
    }

    // ─── Schedule ────────────────────────────────────────────────────────

    private function scheduleCampaign(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Campaign ID is required. Usage: outreach:campaign schedule <id> --at="2026-03-05 10:00"');
            return Command::FAILURE;
        }

        $scheduledAt = $input->getOption('at');
        if (!$scheduledAt) {
            $scheduledAt = $io->ask('Schedule for (datetime, e.g. 2026-03-05 10:00)');
        }

        $http = $iris->getHttpClient();
        $payload = [];
        if ($scheduledAt) {
            $payload['scheduled_at'] = $scheduledAt;
        }

        $response = $http->post(self::BASE_PATH . "/{$id}/schedule", $payload);
        $campaign = $response['campaign'] ?? $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $message = $response['message'] ?? 'Campaign scheduled';
        $io->success($message);

        return Command::SUCCESS;
    }

    // ─── Analytics ───────────────────────────────────────────────────────

    private function showAnalytics(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Campaign ID is required. Usage: outreach:campaign analytics <id>');
            return Command::FAILURE;
        }

        $http = $iris->getHttpClient();
        $response = $http->get(self::BASE_PATH . "/{$id}/analytics");
        $analytics = $response['analytics'] ?? $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($analytics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->title("Campaign Analytics [#{$id}]");

        // Overview table
        $overview = $analytics['overview'] ?? [];
        $io->section('Overview');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total Recipients', $overview['total_recipients'] ?? 0],
                ['Sent', $overview['sent_count'] ?? 0],
                ['Delivered', $overview['delivered_count'] ?? 0],
                ['Opened', $overview['opened_count'] ?? 0],
                ['Clicked', $overview['clicked_count'] ?? 0],
                ['Replied', $overview['replied_count'] ?? 0],
                ['Bounced', $overview['bounced_count'] ?? 0],
                ['Failed', $overview['failed_count'] ?? 0],
            ]
        );

        // Rates
        $rates = $analytics['rates'] ?? [];
        $io->section('Rates');
        $io->table(
            ['Rate', 'Value'],
            [
                ['Delivery Rate', ($rates['delivery_rate'] ?? 0) . '%'],
                ['Open Rate', ($rates['open_rate'] ?? 0) . '%'],
                ['Click Rate', ($rates['click_rate'] ?? 0) . '%'],
                ['Reply Rate', ($rates['reply_rate'] ?? 0) . '%'],
                ['Bounce Rate', ($rates['bounce_rate'] ?? 0) . '%'],
            ]
        );

        // Progress
        $progress = $analytics['progress'] ?? 0;
        $io->text("Progress: " . $this->progressBar($progress));

        // Status + timestamps
        $io->newLine();
        $io->text("Status: " . $this->formatStatus($analytics['status'] ?? 'unknown'));
        if (!empty($analytics['started_at'])) {
            $io->text("Started: {$analytics['started_at']}");
        }
        if (!empty($analytics['completed_at'])) {
            $io->text("Completed: {$analytics['completed_at']}");
        }

        return Command::SUCCESS;
    }

    // ─── Duplicate ───────────────────────────────────────────────────────

    private function duplicateCampaign(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Campaign ID is required. Usage: outreach:campaign duplicate <id>');
            return Command::FAILURE;
        }

        $newName = $input->getOption('new-name') ?? $io->ask('New name for the duplicate (optional)', '');

        $http = $iris->getHttpClient();
        $payload = [];
        if ($newName) {
            $payload['name'] = $newName;
        }

        $response = $http->post(self::BASE_PATH . "/{$id}/duplicate", $payload);
        $campaign = $response['campaign'] ?? $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($campaign, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $io->success("Campaign duplicated!");
        $io->definitionList(
            ['New ID' => $campaign['id'] ?? '?'],
            ['Name' => $campaign['name'] ?? '(copy)'],
            ['Status' => 'Draft']
        );

        return Command::SUCCESS;
    }

    // ─── Delete ──────────────────────────────────────────────────────────

    private function deleteCampaign(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Campaign ID is required. Usage: outreach:campaign delete <id>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "Are you sure you want to delete campaign #{$id}? (Only draft campaigns can be deleted) [y/N] ",
            false
        );

        if (!$helper->ask($input, $io, $question)) {
            $io->info('Cancelled.');
            return Command::SUCCESS;
        }

        $http = $iris->getHttpClient();
        $http->delete(self::BASE_PATH . "/{$id}");

        $io->success("Campaign #{$id} deleted.");
        return Command::SUCCESS;
    }

    // ─── Recipients ──────────────────────────────────────────────────────

    private function showRecipients(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $id = $input->getArgument('id');
        if (!$id) {
            $io->error('Campaign ID is required. Usage: outreach:campaign recipients <id>');
            return Command::FAILURE;
        }

        $http = $iris->getHttpClient();
        $query = [];
        if ($status = $input->getOption('status')) {
            $query['status'] = $status;
        }

        $response = $http->get(self::BASE_PATH . "/{$id}/recipients", $query);
        $recipientsData = $response['recipients'] ?? $response['data'] ?? [];

        // Handle paginated response
        $recipients = $recipientsData['data'] ?? $recipientsData;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($recipientsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        if (empty($recipients)) {
            $io->info("No recipients found for campaign #{$id}.");
            return Command::SUCCESS;
        }

        $io->title("Campaign #{$id} Recipients");

        $rows = [];
        foreach ($recipients as $r) {
            $lead = $r['lead'] ?? [];
            $rows[] = [
                $r['lead_id'] ?? '-',
                $lead['name'] ?? ($lead['first_name'] ?? '-'),
                $lead['email'] ?? '-',
                $this->formatRecipientStatus($r['status'] ?? 'pending'),
                $r['sent_at'] ?? '-',
                $r['opened_at'] ?? '-',
            ];
        }

        $io->table(
            ['Lead ID', 'Name', 'Email', 'Status', 'Sent At', 'Opened At'],
            $rows
        );

        // Pagination info
        $total = $recipientsData['total'] ?? count($recipients);
        $currentPage = $recipientsData['current_page'] ?? 1;
        $lastPage = $recipientsData['last_page'] ?? 1;
        if ($lastPage > 1) {
            $io->text("<fg=gray>Page {$currentPage} of {$lastPage} (total: {$total})</>");
        }

        return Command::SUCCESS;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function formatStatus(string $status): string
    {
        return match ($status) {
            'active'    => '<fg=green>● Active</>',
            'draft'     => '<fg=yellow>○ Draft</>',
            'scheduled' => '<fg=cyan>◎ Scheduled</>',
            'paused'    => '<fg=yellow>⏸ Paused</>',
            'completed' => '<fg=gray>✓ Completed</>',
            'cancelled' => '<fg=red>✗ Cancelled</>',
            default     => $status,
        };
    }

    private function formatStatusPlain(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    private function formatRecipientStatus(string $status): string
    {
        return match ($status) {
            'pending'      => '<fg=gray>Pending</>',
            'sent'         => '<fg=cyan>Sent</>',
            'delivered'    => '<fg=blue>Delivered</>',
            'opened'       => '<fg=green>Opened</>',
            'clicked'      => '<fg=green>Clicked</>',
            'replied'      => '<fg=green>Replied</>',
            'bounced'      => '<fg=red>Bounced</>',
            'failed'       => '<fg=red>Failed</>',
            'unsubscribed' => '<fg=yellow>Unsubscribed</>',
            default        => $status,
        };
    }

    private function progressBar(float $percentage): string
    {
        $filled = (int) round($percentage / 5);
        $empty = 20 - $filled;
        $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
        return "[{$bar}] " . round($percentage, 1) . '%';
    }
}
