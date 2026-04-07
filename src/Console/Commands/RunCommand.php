<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\Auth\CredentialStore;
use IRIS\SDK\IRIS;

/**
 * Unified Run Command — execute any tool or integration function from the CLI.
 *
 * Automatically routes to the right backend:
 * - V6ToolRegistry system tools (searchPlaces, reviewAggregator, deepResearch, etc.)
 * - IntegrationRegistry functions (gmail, copycat-ai, servis-ai, genesis, etc.)
 *
 * Usage:
 *   iris run gmail search_emails query="invoice" maxResults=5
 *   iris run copycat-ai generate_article url="https://youtube.com/..."
 *   iris run servis-ai list_activities
 *   iris run searchPlaces query="pizza" location="Austin, TX"
 *   iris run reviewAggregator business_name="40 Love" location="Scottsdale"
 *   iris run restaurantIntelligence business_name="40 Love" location="Scottsdale" recipient_email="r@email.com"
 */
class RunCommand extends Command
{
    /**
     * Known integration types — routes to IntegrationRegistry.
     * Anything not in this list routes to V6ToolRegistry.
     */
    protected const INTEGRATION_TYPES = [
        'atlas-os', 'beatbox-showcase', 'buffer', 'copycat-ai', 'fal-ai',
        'fl-api', 'genesis', 'github-copilot', 'gmail', 'google-calendar',
        'google-drive', 'google-gemini', 'macos', 'savelife-ai', 'servis-ai',
        'slack', 'vagaro', 'vapi', 'whatsapp', 'workflow-composer',
    ];

