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
 * CLI command for per-lead outreach — mirrors the UI exactly.
 *
 * Usage:
 *   ./bin/iris outreach:send list 412                     # Show lead's outreach steps
 *   ./bin/iris outreach:send show 412 --step=1            # View step message
 *   ./bin/iris outreach:send vary 412 --step=1            # Generate AI variation
 *   ./bin/iris outreach:send vary 412 --step=1 --use      # Vary + save as new message
 *   ./bin/iris outreach:send personalize 412 --step=1     # Personalize for lead
 *   ./bin/iris outreach:send copy 412 --step=1            # Copy message to clipboard
 *   ./bin/iris outreach:send open 412 --step=1            # Copy + open Instagram profile
 *   ./bin/iris outreach:send complete 412 --step=1        # Mark step done
 *   ./bin/iris outreach:send send 412 --step=1            # Send email/SMS
 *   ./bin/iris outreach:send apply 412                    # Apply strategy template
 *   ./bin/iris outreach:send invalid 412 --step=1         # Mark cannot contact
 */
class OutreachSendCommand extends Command
{
    private const TYPE_ICONS = [
        'email'     => 'Email',
        'phone'     => 'Phone',
        'sms'       => 'SMS',
        'visit'     => 'Visit',
        'linkedin'  => 'LinkedIn',
        'social'    => 'Social',
        'instagram' => 'Instagram',
        'mail'      => 'Mail',
        'other'     => 'Other',
    ];

    private const INVALID_REASONS = [
        'Private Account',
        "Profile Doesn't Exist",
        "Can't DM",
        'Wrong Handle',
        'No Email Address',
        'Email Bounced',
        'Invalid Email',
        'Phone Disconnected',
        'Other',
    ];

    private const STRATEGIES = [
        'instagram-first' => 'Instagram First — social media leads with IG handles',
        'email-first'     => 'Email First — professional/B2B leads',
        'sms-first'       => 'SMS First — phone-only/local contacts',
        'multi-channel'   => 'Multi-Channel — high-value/VIP leads',
    ];

    protected function configure(): void
    {
        $this
            ->setName('outreach:send')
            ->setDescription('Per-lead outreach — view steps, vary, personalize, copy, send, complete')
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list|show|vary|personalize|copy|open|complete|send|apply|invalid', 'list')
            ->addArgument('lead_id', InputArgument::OPTIONAL, 'Lead ID')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Step number (1-based)')
            ->addOption('use', null, InputOption::VALUE_NONE, 'For vary/personalize: save result as new message')
            ->addOption('notes', null, InputOption::VALUE_REQUIRED, 'Notes for completion')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'Reason for invalid')
            ->addOption('strategy', null, InputOption::VALUE_REQUIRED, 'Strategy key or template ID')
            ->addOption('bloq', null, InputOption::VALUE_REQUIRED, 'Bloq ID (for custom strategy templates)')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Email subject (for send)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment: local or production')
            ->setHelp(<<<'HELP'
Per-lead outreach — mirrors the UI outreach panel exactly.

Usage:
  outreach:send list <lead_id>                              Show outreach steps + progress
  outreach:send show <lead_id> --step=1                     View step's full message
  outreach:send vary <lead_id> --step=1                     Generate AI variation of message
  outreach:send vary <lead_id> --step=1 --use               Vary + save as new message
  outreach:send personalize <lead_id> --step=1              Personalize message for this lead
  outreach:send personalize <lead_id> --step=1 --use        Personalize + save
  outreach:send copy <lead_id> --step=1                     Copy message to clipboard
  outreach:send open <lead_id>                              Open lead's Instagram profile
  outreach:send open <lead_id> --step=1                     Copy message + open profile
  outreach:send complete <lead_id> --step=1                 Mark step as done
  outreach:send send <lead_id> --step=1                     Send email/SMS for this step
  outreach:send apply <lead_id>                             Apply strategy template interactively
  outreach:send apply <lead_id> --strategy=instagram-first  Apply built-in strategy
  outreach:send invalid <lead_id> --step=1                  Mark lead as cannot contact

Examples:
  outreach:send 412                                         List steps (shorthand)
  outreach:send vary 412 --step=3 --use                     Vary step 3 + save
  outreach:send open 412 --step=3                           Copy step 3 message + open IG
  outreach:send complete 412 --step=3 --notes="Sent DM"     Mark done with notes

Related Commands:
  outreach:strategy list <bloq_id>                          Manage strategy templates
  outreach:campaign list --bloq=40                          Manage bulk campaigns
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // --- Credentials ---
        $store = new \IRIS\SDK\Auth\CredentialStore();
        $env = $input->getOption('env');
        if ($env) {
            putenv("IRIS_ENV={$env}");
        }

        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey) {
            $io->error('No API key found. Run: php bin/iris setup');
            return Command::FAILURE;
        }

