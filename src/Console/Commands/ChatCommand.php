<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\ProgressBar;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * Interactive chat command with real-time progress display.
 *
 * Usage:
 *   iris chat <agent_id> <message>
 *   iris chat 11 "What can you do?"
 *   iris chat 337 "Analyze this data" --bloq=40
 */
class ChatCommand extends Command
{
    private const SPINNER_FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
    private const STATUS_ICONS = [
        'running' => '⏳',
        'completed' => '✅',
        'failed' => '❌',
        'paused' => '⏸️',
    ];

    protected function configure(): void
    {
        $this
            ->setName('chat')
            ->setDescription('Chat with an AI agent')
            ->setHelp(<<<HELP
Interactive chat with AI agents using real-time progress display.

<info>Examples:</info>
  iris chat 11 "Hello, what can you do?"
  iris chat 337 "Analyze my leads" --bloq=40
  iris chat 11 "Generate a report" --json

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
  IRIS_URL        (Optional) IRIS API URL
HELP
            )
            ->addArgument('agent_id', InputArgument::REQUIRED, 'Agent ID to chat with')
            ->addArgument('message', InputArgument::REQUIRED, 'Message to send')
            ->addOption('bloq', 'b', InputOption::VALUE_REQUIRED, 'Bloq ID for context')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('no-progress', null, InputOption::VALUE_NONE, 'Disable progress display')
            ->addOption('timeout', 't', InputOption::VALUE_REQUIRED, 'Timeout in seconds', '300')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('iris-url', null, InputOption::VALUE_REQUIRED, 'IRIS API URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load credentials from store first, then override with CLI options/env vars
        $store = new CredentialStore();

        // Try to load from .env first, then check other sources
        // Priority: .env > CLI options > env vars > stored credentials
        $apiKey = $input->getOption('api-key')
            ?: getenv('IRIS_API_KEY')
            ?: $store->get('api_key');

        $userId = $input->getOption('user-id')
            ?: getenv('IRIS_USER_ID')
            ?: $store->get('user_id');

        $irisUrl = $input->getOption('iris-url')
            ?: getenv('IRIS_URL')
            ?: $store->get('iris_url');

        // If still no credentials, try to initialize SDK to let Config load from .env
        if (!$apiKey || !$userId) {
            try {
                // Attempt to load from .env via Config
                $tempConfig = new \IRIS\SDK\Config([]);
                if (!$apiKey && isset($tempConfig->apiKey)) {
                    $apiKey = $tempConfig->apiKey;
                }
                if (!$userId && isset($tempConfig->userId)) {
                    $userId = $tempConfig->userId;
                }
            } catch (\Exception $e) {
                // Config will throw if api_key not found, that's ok
            }
        }

        if (!$apiKey || !$userId) {
            $io->error([
                'Missing API credentials.',
                '',
                'Run "iris config setup" to configure credentials, or set environment variables:',
                '  IRIS_API_KEY=your-api-key',
                '  IRIS_USER_ID=your-user-id',
            ]);
            return Command::FAILURE;
        }

        $agentId = $input->getArgument('agent_id');
        $message = $input->getArgument('message');
        $bloqId = $input->getOption('bloq');
        $timeout = (int) $input->getOption('timeout');
        $jsonOutput = $input->getOption('json');
        $showProgress = !$input->getOption('no-progress') && !$jsonOutput;

        try {
            // Initialize SDK with credentials from all sources
            $options = [
                'api_key' => $apiKey,
                'user_id' => (int) $userId,
                'max_polling_duration' => $timeout,
            ];

            if ($irisUrl) {
                $options['iris_url'] = $irisUrl;
            }

            // Add OAuth credentials if available (from store)
            $clientId = $store->get('client_id');
            $clientSecret = $store->get('client_secret');
            if ($clientId && $clientSecret) {
                $options['client_id'] = $clientId;
                $options['client_secret'] = $clientSecret;
            }

            // Add base_url if stored
            $baseUrl = $store->get('base_url');
            if ($baseUrl) {
                $options['base_url'] = $baseUrl;
            }

            $iris = new IRIS($options);

            // Show initial info (unless JSON output)
            if ($showProgress) {
                $this->showHeader($output, $agentId, $message, $bloqId);
            }

            // Prepare chat options
            $chatOptions = [
                'query' => $message,
                'agentId' => $agentId,
            ];

            if ($bloqId) {
                $chatOptions['bloqId'] = $bloqId;
            }

            // Execute with progress tracking
            $spinnerFrame = 0;
            $lastStatus = '';
            $startTime = microtime(true);

            $result = $iris->chat->execute($chatOptions, function ($status) use (
                $output,
                $showProgress,
                &$spinnerFrame,
                &$lastStatus,
                $startTime
            ) {
                if (!$showProgress) {
                    return;
                }

                $currentStatus = $status['status'] ?? 'unknown';
                $elapsed = round(microtime(true) - $startTime, 1);
                $spinner = self::SPINNER_FRAMES[$spinnerFrame % count(self::SPINNER_FRAMES)];
                $spinnerFrame++;

                // Build status line
                $icon = self::STATUS_ICONS[$currentStatus] ?? '❓';
                $statusText = ucfirst($currentStatus);

                // Clear previous line and write new status
                $output->write("\r\033[K"); // Clear line
                $output->write(sprintf(
                    "<fg=cyan>%s</> <fg=yellow>%s %s</> <fg=gray>(%ss)</>",
                    $spinner,
                    $icon,
                    $statusText,
                    $elapsed
                ));

                $lastStatus = $currentStatus;
            });

            // Clear progress line
            if ($showProgress) {
                $output->write("\r\033[K");
            }

            // Calculate metrics
            $elapsed = round(microtime(true) - $startTime, 2);

            // Output result
            if ($jsonOutput) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->showResult($output, $io, $result, $elapsed);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            // Clear progress line on error
            if ($showProgress) {
                $output->write("\r\033[K");
            }

            $io->error([
                'Chat Error:',
                $e->getMessage(),
                '',
                'Error Type: ' . get_class($e),
                'Code: ' . $e->getCode(),
            ]);

            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Show initial header with request info.
     */
    private function showHeader(OutputInterface $output, string $agentId, string $message, ?string $bloqId): void
    {
        $output->writeln('');
        $output->writeln('<fg=bright-blue>╭─────────────────────────────────────────────────────────────╮</>');
        $output->writeln(sprintf(
            '<fg=bright-blue>│</> <fg=white;options=bold>🤖 Agent #%s</> %s <fg=bright-blue>│</>',
            $agentId,
            $bloqId ? "<fg=gray>(Bloq: {$bloqId})</>" : ''
        ));
        $output->writeln('<fg=bright-blue>╰─────────────────────────────────────────────────────────────╯</>');
        $output->writeln('');

        // Show truncated message
        $displayMessage = strlen($message) > 60 ? substr($message, 0, 57) . '...' : $message;
        $output->writeln(sprintf('<fg=gray>📤 Sending:</> <fg=white>"%s"</>', $displayMessage));
        $output->writeln('');
    }

    /**
     * Show the result in a beautiful box.
     */
    private function showResult(OutputInterface $output, SymfonyStyle $io, array $result, float $elapsed): void
    {
        $status = $result['status'] ?? 'unknown';
        $summary = $result['summary'] ?? null;
        $agentName = $result['agent_name'] ?? 'AI Agent';

        // Success header
        $icon = self::STATUS_ICONS[$status] ?? '✅';
        $output->writeln(sprintf('<fg=green>%s Complete!</>', $icon));
        $output->writeln('');

        // Response box
        $output->writeln('<fg=green>╭─────────────────────────────────────────────────────────────╮</>');

        if ($summary) {
            // Word wrap the summary
            $wrapped = wordwrap($summary, 59, "\n", true);
            $lines = explode("\n", $wrapped);

            foreach ($lines as $line) {
                $padding = 59 - mb_strlen($line);
                $output->writeln(sprintf(
                    '<fg=green>│</> %s%s <fg=green>│</>',
                    $line,
                    str_repeat(' ', max(0, $padding))
                ));
            }
        } else {
            $output->writeln('<fg=green>│</> <fg=gray>(No response content)</> <fg=green>│</>');
        }

        $output->writeln('<fg=green>╰─────────────────────────────────────────────────────────────╯</>');
        $output->writeln('');

        // Metrics footer
        $metrics = $result['metrics'] ?? [];
        $tokens = $metrics['total_tokens'] ?? 'N/A';
        $model = $metrics['model'] ?? 'unknown';

        $output->writeln(sprintf(
            '<fg=gray>📊 Tokens: %s | Time: %ss | Model: %s | Agent: %s</>',
            $tokens,
            $elapsed,
            $model,
            $agentName
        ));
        $output->writeln('');

        // Show HITL status if paused
        if ($status === 'paused' && ($result['requires_approval'] ?? false)) {
            $output->writeln('<fg=yellow>⚠️  Workflow paused - requires human approval</>');
            $output->writeln(sprintf(
                '<fg=gray>   Workflow ID: %s</>',
                $result['workflow_id'] ?? 'N/A'
            ));

            if ($pending = $result['pending_approval'] ?? null) {
                $output->writeln(sprintf(
                    '<fg=gray>   Step: %s</>',
                    $pending['step_name'] ?? 'N/A'
                ));
                if ($prompt = $pending['approval_prompt'] ?? null) {
                    $output->writeln(sprintf('<fg=gray>   Prompt: %s</>', $prompt));
                }
            }
            $output->writeln('');
        }
    }
}