    protected function configure(): void
    {
        $this
            ->setName('run')
            ->setDescription('Execute any tool or integration function')
            ->setHelp(<<<'HELP'
Unified command to call any V6 system tool or integration function.

SYSTEM TOOLS (V6ToolRegistry):
  iris run searchPlaces query="pizza" location="Austin, TX"
  iris run reviewAggregator business_name="40 Love" location="Scottsdale"
  iris run menuScraper business_name="Chili's" location="Austin"
  iris run restaurantIntelligence business_name="40 Love" location="Scottsdale"
  iris run deepResearch topic="AI in healthcare"
  iris run leadGeneration query="dentists in Austin" target_count=50

INTEGRATIONS (IntegrationRegistry):
  iris run gmail search_emails query="invoice" maxResults=5
  iris run gmail send_email to="user@email.com" subject="Hello" body="Hi there"
  iris run copycat-ai generate_article url="https://youtube.com/watch?v=abc"
  iris run servis-ai list_activities
  iris run servis-ai get_case_details case_id=123
  iris run genesis create_page slug="my-page" title="My Page"
  iris run google-drive search_files query="budget 2026"
  iris run slack send_message channel="#general" text="Hello from CLI"
  iris run atlas-os create_contract template=nda

LIST ALL:
  iris run --list-tools          # V6 system tools
  iris run --list-integrations   # Integration types
HELP
            )
            ->addArgument('target', InputArgument::OPTIONAL, 'Tool name or integration type (e.g., gmail, searchPlaces, copycat-ai)')
            ->addArgument('function', InputArgument::OPTIONAL, 'Function name for integrations (e.g., search_emails, generate_article)')
            ->addArgument('params', InputArgument::IS_ARRAY, 'Parameters as key=value pairs (e.g., query="pizza" location="Austin")')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('list-tools', null, InputOption::VALUE_NONE, 'List all V6 system tools')
            ->addOption('list-integrations', null, InputOption::VALUE_NONE, 'List all integration types')
            ->addOption('list-connected', null, InputOption::VALUE_NONE, 'Show your connected integrations')
            ->addOption('list-available', null, InputOption::VALUE_NONE, 'Show all available integrations + connection status')
            ->addOption('connect', null, InputOption::VALUE_REQUIRED, 'Get OAuth URL or instructions to connect an integration')
            ->addOption('functions', null, InputOption::VALUE_NONE, 'List available functions for an integration')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $iris = $this->buildIris($input, $io);
            if (! $iris) {
                return Command::FAILURE;
            }

            // List / discovery modes
            if ($input->getOption('list-tools')) {
                return $this->listTools($iris, $io, $input);
            }
            if ($input->getOption('list-integrations')) {
                return $this->listIntegrations($io);
            }
            if ($input->getOption('list-connected')) {
                return $this->listConnected($iris, $io, $input);
            }
            if ($input->getOption('list-available')) {
                return $this->listAvailable($iris, $io, $input);
            }
            if ($connectType = $input->getOption('connect')) {
                return $this->connectIntegration($iris, $io, $connectType);
            }

            $target = $input->getArgument('target');

            // --functions flag: list available functions for an integration
            if ($target && $input->getOption('functions')) {
                return $this->listFunctions($iris, $io, $input, $target);
            }

            if (! $target) {
                $io->title('IRIS Run — Unified Tool & Integration Executor');
                $io->text([
                    'Usage: iris run <target> [function] [key=value ...]',
                    '',
                    'Examples:',
                    '  iris run gmail search_emails query="invoice"',
                    '  iris run copycat-ai generate_article url="https://youtube.com/..."',
                    '  iris run searchPlaces query="pizza" location="Austin, TX"',
                    '  iris run reviewAggregator business_name="40 Love" location="Scottsdale"',
                    '',
                    '  iris run --list-tools          # Show all system tools',
                    '  iris run --list-integrations   # Show all integration types',
                    '  iris run --list-connected      # Your connected integrations',
                    '  iris run --list-available      # All available + status',
                    '  iris run --connect gmail       # Get OAuth URL to connect',
                    '  iris run gmail --functions     # List functions for gmail',
                ]);

                return Command::SUCCESS;
            }

            // Parse key=value params
            $params = $this->parseParams($input->getArgument('params'));
            $function = $input->getArgument('function');

            // Route: hive, integration, or system tool?
            if (strtolower($target) === 'hive') {
                return $this->executeHive($iris, $io, $input, $function, $params);
            }

            if ($this->isIntegration($target)) {
                return $this->executeIntegration($iris, $io, $input, $target, $function, $params);
            }

            // System tool — function name is optional (merge into params if it looks like key=value)
            if ($function && str_contains($function, '=')) {
                $params = array_merge($this->parseParams([$function]), $params);
                $function = null;
            }

            return $this->executeTool($iris, $io, $input, $target, $params);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    // ─── Integration Execution ──────────────────────────────────

    protected function executeIntegration(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $type, ?string $function, array $params): int
    {
        if (! $function) {
            // No function specified — show available functions for this integration
            $io->text("No function specified for <fg=cyan>{$type}</>. Showing available functions:\n");

            return $this->listFunctions($iris, $io, $input, $type);
        }

        $io->text("Executing <fg=cyan>{$type}</>.{$function}...");

        $result = $iris->integrations->execute($type, $function, $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->displayResult($result, "{$type}.{$function}", $io);
        }

        return Command::SUCCESS;
    }

    // ─── System Tool Execution ──────────────────────────────────

    protected function executeTool(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $toolName, array $params): int
    {
        $io->text("Executing <fg=cyan>{$toolName}</>...");

        $result = $iris->tools->execute($toolName, $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->displayResult($result, $toolName, $io);
        }

        return Command::SUCCESS;
    }

    // ─── List Tools ─────────────────────────────────────────────

    protected function listTools(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('V6 System Tools');

        try {
            $result = $iris->tools->registry();

            if ($input->getOption('json')) {
                $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;
            }

            foreach ($result['tools'] ?? [] as $tool) {
                $name = $tool['name'] ?? '?';
                $healthy = ($tool['healthy'] ?? false) ? '<fg=green>ok</>' : '<fg=yellow>?</>';
                $io->writeln("  <fg=cyan>{$name}</> [{$healthy}]");
            }

            $io->newLine();
            $io->text('Run: iris run <tool-name> key=value ...');
        } catch (\Exception $e) {
            $io->warning('Could not fetch registry: ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }

    // ─── List Integrations ──────────────────────────────────────

    protected function listIntegrations(SymfonyStyle $io): int
    {
        $io->title('Available Integrations');

        foreach (self::INTEGRATION_TYPES as $type) {
            $io->writeln("  <fg=cyan>{$type}</>");
        }

        $io->newLine();
        $io->text('Run: iris run <integration> <function> key=value ...');
        $io->text('Example: iris run gmail search_emails query="invoice"');

        return Command::SUCCESS;
    }

    // ─── Hive (Distributed Compute) ──────────────────────────────

    protected function executeHive(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $action, array $params): int
    {
        $action = $action ?: 'help';

        switch ($action) {
            case 'nodes':
                $io->title('Hive — Connected Nodes');
                $result = $iris->tools->hiveNodes();
                if ($input->getOption('json')) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $nodes = $result['data'] ?? $result['nodes'] ?? $result;
                    if (empty($nodes)) {
                        $io->text('No nodes connected.');
                        $io->text('Connect one: node daemon.js --api-key node_live_xxx');

                        return Command::SUCCESS;
                    }
                    foreach ($nodes as $node) {
                        $n = is_object($node) ? (array) $node : $node;
                        $status = $n['status'] ?? '?';
                        $statusColor = $status === 'online' ? 'green' : 'gray';
                        $hw = $n['hardware_summary'] ?? '';
                        $io->writeln("  <fg=cyan>{$n['name']}</> [<fg={$statusColor}>{$status}</>] {$hw}");
                    }
                }

                return Command::SUCCESS;

            case 'tasks':
                $io->title('Hive — Recent Tasks');
                $result = $iris->tools->hiveTasks();
                if ($input->getOption('json')) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $tasks = $result['data'] ?? $result['tasks'] ?? $result;
                    if (empty($tasks)) {
                        $io->text('No tasks.');

                        return Command::SUCCESS;
                    }
                    $rows = [];
                    foreach (array_slice($tasks, 0, 20) as $t) {
                        $t = is_object($t) ? (array) $t : $t;
                        $rows[] = [
                            $t['id'] ?? '?',
                            mb_substr($t['title'] ?? $t['type'] ?? '?', 0, 40),
                            $t['status'] ?? '?',
                            $t['created_at'] ?? '',
                        ];
                    }
                    $io->table(['ID', 'Title', 'Status', 'Created'], $rows);
                }

                return Command::SUCCESS;

            case 'status':
                $taskId = $params['id'] ?? $params['task_id'] ?? null;
                if (! $taskId) {
                    $io->error('Task ID required. Usage: iris run hive status id=123');

                    return Command::FAILURE;
                }
                $result = $iris->tools->hiveTaskStatus((string) $taskId);
                $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;

            case 'cancel':
                $taskId = $params['id'] ?? $params['task_id'] ?? null;
                if (! $taskId) {
                    $io->error('Task ID required. Usage: iris run hive cancel id=123');

                    return Command::FAILURE;
                }
                $result = $iris->tools->hiveTaskCancel((string) $taskId);
                $io->success("Task {$taskId} cancelled.");

                return Command::SUCCESS;

            case 'campaigns':
                $io->title('Hive — Campaign Templates');
                $result = $iris->tools->hiveCampaigns();
                if ($input->getOption('json')) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                } else {
                    $campaigns = $result['data'] ?? $result;
                    foreach ($campaigns as $c) {
                        $c = is_object($c) ? (array) $c : $c;
                        $cName = $c['name'] ?? '?';
                        $cType = $c['task_type'] ?? '?';
                        $io->writeln("  <fg=cyan>[{$c['id']}]</> {$cName} — {$cType}");
                    }
                }

                return Command::SUCCESS;

            case 'launch':
                $templateId = $params['id'] ?? $params['template_id'] ?? null;
                if (! $templateId) {
                    $io->error('Template ID required. Usage: iris run hive launch id=5');

                    return Command::FAILURE;
                }
                $io->text("Launching campaign template #{$templateId}...");
                $result = $iris->tools->hiveCampaignLaunch((int) $templateId, $params);
                $io->success('Campaign launched.');
                $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;

            case 'dispatch':
                $type = $params['type'] ?? 'custom';
                $prompt = $params['prompt'] ?? '';
                $title = $params['title'] ?? "CLI: {$type}";
                if (empty($prompt)) {
                    $io->error('prompt required. Usage: iris run hive dispatch type=som prompt="Search for dentists in Austin"');

                    return Command::FAILURE;
                }
                $io->text("Dispatching <fg=cyan>{$type}</> task...");
                $result = $iris->tools->hiveDispatch([
                    'title' => $title,
                    'prompt' => $prompt,
                    'type' => $type,
                    'timeout_seconds' => (int) ($params['timeout'] ?? 600),
                ]);
                $io->success('Task dispatched.');
                $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;

            case 'tool':
                $tool = $params['tool'] ?? '';
                $fn = $params['function'] ?? 'execute';
                if (empty($tool)) {
                    $io->error('tool required. Usage: iris run hive tool tool=searchPlaces function=search query="pizza"');

                    return Command::FAILURE;
                }
                unset($params['tool'], $params['function']);
                $io->text("Dispatching <fg=cyan>{$tool}</>.{$fn} to Hive...");
                $result = $iris->tools->hiveExecuteTool($tool, $fn, $params);
                $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;

            default:
                $io->title('Hive — Distributed Compute');
                $io->text([
                    'Usage: iris run hive <action> [params]',
                    '',
                    'Actions:',
                    '  <fg=cyan>nodes</>        List connected compute nodes',
                    '  <fg=cyan>tasks</>        List recent tasks',
                    '  <fg=cyan>status</>       Get task status          iris run hive status id=123',
                    '  <fg=cyan>cancel</>       Cancel a task            iris run hive cancel id=123',
                    '  <fg=cyan>campaigns</>    List campaign templates',
                    '  <fg=cyan>launch</>       Launch a campaign        iris run hive launch id=5',
                    '  <fg=cyan>dispatch</>     Dispatch a custom task   iris run hive dispatch type=som prompt="..."',
                    '  <fg=cyan>tool</>         Execute a V6 tool via Hive  iris run hive tool tool=searchPlaces query="pizza"',
                ]);

                return Command::SUCCESS;
        }
    }

    // ─── Connected Integrations ────────────────────────────────

    protected function listConnected(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('Your Connected Integrations');

        try {
            $integrations = $iris->integrations->list();
            $items = $integrations->items ?? [];

            // Handle different response shapes
            if (empty($items) && is_array($integrations)) {
                $items = $integrations['data'] ?? $integrations;
            }

            if ($input->getOption('json')) {
                $io->writeln(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;
            }

            if (empty($items)) {
                $io->text('No integrations connected yet.');
                $io->text('Run: iris run --list-available');

                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($items as $item) {
                $data = is_object($item) ? (array) $item : $item;
                $rows[] = [
                    $data['id'] ?? '?',
                    $data['name'] ?? $data['type'] ?? '?',
                    $data['type'] ?? '?',
                    $data['status'] ?? 'active',
                    $data['created_at'] ?? '',
                ];
            }

            $io->table(['ID', 'Name', 'Type', 'Status', 'Connected'], $rows);
            $io->text('Run a function: iris run <type> <function> key=value ...');
        } catch (\Exception $e) {
            $io->error('Failed to fetch integrations: ' . $e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    // ─── Available Integrations ─────────────────────────────────

    protected function listAvailable(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('Available Integrations');

        try {
            // Get available types
            $types = $iris->integrations->types();
            $typeList = $types['types'] ?? $types['data'] ?? $types;

            // Get connected integrations to mark status
            $connected = [];
            try {
                $list = $iris->integrations->list();
                $items = $list->items ?? (is_array($list) ? ($list['data'] ?? $list) : []);
                foreach ($items as $item) {
                    $data = is_object($item) ? (array) $item : $item;
                    $connected[strtolower($data['type'] ?? '')] = true;
                }
            } catch (\Exception $e) {
                // Non-fatal — just won't show connection status
            }

            if ($input->getOption('json')) {
                $io->writeln(json_encode([
                    'types' => $typeList,
                    'connected' => array_keys($connected),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;
            }

            if (is_array($typeList)) {
                foreach ($typeList as $type) {
                    $name = is_string($type) ? $type : ($type['type'] ?? $type['name'] ?? '?');
                    $desc = is_array($type) ? ($type['description'] ?? '') : '';
                    $auth = is_array($type) ? ($type['auth_type'] ?? '') : '';
                    $isConnected = isset($connected[strtolower($name)]);
                    $status = $isConnected ? '<fg=green>connected</>' : '<fg=gray>not connected</>';
                    $authLabel = $auth ? " [{$auth}]" : '';

                    $io->writeln("  <fg=cyan>{$name}</> {$status}{$authLabel}");
                    if ($desc) {
                        $io->writeln("    {$desc}");
                    }
                }
            }

            $io->newLine();
            $io->text('Connect: iris run --connect <type>');
        } catch (\Exception $e) {
            // Fallback to hardcoded list with connection status
            foreach (self::INTEGRATION_TYPES as $type) {
                $isConnected = isset($connected[strtolower($type)]);
                $status = $isConnected ? '<fg=green>connected</>' : '<fg=gray>available</>';
                $io->writeln("  <fg=cyan>{$type}</> {$status}");
            }
        }

        return Command::SUCCESS;
    }

    // ─── Connect Integration ────────────────────────────────────

    protected function connectIntegration(IRIS $iris, SymfonyStyle $io, string $type): int
    {
        $io->title("Connect: {$type}");

        try {
            // Check if already connected
            $status = $iris->integrations->status($type);
            if ($status['connected'] ?? false) {
                $io->success("{$type} is already connected!");
                $io->text("Run: iris run {$type} --functions");

                return Command::SUCCESS;
            }

            if ($iris->integrations->usesOAuth($type)) {
                return $this->connectOAuth($iris, $io, $type);
            } elseif ($iris->integrations->usesApiKey($type)) {
                return $this->connectApiKey($io, $type);
            } else {
                // Try OAuth anyway — the usesOAuth list might not be exhaustive
                return $this->connectOAuth($iris, $io, $type);
            }
        } catch (\Exception $e) {
            $io->error('Failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Get OAuth URL from iris-api's Composio endpoint.
     */
    protected function getComposioOAuthUrl(IRIS $iris, string $type): ?string
    {
        try {
            $config = $iris->getConfig();
            $userId = $config->userId;
            $apiKey = $config->apiKey;

            // Try multiple iris-api URLs
            $urls = [
                'https://main.heyiris.io',
                'https://heyiris.io',
                'https://iris-api.freelabel.net',
            ];

            foreach ($urls as $base) {
                $ch = curl_init("{$base}/api/v1/integrations-temp/oauth-url/{$type}?user_id={$userId}");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => [
                        'Accept: application/json',
                        'Authorization: Bearer ' . $apiKey,
                    ],
                ]);
                $resp = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($code === 200 && $resp) {
                    $data = json_decode($resp, true);
                    if (!empty($data['data']['oauth_url'])) {
                        return $data['data']['oauth_url'];
                    }
                }
            }
        } catch (\Exception $e) {
            // Fall through
        }

        return null;
    }

    protected function connectOAuth(IRIS $iris, SymfonyStyle $io, string $type): int
    {
        $io->text("Starting OAuth flow for <fg=cyan>{$type}</>...");

        try {
            // Try iris-api Composio endpoint first (handles all Composio-backed integrations)
            $url = $this->getComposioOAuthUrl($iris, $type);

            // Fall back to fl-api SDK for non-Composio integrations
            if (empty($url)) {
                $url = $iris->integrations->getOAuthUrl($type);
            }

            if (empty($url)) {
                $io->error("Could not generate OAuth URL for {$type}.");
                $io->text([
                    'This usually means the OAuth client credentials are not configured.',
                    "Check that the Google/Slack/etc client ID and secret are set in fl-api's .env",
                    '',
                    'Required env vars for Google integrations:',
                    '  GOOGLE_CALENDAR_CLIENT_ID=...',
                    '  GOOGLE_CALENDAR_CLIENT_SECRET=...',
                ]);

                return Command::FAILURE;
            }

            $io->newLine();
            $io->section('Step 1: Authorize in your browser');
            $io->writeln("  <fg=cyan>{$url}</>");
            $io->newLine();

            // Auto-open on Mac
            if (PHP_OS_FAMILY === 'Darwin') {
                exec('open ' . escapeshellarg($url) . ' 2>/dev/null &');
                $io->text('(Opened in your browser)');
            } elseif (PHP_OS_FAMILY === 'Linux') {
                exec('xdg-open ' . escapeshellarg($url) . ' 2>/dev/null &');
            }

            $io->section('Step 2: Wait for authorization');
            $io->text('After you authorize in the browser, the integration connects automatically.');
            $io->text("Check status: <fg=cyan>iris run --list-connected</>");
            $io->text("Then use it:  <fg=cyan>iris run {$type} <function> key=value ...</>");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('OAuth flow failed: ' . $e->getMessage());

            // Provide helpful context
            if (str_contains($e->getMessage(), 'not configured')) {
                $io->text([
                    '',
                    'The OAuth provider credentials are not set up.',
                    'To configure Google OAuth:',
                    '  1. Go to https://console.cloud.google.com/apis/credentials',
                    '  2. Create an OAuth 2.0 Client ID',
                    '  3. Add the redirect URI: https://apiv2.heyiris.io/api/v1/integrations-temp/oauth-callback/' . $type,
                    '  4. Set GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_CLIENT_SECRET in fl-api .env',
                ]);
            }

            return Command::FAILURE;
        }
    }

    protected function connectApiKey(SymfonyStyle $io, string $type): int
    {
        $instructions = [
            'vapi' => ['url' => 'https://dashboard.vapi.ai', 'field' => 'API Key'],
            'servis-ai' => ['url' => 'https://freeagent.network', 'field' => 'Client ID + Secret'],
            'openai' => ['url' => 'https://platform.openai.com/api-keys', 'field' => 'API Key'],
        ];

        $info = $instructions[$type] ?? null;

        $io->text("{$type} uses API key authentication.");
        $io->newLine();

        if ($info) {
            $io->text("Get your credentials: <fg=cyan>{$info['url']}</>");
            $io->newLine();
        }

        $io->text([
            'Connect interactively:',
            "  <fg=cyan>iris integrations connect {$type}</>",
            '',
            'The interactive command will prompt you for the credentials.',
        ]);

        return Command::SUCCESS;
    }

    // ─── List Functions for an Integration ──────────────────────

    protected function listFunctions(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $type): int
    {
        try {
            $functions = $iris->integrations->getFunctions($type);

            if ($input->getOption('json')) {
                $io->writeln(json_encode($functions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return Command::SUCCESS;
            }

            $items = is_array($functions) ? $functions : [];

            if (empty($items)) {
                $io->text("No functions found for <fg=cyan>{$type}</>.");

                return Command::SUCCESS;
            }

            $io->title("Functions: {$type}");

            foreach ($items as $fn) {
                $data = is_object($fn) ? (array) $fn : $fn;
                $name = $data['name'] ?? $data['function'] ?? '?';
                $desc = $data['description'] ?? '';
                $io->writeln("  <fg=cyan>{$name}</>");
                if ($desc) {
                    $io->writeln("    {$desc}");
                }

                // Show parameters if available
                $params = $data['parameters'] ?? $data['params'] ?? [];
                if (! empty($params)) {
                    foreach ($params as $pName => $pDef) {
                        if (is_array($pDef)) {
                            $required = ($pDef['required'] ?? false) ? ' <fg=red>*</>' : '';
                            $pType = $pDef['type'] ?? '';
                            $pDesc = $pDef['description'] ?? '';
                            $io->writeln("      {$pName}{$required} <fg=gray>({$pType})</> {$pDesc}");
                        }
                    }
                }
                $io->newLine();
            }

            $io->text("Run: iris run {$type} <function> key=value ...");
        } catch (\Exception $e) {
            $io->warning("Could not fetch functions for {$type}: " . $e->getMessage());
            $io->text("Try executing directly: iris run {$type} <function_name> key=value ...");
        }

        return Command::SUCCESS;
    }

    // ─── Helpers ────────────────────────────────────────────────

    protected function isIntegration(string $target): bool
    {
        return in_array(strtolower($target), self::INTEGRATION_TYPES);
    }

    /**
     * Parse key=value pairs from CLI arguments.
     * Handles: key=value, key="value with spaces", key='value'
     */
    protected function parseParams(array $rawParams): array
    {
        $params = [];
        foreach ($rawParams as $param) {
            if (str_contains($param, '=')) {
                [$key, $value] = explode('=', $param, 2);
                // Strip surrounding quotes
                $value = trim($value, '"\'');
                // Try to parse as int/bool
                if (is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                } elseif ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                }
                $params[$key] = $value;
            }
        }

        return $params;
    }

    protected function displayResult(array $result, string $name, SymfonyStyle $io): void
    {
        $success = $result['success'] ?? ! isset($result['error']);

        if (! $success) {
            $io->error($result['error'] ?? 'Execution failed');

            return;
        }

        $io->success("{$name} completed");

        foreach ($result as $key => $value) {
            if (in_array($key, ['success', 'status'])) {
                continue;
            }

            if (is_string($value)) {
                $io->writeln("  <fg=yellow>{$key}</>: {$value}");
            } elseif (is_numeric($value) || is_bool($value)) {
                $display = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                $io->writeln("  <fg=yellow>{$key}</>: {$display}");
            } elseif (is_array($value)) {
                $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if (strlen($encoded) < 500) {
                    $io->writeln("  <fg=yellow>{$key}</>: {$encoded}");
                } else {
                    $io->writeln("  <fg=yellow>{$key}</>: [" . count($value) . ' items]');
                }
            }
        }
    }

    protected function buildIris(InputInterface $input, SymfonyStyle $io): ?IRIS
    {
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key')
            ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id')
            ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');
        $irisUrl = getenv('IRIS_URL') ?: $store->get('iris_url');

        if (! $apiKey) {
            $io->error('No API key. Run: iris setup');

            return null;
        }

        $options = [
            'api_key' => $apiKey,
            'user_id' => (int) $userId,
        ];
        if ($irisUrl) {
            $options['base_url'] = $irisUrl;
        }

        return new IRIS($options);
    }
}