        $iris = new IRIS(['api_key' => $apiKey, 'user_id' => $userId ? (int)$userId : null]);
        $http = $iris->getHttpClient();

        $action = $input->getArgument('action');
        $leadId = $input->getArgument('lead_id');

        // Handle shorthand: outreach:send 412 (action = numeric = lead_id)
        if (is_numeric($action) && !$leadId) {
            $leadId = $action;
            $action = 'list';
        }

        if (!$leadId) {
            $io->error('Lead ID is required. Usage: outreach:send <action> <lead_id>');
            return Command::FAILURE;
        }

        $isJson = $input->getOption('json');

        try {
            return match ($action) {
                'list'        => $this->listSteps($io, $http, $leadId, $isJson),
                'show'        => $this->showStep($io, $http, $input, $leadId, $isJson),
                'vary'        => $this->varyStep($io, $http, $input, $leadId, $isJson),
                'personalize' => $this->personalizeStep($io, $http, $input, $leadId, $isJson),
                'copy'        => $this->copyStep($io, $http, $input, $leadId),
                'open'        => $this->openProfile($io, $http, $input, $leadId),
                'complete'    => $this->completeStep($io, $http, $input, $leadId, $isJson),
                'send'        => $this->sendStep($io, $http, $input, $leadId, $isJson),
                'apply'       => $this->applyStrategy($io, $http, $input, $leadId, $isJson),
                'invalid'     => $this->markInvalid($io, $http, $input, $leadId, $isJson),
                default       => $this->unknownAction($io, $action),
            };
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function fetchLead($http, string $leadId): array
    {
        return $http->get("/api/v1/leads/{$leadId}");
    }

    private function fetchSteps($http, string $leadId): array
    {
        return $http->get("/api/v1/leads/{$leadId}/outreach-steps");
    }

    private function getStepByNumber(array $stepsData, int $stepNum): ?array
    {
        $steps = $stepsData['steps'] ?? $stepsData['data']['steps'] ?? $stepsData;
        if (is_array($steps) && isset($steps[$stepNum - 1])) {
            return $steps[$stepNum - 1];
        }
        return null;
    }

    private function getAllSteps(array $stepsData): array
    {
        return $stepsData['steps'] ?? $stepsData['data']['steps'] ?? $stepsData;
    }

    private function getStats(array $stepsData): array
    {
        return $stepsData['stats'] ?? $stepsData['data']['stats'] ?? [];
    }

    private function getLeadHandle(array $lead): ?string
    {
        $handle = $lead['twitter'] ?? $lead['contact_info']['social_handle'] ?? $lead['contact_info']['instagram'] ?? $lead['social_handle'] ?? null;
        if ($handle) {
            $handle = str_replace('@', '', $handle);
            // Strip URL prefixes
            if (str_contains($handle, 'instagram.com/')) {
                $handle = basename(parse_url($handle, PHP_URL_PATH) ?: $handle);
            }
        }
        return $handle;
    }

    private function getLeadName(array $lead): string
    {
        return $lead['nickname'] ?? $lead['name'] ?? $lead['first_name'] ?? 'Unknown';
    }

    private function getLeadEmail(array $lead): ?string
    {
        return $lead['email'] ?? $lead['contact_info']['email'] ?? null;
    }

    private function getLeadPhone(array $lead): ?string
    {
        return $lead['phone'] ?? $lead['contact_info']['phone'] ?? null;
    }

    private function requireStep(InputInterface $input, SymfonyStyle $io): ?int
    {
        $step = $input->getOption('step');
        if (!$step) {
            $io->error('--step=N is required for this action. Example: --step=1');
            return null;
        }
        return (int)$step;
    }

    private function progressBar(int $completed, int $total): string
    {
        if ($total === 0) return '[--------------------] 0%';
        $pct = (int)(($completed / $total) * 100);
        $filled = (int)(($completed / $total) * 20);
        $empty = 20 - $filled;
        return '[' . str_repeat('#', $filled) . str_repeat('-', $empty) . "] {$pct}%";
    }

    private function typeLabel(string $type): string
    {
        return self::TYPE_ICONS[$type] ?? ucfirst($type);
    }

    private function statusSymbol(bool $completed, bool $isNext = false): string
    {
        if ($completed) return '<fg=green>done</>';
        if ($isNext) return '<fg=yellow>next</>';
        return '<fg=gray>--</>';
    }

    // ─── Actions ─────────────────────────────────────────────────────────

    private function listSteps(SymfonyStyle $io, $http, string $leadId, bool $isJson): int
    {
        $lead = $this->fetchLead($http, $leadId);
        $stepsData = $this->fetchSteps($http, $leadId);
        $steps = $this->getAllSteps($stepsData);
        $stats = $this->getStats($stepsData);

        if ($isJson) {
            $io->writeln(json_encode(['lead' => $lead, 'steps' => $steps, 'stats' => $stats], JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $handle = $this->getLeadHandle($lead);
        $name = $this->getLeadName($lead);

        $io->newLine();
        $headerParts = [];
        if ($handle) $headerParts[] = "@{$handle}";
        $headerParts[] = $name;
        $io->writeln("  <fg=white;options=bold>Lead #{$leadId}: " . implode(' - ', $headerParts) . '</>');

        $completed = (int)($stats['completed'] ?? 0);
        $total = (int)($stats['total'] ?? count($steps));
        $io->writeln("  Progress: " . $this->progressBar($completed, $total) . " ({$completed}/{$total})");
        $io->newLine();

        if (empty($steps)) {
            $io->writeln('  <fg=gray>No outreach steps. Use: outreach:send apply ' . $leadId . ' to add a strategy.</>');
            return Command::SUCCESS;
        }

        // Find first incomplete step
        $nextIdx = null;
        foreach ($steps as $i => $s) {
            if (empty($s['is_completed'])) {
                $nextIdx = $i;
                break;
            }
        }

        $rows = [];
        foreach ($steps as $i => $step) {
            $isDone = !empty($step['is_completed']);
            $isNext = ($i === $nextIdx);
            $preview = $step['instructions'] ?? '';
            if (strlen($preview) > 70) {
                $preview = substr($preview, 0, 67) . '...';
            }

            $num = $i + 1;
            $type = $this->typeLabel($step['type'] ?? 'other');
            $title = $step['title'] ?? 'Untitled';
            $status = $this->statusSymbol($isDone, $isNext);

            if ($isDone) {
                $rows[] = ["  <fg=green>{$num}</>", "<fg=green>{$type}</>", "<fg=green>{$title}</>", $status, "<fg=gray>{$preview}</>"];
            } elseif ($isNext) {
                $rows[] = ["  <fg=yellow;options=bold>{$num}</>", "<fg=yellow>{$type}</>", "<fg=yellow;options=bold>{$title}</>", $status, $preview];
            } else {
                $rows[] = ["  {$num}", $type, $title, $status, "<fg=gray>{$preview}</>"];
            }
        }

        $io->table(['  #', 'Type', 'Title', 'Status', 'Message Preview'], $rows);
        $io->writeln('  <fg=gray>Tip: outreach:send show ' . $leadId . ' --step=N to view full message</>');

        return Command::SUCCESS;
    }

    private function showStep(SymfonyStyle $io, $http, InputInterface $input, string $leadId, bool $isJson): int
    {
        $stepNum = $this->requireStep($input, $io);
        if (!$stepNum) return Command::FAILURE;

        $stepsData = $this->fetchSteps($http, $leadId);
        $step = $this->getStepByNumber($stepsData, $stepNum);

        if (!$step) {
            $io->error("Step #{$stepNum} not found. This lead has " . count($this->getAllSteps($stepsData)) . " steps.");
            return Command::FAILURE;
        }

        if ($isJson) {
            $io->writeln(json_encode($step, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->newLine();
        $io->writeln("  <fg=white;options=bold>Step #{$stepNum}: " . ($step['title'] ?? 'Untitled') . '</>');
        $io->writeln("  Type: " . $this->typeLabel($step['type'] ?? 'other'));

        $isDone = !empty($step['is_completed']);
        $io->writeln("  Status: " . ($isDone ? '<fg=green>Completed</>' : '<fg=yellow>Pending</>'));

        if (!empty($step['due_date'])) {
            $io->writeln("  Due: " . $step['due_date']);
        }

        $io->newLine();
        $message = $step['instructions'] ?? '';
        if ($message) {
            $io->writeln('  <fg=cyan>--- Message ---</>');
            // Word-wrap the message for terminal display
            $wrapped = wordwrap($message, 80, "\n");
            foreach (explode("\n", $wrapped) as $line) {
                $io->writeln("  {$line}");
            }
            $io->writeln('  <fg=cyan>--- End ---</>');
            $io->writeln("  <fg=gray>" . strlen($message) . " characters</>");
        } else {
            $io->writeln('  <fg=gray>No message script for this step.</>');
        }

        if (!empty($step['notes'])) {
            $io->newLine();
            $io->writeln("  <fg=gray>Notes: " . $step['notes'] . '</>');
        }

        $io->newLine();
        $io->writeln('  <fg=gray>Actions:</>');
        $io->writeln("    <fg=gray>outreach:send vary {$leadId} --step={$stepNum}          Generate AI variation</>");
        $io->writeln("    <fg=gray>outreach:send personalize {$leadId} --step={$stepNum}   Personalize for this lead</>");
        $io->writeln("    <fg=gray>outreach:send copy {$leadId} --step={$stepNum}          Copy to clipboard</>");
        $io->writeln("    <fg=gray>outreach:send open {$leadId} --step={$stepNum}          Copy + open Instagram</>");
        $io->writeln("    <fg=gray>outreach:send complete {$leadId} --step={$stepNum}      Mark done</>");

        return Command::SUCCESS;
    }

    private function varyStep(SymfonyStyle $io, $http, InputInterface $input, string $leadId, bool $isJson): int
    {
        $stepNum = $this->requireStep($input, $io);
        if (!$stepNum) return Command::FAILURE;

        $lead = $this->fetchLead($http, $leadId);
        $stepsData = $this->fetchSteps($http, $leadId);
        $step = $this->getStepByNumber($stepsData, $stepNum);

        if (!$step) {
            $io->error("Step #{$stepNum} not found.");
            return Command::FAILURE;
        }

        $message = $step['instructions'] ?? '';
        if (!$message) {
            $io->error("Step #{$stepNum} has no message to vary.");
            return Command::FAILURE;
        }

        $io->writeln('  Generating variation...');

        $payload = [
            'original_message' => $message,
            'platform' => $step['type'] ?? 'social',
            'lead_name' => $this->getLeadName($lead),
        ];

        if (!empty($step['variation_prompt'])) {
            $payload['custom_prompt'] = $step['variation_prompt'];
        }

        $result = $http->post('/api/v1/ai/generate-variation', $payload);
        $variation = $result['variation'] ?? null;

        if (!$variation) {
            $io->error('Failed to generate variation.');
            return Command::FAILURE;
        }

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->newLine();
        $io->writeln('  <fg=cyan>--- Original ---</>');
        foreach (explode("\n", wordwrap($message, 80, "\n")) as $line) {
            $io->writeln("  {$line}");
        }
        $io->writeln("  <fg=gray>" . strlen($message) . " chars</>");

        $io->newLine();
        $io->writeln('  <fg=green>--- Variation ---</>');
        foreach (explode("\n", wordwrap($variation, 80, "\n")) as $line) {
            $io->writeln("  {$line}");
        }
        $io->writeln("  <fg=gray>" . strlen($variation) . " chars</>");

        if (!empty($result['used_custom_prompt'])) {
            $io->writeln('  <fg=gray>(Used custom variation prompt from strategy template)</>');
        }

        // Save if --use flag
        if ($input->getOption('use')) {
            $stepId = $step['id'];
            $http->put("/api/v1/leads/{$leadId}/outreach-steps/{$stepId}", [
                'instructions' => $variation,
            ]);
            $io->newLine();
            $io->success('Variation saved as new message for step #' . $stepNum);
        } else {
            $io->newLine();
            $io->writeln("  <fg=gray>Add --use to save this variation as the new message.</>");
        }

        return Command::SUCCESS;
    }

    private function personalizeStep(SymfonyStyle $io, $http, InputInterface $input, string $leadId, bool $isJson): int
    {
        $stepNum = $this->requireStep($input, $io);
        if (!$stepNum) return Command::FAILURE;

        $lead = $this->fetchLead($http, $leadId);
        $stepsData = $this->fetchSteps($http, $leadId);
        $step = $this->getStepByNumber($stepsData, $stepNum);

        if (!$step) {
            $io->error("Step #{$stepNum} not found.");
            return Command::FAILURE;
        }

        $message = $step['instructions'] ?? '';
        if (!$message) {
            $io->error("Step #{$stepNum} has no message to personalize.");
            return Command::FAILURE;
        }

        $handle = $this->getLeadHandle($lead);
        if (!$handle) {
            $io->warning('No social handle found for this lead. Falling back to variation.');
            // Fall back to regular variation
            return $this->varyStep($io, $http, $input, $leadId, $isJson);
        }

        $io->writeln("  Personalizing for @{$handle}...");

        $result = $http->post('/api/v1/ai/generate-personalized-message', [
            'script_message' => $message,
            'instagram_handle' => $handle,
            'lead_name' => $this->getLeadName($lead),
        ]);

        $personalized = $result['message'] ?? null;

        if (!$personalized) {
            $io->error('Failed to personalize. The profile may be private.');
            return Command::FAILURE;
        }

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->newLine();
        $io->writeln('  <fg=cyan>--- Original ---</>');
        foreach (explode("\n", wordwrap($message, 80, "\n")) as $line) {
            $io->writeln("  {$line}");
        }

        $io->newLine();
        $io->writeln('  <fg=magenta>--- Personalized ---</>');
        foreach (explode("\n", wordwrap($personalized, 80, "\n")) as $line) {
            $io->writeln("  {$line}");
        }
        $io->writeln("  <fg=gray>" . strlen($personalized) . " chars</>");

        // Show profile data if available
        if (!empty($result['profile_used'])) {
            $profile = $result['profile_used'];
            $io->newLine();
            $io->writeln('  <fg=gray>Profile data used:</>');
            if (!empty($profile['full_name'])) {
                $io->writeln("    Name: {$profile['full_name']}");
            }
            if (!empty($profile['followers'])) {
                $io->writeln("    Followers: " . number_format($profile['followers']));
            }
            $io->writeln("    Has bio: " . (!empty($profile['has_bio']) ? 'Yes' : 'No'));
        }

        // Save if --use flag
        if ($input->getOption('use')) {
            $stepId = $step['id'];
            $http->put("/api/v1/leads/{$leadId}/outreach-steps/{$stepId}", [
                'instructions' => $personalized,
            ]);
            $io->newLine();
            $io->success('Personalized message saved for step #' . $stepNum);
        } else {
            $io->newLine();
            $io->writeln("  <fg=gray>Add --use to save this as the new message.</>");
        }

        return Command::SUCCESS;
    }

    private function copyStep(SymfonyStyle $io, $http, InputInterface $input, string $leadId): int
    {
        $stepNum = $this->requireStep($input, $io);
        if (!$stepNum) return Command::FAILURE;

        $stepsData = $this->fetchSteps($http, $leadId);
        $step = $this->getStepByNumber($stepsData, $stepNum);

        if (!$step) {
            $io->error("Step #{$stepNum} not found.");
            return Command::FAILURE;
        }

        $message = $step['instructions'] ?? '';
        if (!$message) {
            $io->error("Step #{$stepNum} has no message to copy.");
            return Command::FAILURE;
        }

        // Copy to clipboard using pbcopy (macOS)
        $process = proc_open('pbcopy', [['pipe', 'r']], $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $message);
            fclose($pipes[0]);
            proc_close($process);
        }

        $io->success("Copied to clipboard! (" . strlen($message) . " characters)");
        $io->writeln("  <fg=gray>Step #{$stepNum}: " . ($step['title'] ?? 'Untitled') . '</>');

        return Command::SUCCESS;
    }

    private function openProfile(SymfonyStyle $io, $http, InputInterface $input, string $leadId): int
    {
        $lead = $this->fetchLead($http, $leadId);
        $handle = $this->getLeadHandle($lead);

        if (!$handle) {
            $io->error('No social handle found for this lead.');
            return Command::FAILURE;
        }

        // If --step provided, copy message first
        $stepNum = $input->getOption('step');
        if ($stepNum) {
            $stepsData = $this->fetchSteps($http, $leadId);
            $step = $this->getStepByNumber($stepsData, (int)$stepNum);
            if ($step && !empty($step['instructions'])) {
                $message = $step['instructions'];
                $process = proc_open('pbcopy', [['pipe', 'r']], $pipes);
                if (is_resource($process)) {
                    fwrite($pipes[0], $message);
                    fclose($pipes[0]);
                    proc_close($process);
                }
                $io->writeln("  Message copied! (" . strlen($message) . " chars)");
            }
        }

        $url = "https://www.instagram.com/{$handle}/";
        exec("open " . escapeshellarg($url));
        $io->success("Opened @{$handle} in browser");

        return Command::SUCCESS;
    }

    private function completeStep(SymfonyStyle $io, $http, InputInterface $input, string $leadId, bool $isJson): int
    {
        $stepNum = $this->requireStep($input, $io);
        if (!$stepNum) return Command::FAILURE;

        $stepsData = $this->fetchSteps($http, $leadId);
        $step = $this->getStepByNumber($stepsData, $stepNum);

        if (!$step) {
            $io->error("Step #{$stepNum} not found.");
            return Command::FAILURE;
        }

        if (!empty($step['is_completed'])) {
            $io->warning("Step #{$stepNum} is already completed.");
            return Command::SUCCESS;
        }

        $notes = $input->getOption('notes') ?? '';
        $stepId = $step['id'];

        $data = ['is_completed' => true];
        if ($notes) {
            $data['notes'] = $notes;
        }

        $result = $http->put("/api/v1/leads/{$leadId}/outreach-steps/{$stepId}", $data);

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        // Show updated progress
        $allSteps = $this->getAllSteps($stepsData);
        $total = count($allSteps);
        $completed = 1; // This one we just completed
        foreach ($allSteps as $s) {
            if (!empty($s['is_completed'])) {
                $completed++;
            }
        }

        $io->success("Step #{$stepNum} marked complete: " . ($step['title'] ?? 'Untitled'));
        $io->writeln("  Progress: " . $this->progressBar($completed, $total) . " ({$completed}/{$total})");

        if ($completed >= $total) {
            $io->newLine();
            $io->writeln('  <fg=green;options=bold>All steps complete! Outreach sequence finished.</>');
        }

        return Command::SUCCESS;
    }

    private function sendStep(SymfonyStyle $io, $http, InputInterface $input, string $leadId, bool $isJson): int
    {
        $stepNum = $this->requireStep($input, $io);
        if (!$stepNum) return Command::FAILURE;

        $lead = $this->fetchLead($http, $leadId);
        $stepsData = $this->fetchSteps($http, $leadId);
        $step = $this->getStepByNumber($stepsData, $stepNum);

        if (!$step) {
            $io->error("Step #{$stepNum} not found.");
            return Command::FAILURE;
        }

        $message = $step['instructions'] ?? '';
        if (!$message) {
            $io->error("Step #{$stepNum} has no message to send.");
            return Command::FAILURE;
        }

        $type = $step['type'] ?? 'other';

        switch ($type) {
            case 'email':
                return $this->sendEmail($io, $http, $input, $lead, $leadId, $step, $stepNum, $message, $isJson);

            case 'sms':
                return $this->sendSms($io, $http, $input, $lead, $leadId, $step, $stepNum, $message, $isJson);

            case 'phone':
                return $this->sendPhoneCall($io, $http, $input, $lead, $leadId, $step, $stepNum, $message, $isJson);

            case 'instagram':
            case 'social':
            case 'linkedin':
                // Can't auto-send DMs — copy + open instead
                $io->writeln("  <fg=yellow>Can't auto-send {$type} messages. Copying + opening profile instead.</>");
                $io->newLine();

                // Copy to clipboard
                $process = proc_open('pbcopy', [['pipe', 'r']], $pipes);
                if (is_resource($process)) {
                    fwrite($pipes[0], $message);
                    fclose($pipes[0]);
                    proc_close($process);
                }
                $io->writeln("  Message copied! (" . strlen($message) . " chars)");

                // Open profile
                $handle = $this->getLeadHandle($lead);
                if ($handle) {
                    $url = "https://www.instagram.com/{$handle}/";
                    exec("open " . escapeshellarg($url));
                    $io->writeln("  Opened @{$handle} in browser");
                }

                $io->newLine();
                $io->writeln("  <fg=gray>After sending manually, run: outreach:send complete {$leadId} --step={$stepNum}</>");
                return Command::SUCCESS;

            default:
                $io->writeln("  <fg=yellow>No auto-send for type '{$type}'. Showing message:</>");
                $io->newLine();
                foreach (explode("\n", wordwrap($message, 80, "\n")) as $line) {
                    $io->writeln("  {$line}");
                }
                return Command::SUCCESS;
        }
    }

    private function sendEmail(SymfonyStyle $io, $http, InputInterface $input, array $lead, string $leadId, array $step, int $stepNum, string $message, bool $isJson): int
    {
        $email = $this->getLeadEmail($lead);
        if (!$email) {
            $io->error('No email address found for this lead.');
            return Command::FAILURE;
        }

        $subject = $input->getOption('subject') ?? $step['subject'] ?? '';
        if (!$subject) {
            $helper = $this->getHelper('question');
            $question = new \Symfony\Component\Console\Question\Question('  Email subject: ');
            $subject = $helper->ask($input, $io, $question);
            if (!$subject) {
                $io->error('Email subject is required.');
                return Command::FAILURE;
            }
        }

        $io->newLine();
        $io->writeln("  <fg=white;options=bold>Send Email Preview</>");
        $io->writeln("  To: {$email} (" . $this->getLeadName($lead) . ")");
        $io->writeln("  Subject: {$subject}");
        $io->newLine();
        foreach (explode("\n", wordwrap($message, 80, "\n")) as $line) {
            $io->writeln("  {$line}");
        }
        $io->newLine();

        $helper = $this->getHelper('question');
        $confirm = new ConfirmationQuestion('  Send this email? (y/N) ', false);
        if (!$helper->ask($input, $io, $confirm)) {
            $io->writeln('  <fg=gray>Cancelled.</>');
            return Command::SUCCESS;
        }

        $result = $http->post("/api/v1/leads/{$leadId}/outreach/send-email", [
            'to_email' => $email,
            'to_name' => $this->getLeadName($lead),
            'subject' => $subject,
            'body_html' => $message,
            'plain_text_only' => true,
        ]);

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Email sent to {$email}!");

        // Auto-mark complete
        $stepId = $step['id'];
        $http->put("/api/v1/leads/{$leadId}/outreach-steps/{$stepId}", [
            'is_completed' => true,
            'notes' => 'Email sent via CLI',
        ]);
        $io->writeln("  Step #{$stepNum} marked complete.");

        return Command::SUCCESS;
    }

    private function sendSms(SymfonyStyle $io, $http, InputInterface $input, array $lead, string $leadId, array $step, int $stepNum, string $message, bool $isJson): int
    {
        $phone = $this->getLeadPhone($lead);
        if (!$phone) {
            $io->error('No phone number found for this lead.');
            return Command::FAILURE;
        }

        $io->newLine();
        $io->writeln("  <fg=white;options=bold>Send SMS Preview</>");
        $io->writeln("  To: {$phone} (" . $this->getLeadName($lead) . ")");
        $io->newLine();
        foreach (explode("\n", wordwrap($message, 80, "\n")) as $line) {
            $io->writeln("  {$line}");
        }
        $io->writeln("  <fg=gray>" . strlen($message) . " chars</>");
        $io->newLine();

        $helper = $this->getHelper('question');
        $confirm = new ConfirmationQuestion('  Send this SMS? (y/N) ', false);
        if (!$helper->ask($input, $io, $confirm)) {
            $io->writeln('  <fg=gray>Cancelled.</>');
            return Command::SUCCESS;
        }

        $result = $http->post("/api/v1/leads/{$leadId}/outreach/send-sms", [
            'message' => $message,
        ]);

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("SMS sent to {$phone}!");

        // Auto-mark complete
        $stepId = $step['id'];
        $http->put("/api/v1/leads/{$leadId}/outreach-steps/{$stepId}", [
            'is_completed' => true,
            'notes' => 'SMS sent via CLI',
        ]);
        $io->writeln("  Step #{$stepNum} marked complete.");

        return Command::SUCCESS;
    }

    private function sendPhoneCall(SymfonyStyle $io, $http, InputInterface $input, array $lead, string $leadId, array $step, int $stepNum, string $message, bool $isJson): int
    {
        $phone = $this->getLeadPhone($lead);
        if (!$phone) {
            $io->error('No phone number found for this lead.');
            $io->text("Use 'iris call make --phone=+1XXXXXXXXXX' to call an arbitrary number.");
            return Command::FAILURE;
        }

        $leadName = $this->getLeadName($lead);

        $io->newLine();
        $io->writeln('  <fg=white;options=bold>Phone Call Preview</>');
        $io->writeln("  To: <info>{$phone}</info> ({$leadName})");
        $stepTitle = $step['title'] ?? 'Phone Call';
        $io->writeln("  Step: {$stepTitle}");
        $io->newLine();

        // Show call script
        if ($message) {
            $io->writeln('  <fg=gray>Call Script:</>');
            foreach (explode("\n", wordwrap($message, 80, "\n")) as $line) {
                $io->writeln("  {$line}");
            }
            $io->newLine();
        }

        // Confirm before dialing
        $helper = $this->getHelper('question');
        $confirm = new ConfirmationQuestion("  Initiate call to {$phone}? (y/N) ", false);
        if (!$helper->ask($input, $io, $confirm)) {
            $io->writeln('  <fg=gray>Cancelled.</>');
            return Command::SUCCESS;
        }

        // Call the API
        $result = $http->post('/api/v1/calls/make', [
            'lead_id' => (int) $leadId,
            'purpose' => $step['title'] ?? 'Outreach call',
            'script' => $message,
        ]);

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? [];
        $callSid = $data['call_sid'] ?? $data['status'] ?? 'dispatched';
        $provider = $data['provider'] ?? 'unknown';

        $io->success("Call initiated via {$provider}!");
        $io->writeln("  Call SID: <info>{$callSid}</info>");

        if ($provider === 'vapi') {
            $io->writeln('  <fg=gray>The AI voice agent will call shortly.</>');
        }

        // Auto-mark step complete
        $stepId = $step['id'];
        $http->put("/api/v1/leads/{$leadId}/outreach-steps/{$stepId}", [
            'is_completed' => true,
            'notes' => "Call initiated via CLI ({$provider}). SID: {$callSid}",
        ]);
        $io->writeln("  Step #{$stepNum} marked complete.");

        return Command::SUCCESS;
    }

    private function applyStrategy(SymfonyStyle $io, $http, InputInterface $input, string $leadId, bool $isJson): int
    {
        $strategyKey = $input->getOption('strategy');
        $bloqId = $input->getOption('bloq');

        // If bloq + numeric strategy = custom template from bloq
        if ($bloqId && $strategyKey && is_numeric($strategyKey)) {
            $io->writeln("  Applying custom strategy template #{$strategyKey} from bloq #{$bloqId}...");
            $result = $http->post("/api/v1/bloqs/{$bloqId}/outreach-strategy-templates/{$strategyKey}/apply", [
                'lead_id' => (int)$leadId,
                'clear_existing' => true,
            ]);
        } elseif ($strategyKey && isset(self::STRATEGIES[$strategyKey])) {
            // Built-in strategy
            $io->writeln("  Applying {$strategyKey} strategy...");
            $result = $http->post("/api/v1/leads/{$leadId}/outreach-steps/initialize-strategy", [
                'strategy_key' => $strategyKey,
            ]);
        } else {
            // Interactive: pick a strategy
            $helper = $this->getHelper('question');
            $choices = array_values(self::STRATEGIES);
            $keys = array_keys(self::STRATEGIES);

            $question = new ChoiceQuestion('  Select a strategy:', $choices, 0);
            $selected = $helper->ask($input, $io, $question);
            $selectedIdx = array_search($selected, $choices);
            $strategyKey = $keys[$selectedIdx];

            $io->writeln("  Applying {$strategyKey} strategy...");
            $result = $http->post("/api/v1/leads/{$leadId}/outreach-steps/initialize-strategy", [
                'strategy_key' => $strategyKey,
            ]);
        }

        if ($isJson) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Strategy applied to lead #{$leadId}!");

        // Show the resulting steps
        return $this->listSteps($io, $http, $leadId, false);
    }

    private function markInvalid(SymfonyStyle $io, $http, InputInterface $input, string $leadId, bool $isJson): int
    {
        $stepNum = $this->requireStep($input, $io);
        if (!$stepNum) return Command::FAILURE;

        $stepsData = $this->fetchSteps($http, $leadId);
        $step = $this->getStepByNumber($stepsData, $stepNum);

        if (!$step) {
            $io->error("Step #{$stepNum} not found.");
            return Command::FAILURE;
        }

        $reason = $input->getOption('reason');
        if (!$reason) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('  Reason for invalid:', self::INVALID_REASONS, 0);
            $reason = $helper->ask($input, $io, $question);
        }

        // Add note to lead
        $http->post("/api/v1/leads/{$leadId}/notes", [
            'note' => "Cannot contact: {$reason}",
        ]);

        // Mark step complete with reason
        $stepId = $step['id'];
        $http->put("/api/v1/leads/{$leadId}/outreach-steps/{$stepId}", [
            'is_completed' => true,
            'notes' => "Could not complete: {$reason}",
        ]);

        if ($isJson) {
            $io->writeln(json_encode(['success' => true, 'reason' => $reason], JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->success("Step #{$stepNum} marked invalid: {$reason}");
        $io->writeln("  <fg=gray>Note added to lead #{$leadId}</>.");

        return Command::SUCCESS;
    }

    private function unknownAction(SymfonyStyle $io, string $action): int
    {
        $io->error("Unknown action: {$action}");
        $io->writeln('  Available: list, show, vary, personalize, copy, open, complete, send, apply, invalid');
        return Command::FAILURE;
    }
}
