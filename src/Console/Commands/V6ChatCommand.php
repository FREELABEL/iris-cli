<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;
use IRIS\SDK\Resources\Chat\StreamEvent;

/**
 * V6 ReAct Loop Chat Command with streaming output.
 *
 * Usage:
 *   iris v6:chat <agent_id> <message>
 *   iris v6:chat 11 "Find restaurants near me"
 *   iris v6:chat 337 "Analyze my leads" --bloq=40 --max-iterations=5
 */
class V6ChatCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('v6:chat')
            ->setDescription('V6 ReAct Loop chat with streaming output')
            ->setHelp(<<<HELP
V6 ReAct Loop chat with real-time streaming events.

The V6 system uses:
- Single AI call per iteration (more efficient than V5)
- Model-driven termination (AI decides when done)
- Doom loop detection (prevents infinite loops)
- SSE streaming for real-time visibility

<info>Examples:</info>
  iris v6:chat 11 "Hello, what can you do?"
  iris v6:chat 337 "Find my recent emails" --bloq=40
  iris v6:chat 11 "Analyze this data" --max-iterations=5
  iris v6:chat 11 "Search for leads" --verbose

<info>Event Types:</info>
  🤔 thinking    - AI is reasoning
  🔧 tool_call   - Tool execution started
  ✅ tool_result - Tool completed
  📝 text        - Response text
  ⚠️ doom_loop   - Repetitive action detected
  ❌ error       - Error occurred
  📊 done        - Workflow completed

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
  IRIS_URL        (Optional) IRIS API URL
HELP
            )
            ->addArgument('agent_id', InputArgument::REQUIRED, 'Agent ID to chat with')
            ->addArgument('message', InputArgument::REQUIRED, 'Message to send')
            ->addOption('bloq', 'b', InputOption::VALUE_REQUIRED, 'Bloq ID for context')
            ->addOption('max-iterations', 'm', InputOption::VALUE_REQUIRED, 'Maximum ReAct iterations', '10')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output final result as JSON')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Show all events in detail')
            ->addOption('quiet', 'q', InputOption::VALUE_NONE, 'Only show final response')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('iris-url', null, InputOption::VALUE_REQUIRED, 'IRIS API URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Load credentials
        $store = new CredentialStore();

        $apiKey = $input->getOption('api-key')
            ?: getenv('IRIS_API_KEY')
            ?: $store->get('api_key');

        $userId = $input->getOption('user-id')
            ?: getenv('IRIS_USER_ID')
            ?: $store->get('user_id');

        $irisUrl = $input->getOption('iris-url')
            ?: getenv('IRIS_URL')
            ?: $store->get('iris_url');

        // Try .env fallback
        if (!$apiKey || !$userId) {
            try {
                $tempConfig = new \IRIS\SDK\Config([]);
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
            $io->error('Missing credentials. Run "iris setup" or provide --api-key and --user-id');
            return Command::FAILURE;
        }

        // Initialize SDK
        $options = [
            'api_key' => $apiKey,
            'user_id' => $userId,
        ];

        if ($irisUrl) {
            $options['base_url'] = $irisUrl;
        }

        $iris = new IRIS($options);

        // Get chat options
        $agentId = (int) $input->getArgument('agent_id');
        $message = $input->getArgument('message');
        $bloqId = $input->getOption('bloq');
        $maxIterations = (int) $input->getOption('max-iterations');
        $isJson = $input->getOption('json');
        $isVerbose = $input->getOption('verbose');
        $isQuiet = $input->getOption('quiet');

        // Build chat options
        $chatOptions = [
            'query' => $message,
            'agentId' => $agentId,
            'maxIterations' => $maxIterations,
        ];

        if ($bloqId) {
            $chatOptions['bloqId'] = (int) $bloqId;
        }

        if (!$isQuiet && !$isJson) {
            $io->title('V6 ReAct Loop Chat');
            $io->text([
                "Agent: #{$agentId}",
                "Message: {$message}",
                "Max Iterations: {$maxIterations}",
            ]);
            $io->newLine();
        }

        try {
            // Use executeV6 with progress callback
            $result = $iris->chat->executeV6($chatOptions, function (StreamEvent $event) use ($io, $isQuiet, $isVerbose, $isJson) {
                if ($isQuiet || $isJson) {
                    return;
                }

                $this->displayEvent($io, $event, $isVerbose);
            });

            // Output result
            if ($isJson) {
                $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT));
            } else {
                $io->newLine();
                $io->section('Response');
                $io->text($result->content);

                $io->newLine();
                $io->section('Summary');
                $io->text($result->getSummary());

                if ($result->isDoomLoop()) {
                    $io->warning("Doom loop detected: {$result->doomLoopTool}");
                }

                if ($result->isMaxIterations()) {
                    $io->warning("Max iterations reached ({$result->iterations}/{$result->maxIterations})");
                }
            }

            return $result->isSuccess() ? Command::SUCCESS : Command::FAILURE;

        } catch (\Exception $e) {
            if ($isJson) {
                $output->writeln(json_encode([
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ]));
            } else {
                $io->error('V6 chat failed: ' . $e->getMessage());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Display a stream event.
     */
    protected function displayEvent(SymfonyStyle $io, StreamEvent $event, bool $verbose): void
    {
        switch ($event->type) {
            case StreamEvent::TYPE_ITERATION:
                $io->text("🔄 <fg=cyan>Iteration {$event->iteration}</>");
                break;

            case StreamEvent::TYPE_THINKING:
                if ($verbose) {
                    $io->text("🤔 <fg=gray>{$event->content}</>");
                }
                break;

            case StreamEvent::TYPE_TOOL_CALL:
                $desc = $event->description ? ": {$event->description}" : '';
                $io->text("🔧 <fg=yellow>Calling {$event->tool}</>{$desc}");

                if ($verbose && $event->arguments) {
                    $io->text("   <fg=gray>" . json_encode($event->arguments) . "</>");
                }
                break;

            case StreamEvent::TYPE_TOOL_RESULT:
                $status = $event->result['status'] ?? 'unknown';
                $icon = $status === 'success' ? '✅' : '⚠️';
                $io->text("{$icon} <fg=green>{$event->tool} completed</>");

                if ($verbose && $event->result) {
                    $preview = json_encode($event->result);
                    if (strlen($preview) > 200) {
                        $preview = substr($preview, 0, 200) . '...';
                    }
                    $io->text("   <fg=gray>{$preview}</>");
                }
                break;

            case StreamEvent::TYPE_TEXT:
                if ($verbose && $event->content) {
                    $io->text("📝 {$event->content}");
                }
                break;

            case StreamEvent::TYPE_DOOM_LOOP:
                $io->text("⚠️ <fg=red>Doom loop detected: {$event->tool}</>");
                break;

            case StreamEvent::TYPE_ERROR:
                $io->text("❌ <fg=red>Error: {$event->error}</>");
                break;

            case StreamEvent::TYPE_DONE:
                $io->text("📊 <fg=green>Done</> - {$event->status} | Iterations: {$event->iteration}");
                break;
        }
    }
}
