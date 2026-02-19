<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * RemindR Command - Appointment Reminder Management
 *
 * Provides CLI tools for managing appointment reminders.
 * RemindR uses atomic, composable functions that AI agents can call.
 */
class RemindRCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('remindr')
            ->setDescription('Manage appointment reminders')
            ->setHelp(<<<'EOT'
Manage appointment reminders with atomic, composable functions.

Usage:
  iris remindr list                      # List all reminders
  iris remindr create                    # Create a reminder (interactive)
  iris remindr show <id>                 # Show reminder details
  iris remindr activate <id>             # Activate a draft reminder
  iris remindr pause <id>                # Pause an active reminder
  iris remindr delete <id>               # Delete a reminder
  iris remindr add-timing <id> --offset=24h    # Add timing
  iris remindr add-channel <id> --channel=sms  # Add channel
  iris remindr send <id>                 # Send reminder immediately
  iris remindr defaults                  # Show/set default settings

Options:
  --json          Output as JSON
  --user-id       User ID (overrides .env)
  --api-key       API key (overrides .env)
EOT
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, create, show, activate, pause, delete, add-timing, add-channel, send, defaults', 'list')
            ->addArgument('id', InputArgument::OPTIONAL, 'Reminder ID (for show, activate, pause, delete, add-timing, add-channel, send)')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Timing offset (e.g., 24h, 1h, 30m, 1w, 2d)')
            ->addOption('channel', null, InputOption::VALUE_REQUIRED, 'Channel type (sms, email)')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Custom message template')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status (draft, active, paused, completed, cancelled)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Limit results', 20)
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';

        try {
            // Initialize SDK
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }

            $config = new Config($configOptions);
            $iris = new IRIS($configOptions);

            // Route to appropriate handler
            switch ($action) {
                case 'list':
                    return $this->listReminders($iris, $io, $input, $config);
                case 'create':
                    return $this->createReminder($iris, $io, $input, $output, $config);
                case 'show':
                    return $this->showReminder($iris, $io, $input, $config);
                case 'activate':
                    return $this->activateReminder($iris, $io, $input, $config);
                case 'pause':
                    return $this->pauseReminder($iris, $io, $input, $config);
                case 'delete':
                    return $this->deleteReminder($iris, $io, $input, $output, $config);
                case 'add-timing':
                    return $this->addTiming($iris, $io, $input, $config);
                case 'add-channel':
                    return $this->addChannel($iris, $io, $input, $config);
                case 'send':
                    return $this->sendNow($iris, $io, $input, $config);
                case 'defaults':
                    return $this->manageDefaults($iris, $io, $input, $output, $config);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text("Available actions: list, create, show, activate, pause, delete, add-timing, add-channel, send, defaults");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Call RemindR integration function via FL-API execute endpoint
     */
    private function callRemindR(Config $config, string $function, array $params = []): array
    {
        $baseUrl = rtrim($config->flApiUrl ?? 'http://localhost:8000', '/');
        $userId = $config->userId ?? 193;

        // Use user-scoped route for SDK compatibility (no auth:api middleware required)
        $url = "{$baseUrl}/api/v1/users/{$userId}/integrations/execute";

        $payload = [
            'integration' => 'remindr',
            'action' => $function,
            'parameters' => $params,
            'user_id' => $userId, // Required for SDK compatibility
        ];

        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $config->apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ];

        // Disable SSL verification for local development
        if (strpos($url, 'local.') !== false) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("API request failed: {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $message = $data['message'] ?? $data['error'] ?? "HTTP {$httpCode} error";
            throw new \RuntimeException("API error: {$message}");
        }

        return $data;
    }

    private function listReminders(IRIS $iris, SymfonyStyle $io, InputInterface $input, Config $config): int
    {
        $io->title('Appointment Reminders');

        $params = [
            'limit' => (int)($input->getOption('limit') ?? 20),
        ];

        if ($status = $input->getOption('status')) {
            $params['status'] = $status;
        }

        $result = $this->callRemindR($config, 'list_reminders', $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $reminders = $result['data']['reminders'] ?? $result['reminders'] ?? [];

        if (empty($reminders)) {
            $io->warning('No reminders found.');
            $io->newLine();
            $io->text([
                'Create a new reminder:',
                '  <info>iris remindr create</info>',
            ]);
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($reminders as $reminder) {
            $statusIcon = match($reminder['status'] ?? 'draft') {
                'active' => '<fg=green>Active</>',
                'draft' => '<fg=yellow>Draft</>',
                'paused' => '<fg=gray>Paused</>',
                'completed' => '<fg=cyan>Completed</>',
                'cancelled' => '<fg=red>Cancelled</>',
                default => $reminder['status'] ?? 'unknown',
            };

            $rows[] = [
                $reminder['id'],
                $reminder['client_name'] ?? 'N/A',
                $reminder['service_name'] ?? 'N/A',
                $reminder['appointment_time'] ?? 'N/A',
                $statusIcon,
                count($reminder['timings'] ?? []) . ' timing(s)',
                count($reminder['channels'] ?? []) . ' channel(s)',
            ];
        }

        $io->table(
            ['ID', 'Client', 'Service', 'Appointment', 'Status', 'Timings', 'Channels'],
            $rows
        );

        $io->text(sprintf('Total: <info>%d</info> reminder(s)', count($reminders)));

        return Command::SUCCESS;
    }

    private function createReminder(IRIS $iris, SymfonyStyle $io, InputInterface $input, OutputInterface $output, Config $config): int
    {
        $io->title('Create Appointment Reminder');

        $helper = $this->getHelper('question');

        // Collect reminder details interactively
        $clientName = $helper->ask($input, $output, new Question('Client Name: '));
        if (!$clientName) {
            $io->error('Client name is required');
            return Command::FAILURE;
        }

        $clientPhone = $helper->ask($input, $output, new Question('Client Phone (for SMS): '));
        $clientEmail = $helper->ask($input, $output, new Question('Client Email: '));

        if (!$clientPhone && !$clientEmail) {
            $io->error('At least one contact method (phone or email) is required');
            return Command::FAILURE;
        }

        $serviceName = $helper->ask($input, $output, new Question('Service Name: '));

        $appointmentTime = $helper->ask($input, $output, new Question('Appointment Time (YYYY-MM-DD HH:MM): '));
        if (!$appointmentTime) {
            $io->error('Appointment time is required');
            return Command::FAILURE;
        }

        $providerName = $helper->ask($input, $output, new Question('Provider/Staff Name (optional): '));
        $location = $helper->ask($input, $output, new Question('Location (optional): '));

        $io->text('Creating reminder...');

        $params = [
            'client_name' => $clientName,
            'service_name' => $serviceName,
            'appointment_time' => $appointmentTime,
        ];

        if ($clientPhone) $params['client_phone'] = $clientPhone;
        if ($clientEmail) $params['client_email'] = $clientEmail;
        if ($providerName) $params['provider_name'] = $providerName;
        if ($location) $params['location'] = $location;

        $result = $this->callRemindR($config, 'create_reminder', $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $reminder = $result['data']['reminder'] ?? $result['reminder'] ?? $result;

        $io->success("Reminder created successfully!");
        $io->definitionList(
            ['ID' => $reminder['id'] ?? 'N/A'],
            ['Client' => $reminder['client_name'] ?? $clientName],
            ['Service' => $reminder['service_name'] ?? $serviceName],
            ['Appointment' => $reminder['appointment_time'] ?? $appointmentTime],
            ['Status' => $reminder['status'] ?? 'draft']
        );

        $io->newLine();
        $io->text([
            'Next steps:',
            '  <info>iris remindr add-timing ' . ($reminder['id'] ?? '<id>') . ' --offset=24h</info>  # Add 24h before timing',
            '  <info>iris remindr add-channel ' . ($reminder['id'] ?? '<id>') . ' --channel=sms</info>  # Add SMS channel',
            '  <info>iris remindr activate ' . ($reminder['id'] ?? '<id>') . '</info>  # Activate for delivery',
        ]);

        return Command::SUCCESS;
    }

    private function showReminder(IRIS $iris, SymfonyStyle $io, InputInterface $input, Config $config): int
    {
        $id = $input->getArgument('id');

        if (!$id) {
            $io->error('Please specify a reminder ID');
            $io->text('Example: <info>iris remindr show 123</info>');
            return Command::FAILURE;
        }

        $result = $this->callRemindR($config, 'get_reminder', ['reminder_id' => (int)$id]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $reminder = $result['data']['reminder'] ?? $result['reminder'] ?? $result;

        $io->title("Reminder #{$id}");

        $statusIcon = match($reminder['status'] ?? 'draft') {
            'active' => '<fg=green>Active</>',
            'draft' => '<fg=yellow>Draft</>',
            'paused' => '<fg=gray>Paused</>',
            'completed' => '<fg=cyan>Completed</>',
            'cancelled' => '<fg=red>Cancelled</>',
            default => $reminder['status'] ?? 'unknown',
        };

        $io->section('Details');
        $io->definitionList(
            ['Status' => $statusIcon],
            ['Client' => $reminder['client_name'] ?? 'N/A'],
            ['Phone' => $reminder['client_phone'] ?? 'N/A'],
            ['Email' => $reminder['client_email'] ?? 'N/A'],
            ['Service' => $reminder['service_name'] ?? 'N/A'],
            ['Provider' => $reminder['provider_name'] ?? 'N/A'],
            ['Appointment' => $reminder['appointment_time'] ?? 'N/A'],
            ['Location' => $reminder['location'] ?? 'N/A'],
            ['Source' => $reminder['source_integration'] ?? 'manual'],
            ['Created' => $reminder['created_at'] ?? 'N/A']
        );

        // Show timings
        $timings = $reminder['timings'] ?? [];
        if (!empty($timings)) {
            $io->section('Scheduled Timings');
            $timingRows = [];
            foreach ($timings as $timing) {
                $timingStatus = match($timing['status'] ?? 'pending') {
                    'sent' => '<fg=green>Sent</>',
                    'pending' => '<fg=yellow>Pending</>',
                    'failed' => '<fg=red>Failed</>',
                    'skipped' => '<fg=gray>Skipped</>',
                    default => $timing['status'] ?? 'unknown',
                };
                $timingRows[] = [
                    $timing['id'] ?? 'N/A',
                    $timing['offset_value'] ?? 'Absolute',
                    $timing['scheduled_at'] ?? 'N/A',
                    $timingStatus,
                ];
            }
            $io->table(['ID', 'Offset', 'Scheduled At', 'Status'], $timingRows);
        } else {
            $io->note('No timings configured. Add one with: iris remindr add-timing ' . $id . ' --offset=24h');
        }

        // Show channels
        $channels = $reminder['channels'] ?? [];
        if (!empty($channels)) {
            $io->section('Delivery Channels');
            $channelRows = [];
            foreach ($channels as $channel) {
                $channelIcon = $channel['channel'] === 'sms' ? 'SMS' : 'Email';
                $channelRows[] = [
                    $channel['id'] ?? 'N/A',
                    $channelIcon,
                    !empty($channel['template']) || !empty($channel['has_custom_template']) ? 'Custom' : 'Default',
                ];
            }
            $io->table(['ID', 'Channel', 'Template'], $channelRows);
        } else {
            $io->note('No channels configured. Add one with: iris remindr add-channel ' . $id . ' --channel=sms');
        }

        return Command::SUCCESS;
    }

    private function activateReminder(IRIS $iris, SymfonyStyle $io, InputInterface $input, Config $config): int
    {
        $id = $input->getArgument('id');

        if (!$id) {
            $io->error('Please specify a reminder ID');
            $io->text('Example: <info>iris remindr activate 123</info>');
            return Command::FAILURE;
        }

        $io->text("Activating reminder #{$id}...");

        $result = $this->callRemindR($config, 'activate_reminder', ['reminder_id' => (int)$id]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Reminder #{$id} activated successfully!");
        $io->text('The reminder will be sent at the scheduled times.');

        return Command::SUCCESS;
    }

    private function pauseReminder(IRIS $iris, SymfonyStyle $io, InputInterface $input, Config $config): int
    {
        $id = $input->getArgument('id');

        if (!$id) {
            $io->error('Please specify a reminder ID');
            $io->text('Example: <info>iris remindr pause 123</info>');
            return Command::FAILURE;
        }

        $io->text("Pausing reminder #{$id}...");

        $result = $this->callRemindR($config, 'pause_reminder', ['reminder_id' => (int)$id]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Reminder #{$id} paused successfully!");
        $io->text('Reactivate with: <info>iris remindr activate ' . $id . '</info>');

        return Command::SUCCESS;
    }

    private function deleteReminder(IRIS $iris, SymfonyStyle $io, InputInterface $input, OutputInterface $output, Config $config): int
    {
        $id = $input->getArgument('id');

        if (!$id) {
            $io->error('Please specify a reminder ID');
            $io->text('Example: <info>iris remindr delete 123</info>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion("Are you sure you want to delete reminder #{$id}? (y/N) ", false);

        if (!$helper->ask($input, $output, $question)) {
            $io->text('Cancelled.');
            return Command::SUCCESS;
        }

        $io->text("Deleting reminder #{$id}...");

        $result = $this->callRemindR($config, 'delete_reminder', ['reminder_id' => (int)$id]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Reminder #{$id} deleted successfully!");

        return Command::SUCCESS;
    }

    private function addTiming(IRIS $iris, SymfonyStyle $io, InputInterface $input, Config $config): int
    {
        $id = $input->getArgument('id');
        $offset = $input->getOption('offset');

        if (!$id) {
            $io->error('Please specify a reminder ID');
            $io->text('Example: <info>iris remindr add-timing 123 --offset=24h</info>');
            return Command::FAILURE;
        }

        if (!$offset) {
            $io->error('Please specify a timing offset');
            $io->text([
                'Examples:',
                '  <info>iris remindr add-timing 123 --offset=24h</info>  # 24 hours before',
                '  <info>iris remindr add-timing 123 --offset=1h</info>   # 1 hour before',
                '  <info>iris remindr add-timing 123 --offset=30m</info>  # 30 minutes before',
                '  <info>iris remindr add-timing 123 --offset=1w</info>   # 1 week before',
                '  <info>iris remindr add-timing 123 --offset=2d</info>   # 2 days before',
            ]);
            return Command::FAILURE;
        }

        $io->text("Adding {$offset} timing to reminder #{$id}...");

        $result = $this->callRemindR($config, 'add_timing', [
            'reminder_id' => (int)$id,
            'offset' => $offset,
        ]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $timing = $result['data']['timing'] ?? $result['timing'] ?? $result;

        $io->success("Timing added successfully!");
        $io->definitionList(
            ['Timing ID' => $timing['id'] ?? 'N/A'],
            ['Offset' => $offset],
            ['Scheduled At' => $timing['scheduled_at'] ?? 'N/A']
        );

        return Command::SUCCESS;
    }

    private function addChannel(IRIS $iris, SymfonyStyle $io, InputInterface $input, Config $config): int
    {
        $id = $input->getArgument('id');
        $channel = $input->getOption('channel');
        $template = $input->getOption('template');

        if (!$id) {
            $io->error('Please specify a reminder ID');
            $io->text('Example: <info>iris remindr add-channel 123 --channel=sms</info>');
            return Command::FAILURE;
        }

        if (!$channel) {
            $io->error('Please specify a channel type');
            $io->text([
                'Examples:',
                '  <info>iris remindr add-channel 123 --channel=sms</info>',
                '  <info>iris remindr add-channel 123 --channel=email</info>',
                '  <info>iris remindr add-channel 123 --channel=sms --template="Reminder: {{service_name}} at {{appointment_time}}"</info>',
            ]);
            return Command::FAILURE;
        }

        if (!in_array($channel, ['sms', 'email'])) {
            $io->error("Invalid channel type: {$channel}. Use 'sms' or 'email'.");
            return Command::FAILURE;
        }

        $io->text("Adding {$channel} channel to reminder #{$id}...");

        $params = [
            'reminder_id' => (int)$id,
            'channel' => $channel,
        ];

        if ($template) {
            $params['template'] = $template;
        }

        $result = $this->callRemindR($config, 'add_channel', $params);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $channelData = $result['data']['channel'] ?? $result['channel'] ?? $result;

        $io->success("Channel added successfully!");
        $io->definitionList(
            ['Channel ID' => $channelData['id'] ?? 'N/A'],
            ['Type' => strtoupper($channel)],
            ['Template' => $template ? 'Custom' : 'Default']
        );

        return Command::SUCCESS;
    }

    private function sendNow(IRIS $iris, SymfonyStyle $io, InputInterface $input, Config $config): int
    {
        $id = $input->getArgument('id');

        if (!$id) {
            $io->error('Please specify a reminder ID');
            $io->text('Example: <info>iris remindr send 123</info>');
            return Command::FAILURE;
        }

        $io->text("Sending reminder #{$id} immediately...");

        $result = $this->callRemindR($config, 'send_now', ['reminder_id' => (int)$id]);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Reminder #{$id} sent successfully!");

        // Show delivery status if available
        $deliveryStatus = $result['data']['delivery_status'] ?? $result['delivery_status'] ?? null;
        if ($deliveryStatus) {
            $io->section('Delivery Status');
            foreach ($deliveryStatus as $channel => $status) {
                $statusIcon = ($status['success'] ?? false) ? '<fg=green>Sent</>' : '<fg=red>Failed</>';
                $io->text("  {$channel}: {$statusIcon}");
            }
        }

        return Command::SUCCESS;
    }

    private function manageDefaults(IRIS $iris, SymfonyStyle $io, InputInterface $input, OutputInterface $output, Config $config): int
    {
        $io->title('RemindR Default Settings');

        // Get current defaults
        $result = $this->callRemindR($config, 'get_defaults', []);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $settings = $result['data']['settings'] ?? $result['settings'] ?? $result;

        $io->section('Current Defaults');
        $io->definitionList(
            ['Enabled' => ($settings['enabled'] ?? true) ? 'Yes' : 'No'],
            ['Default Timings' => implode(', ', $settings['default_timings'] ?? ['24h', '1h'])],
            ['Default Channels' => implode(', ', $settings['default_channels'] ?? ['sms', 'email'])],
            ['Timezone' => $settings['timezone'] ?? 'America/New_York']
        );

        if (!empty($settings['sms_template'])) {
            $io->section('SMS Template');
            $io->text($settings['sms_template']);
        }

        if (!empty($settings['email_template'])) {
            $io->section('Email Template');
            $io->text($settings['email_template']);
        }

        $io->newLine();
        $io->text([
            'To update defaults, use sdk:call directly:',
            '  <info>iris sdk:call remindr.set_defaults default_timings=\'["24h","2h"]\' default_channels=\'["sms"]\'</info>',
        ]);

        return Command::SUCCESS;
    }
}
