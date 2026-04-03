<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * SOM Edit — inline editing of outreach campaign scripts.
 *
 * Usage:
 *   som:edit creators                    Interactive — pick a step to edit
 *   som:edit creators --step=1           Edit step 1 directly
 *   som:edit creators --step=1 --field=script    Edit just the script
 *   som:edit creators --step=1 --field=ai        Edit just the AI prompt
 *   som:edit beatbox --step=all          Edit all steps interactively
 */
class SomEditCommand extends Command
{
    private const CAMPAIGNS = [
        'courses'  => ['board' => 38,  'strategy' => 'AI Course | V3',          'ig' => 'heyiris.io',        'label' => 'AI Course Outreach'],
        'creators' => ['board' => 80,  'strategy' => 'Creator Outreach | V1',   'ig' => 'thediscoverpage_',  'label' => 'Creator Outreach'],
        'beatbox'  => ['board' => 224, 'strategy' => 'DJ Outreach | V1',        'ig' => 'thebeatbox__',      'label' => 'DJ Outreach'],
        'venues'   => ['board' => 292, 'strategy' => 'Venue Partnership | V1',  'ig' => 'freelabelnet',      'label' => 'Venue Partnership'],
    ];

    protected function configure(): void
    {
        $this
            ->setName('som:edit')
            ->setAliases(['reachr:edit'])
            ->setDescription('Edit SOM campaign scripts inline')
            ->addArgument('campaign', InputArgument::REQUIRED, 'Campaign: courses|creators|beatbox|venues')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Step number (1-based) or "all"')
            ->addOption('field', null, InputOption::VALUE_REQUIRED, 'Field to edit: script|ai|title|delay')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'New value (skips interactive prompt)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key override')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID override');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $campaignName = $input->getArgument('campaign');
        if (!isset(self::CAMPAIGNS[$campaignName])) {
            $io->error("Unknown campaign: {$campaignName}. Options: " . implode(', ', array_keys(self::CAMPAIGNS)));
            return Command::FAILURE;
        }

        $cfg = self::CAMPAIGNS[$campaignName];
        $boardId = $cfg['board'];
        $strategyName = $cfg['strategy'];

        // Auth
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey) {
            $io->error('No API key. Run: php bin/iris setup');
            return Command::FAILURE;
        }

        $iris = new IRIS(['api_key' => $apiKey, 'user_id' => (int) $userId]);
        $http = $iris->getHttpClient();

        // Fetch strategy
        try {
            $response = $http->get("/api/v1/bloqs/{$boardId}/outreach-strategy-templates");
            $templates = $response['templates'] ?? $response['data']['templates'] ?? [];
        } catch (\Exception $e) {
            $io->error("Failed to fetch strategies: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $active = null;
        foreach ($templates as $t) {
            if ($t['name'] === $strategyName) {
                $active = $t;
                break;
            }
        }

        if (!$active) {
            $io->error("Strategy \"{$strategyName}\" not found on board {$boardId}");
            return Command::FAILURE;
        }

        $steps = $active['steps'] ?? [];
        if (empty($steps)) {
            $io->error('Strategy has no steps to edit');
            return Command::FAILURE;
        }

        // Sort steps by order
        usort($steps, fn($a, $b) => ($a['order'] ?? 0) - ($b['order'] ?? 0));

        $io->title("Edit: {$cfg['label']} ({$strategyName})");

        $stepNum = $input->getOption('step');
        $field = $input->getOption('field');
        $directValue = $input->getOption('value');

        // If no step specified, show all and let user pick
        if (!$stepNum) {
            $io->text("<fg=cyan>Current steps:</>");
            $choices = [];
            foreach ($steps as $i => $s) {
                $label = "Step " . ($s['order'] + 1) . ": {$s['title']} [{$s['type']}]";
                $script = trim($s['instructions'] ?? '');
                if ($script) {
                    $preview = mb_strlen($script) > 60 ? mb_substr($script, 0, 60) . '...' : $script;
                    $label .= " — \"{$preview}\"";
                }
                $choices[$i + 1] = $label;
            }

            $choice = $io->choice('Which step do you want to edit?', $choices);
            $stepNum = (string) array_search($choice, $choices);
        }

        // Handle "all" — edit each step sequentially
        if ($stepNum === 'all') {
            return $this->editAllSteps($io, $input, $http, $boardId, $active, $steps);
        }

        $stepIdx = ((int) $stepNum) - 1;
        if ($stepIdx < 0 || $stepIdx >= count($steps)) {
            $io->error("Invalid step number. This strategy has " . count($steps) . " steps.");
            return Command::FAILURE;
        }

        $step = $steps[$stepIdx];

        return $this->editSingleStep($io, $input, $http, $boardId, $active, $steps, $stepIdx, $field, $directValue);
    }

    private function editSingleStep(
        SymfonyStyle $io,
        InputInterface $input,
        $http,
        int $boardId,
        array $strategy,
        array $steps,
        int $stepIdx,
        ?string $field,
        ?string $directValue
    ): int {
        $step = $steps[$stepIdx];
        $stepNum = $stepIdx + 1;

        $io->section("Step {$stepNum}: {$step['title']} [{$step['type']}]");

        // Show current values
        $io->text("<fg=green>Current script:</>");
        $io->text(wordwrap(trim($step['instructions'] ?? '(empty)'), 90, "\n  "));
        $io->newLine();

        if (!empty($step['ai_prompt'])) {
            $io->text("<fg=blue>Current AI prompt:</>");
            $io->text(wordwrap(trim($step['ai_prompt']), 90, "\n  "));
            $io->newLine();
        }

        // Determine what to edit
        if (!$field) {
            $field = $io->choice('What do you want to edit?', [
                'script' => 'Script (the DM/email text)',
                'ai' => 'AI prompt (personalization instructions)',
                'title' => 'Step title',
                'both' => 'Script + AI prompt',
            ]);
        }

        $updated = false;

        if (in_array($field, ['script', 'both'])) {
            $io->text('<fg=yellow>Enter new script (press Enter twice to finish):</>');
            $newScript = $directValue ?? $this->readMultiline($io, $input);
            if ($newScript !== null && trim($newScript) !== '') {
                $steps[$stepIdx]['instructions'] = trim($newScript);
                $updated = true;
                $io->text('<fg=green>✓ Script updated</>');
            } else {
                $io->text('<fg=gray>Script unchanged</>');
            }
        }

        if (in_array($field, ['ai', 'both'])) {
            $io->text('<fg=yellow>Enter new AI prompt (press Enter twice to finish):</>');
            $newPrompt = $this->readMultiline($io, $input);
            if ($newPrompt !== null && trim($newPrompt) !== '') {
                $steps[$stepIdx]['ai_prompt'] = trim($newPrompt);
                $updated = true;
                $io->text('<fg=green>✓ AI prompt updated</>');
            } else {
                $io->text('<fg=gray>AI prompt unchanged</>');
            }
        }

        if ($field === 'title') {
            $newTitle = $directValue ?? $io->ask('New title', $step['title']);
            if ($newTitle && $newTitle !== $step['title']) {
                $steps[$stepIdx]['title'] = $newTitle;
                $updated = true;
            }
        }

        if (!$updated) {
            $io->info('No changes made.');
            return Command::SUCCESS;
        }

        // Push update
        return $this->pushSteps($io, $http, $boardId, $strategy, $steps);
    }

    private function editAllSteps(
        SymfonyStyle $io,
        InputInterface $input,
        $http,
        int $boardId,
        array $strategy,
        array $steps
    ): int {
        $updated = false;

        foreach ($steps as $i => &$step) {
            $stepNum = $i + 1;
            $io->section("Step {$stepNum}: {$step['title']} [{$step['type']}]");

            $currentScript = trim($step['instructions'] ?? '');
            if ($currentScript) {
                $io->text("<fg=green>Current:</> " . (mb_strlen($currentScript) > 100 ? mb_substr($currentScript, 0, 100) . '...' : $currentScript));
            } else {
                $io->text('<fg=red>(no script)</>');
            }

            $action = $io->choice("Edit step {$stepNum}?", [
                'skip' => 'Skip (keep current)',
                'script' => 'Edit script',
                'ai' => 'Edit AI prompt',
                'both' => 'Edit script + AI prompt',
            ], 'skip');

            if ($action === 'skip') continue;

            if (in_array($action, ['script', 'both'])) {
                $io->text('<fg=yellow>Enter new script:</>');
                $newScript = $this->readMultiline($io, $input);
                if ($newScript !== null && trim($newScript) !== '') {
                    $step['instructions'] = trim($newScript);
                    $updated = true;
                    $io->text('<fg=green>✓ Script updated</>');
                }
            }

            if (in_array($action, ['ai', 'both'])) {
                $io->text('<fg=yellow>Enter new AI prompt:</>');
                $newPrompt = $this->readMultiline($io, $input);
                if ($newPrompt !== null && trim($newPrompt) !== '') {
                    $step['ai_prompt'] = trim($newPrompt);
                    $updated = true;
                    $io->text('<fg=green>✓ AI prompt updated</>');
                }
            }
        }
        unset($step);

        if (!$updated) {
            $io->info('No changes made.');
            return Command::SUCCESS;
        }

        return $this->pushSteps($io, $http, $boardId, $strategy, $steps);
    }

    private function pushSteps(SymfonyStyle $io, $http, int $boardId, array $strategy, array $steps): int
    {
        $strategyId = $strategy['id'];

        // Clean steps for API — only send editable fields
        $cleanSteps = array_map(fn($s) => [
            'title' => $s['title'],
            'type' => $s['type'],
            'instructions' => $s['instructions'] ?? null,
            'order' => $s['order'] ?? 0,
            'delay_hours' => $s['delay_hours'] ?? 0,
            'ai_prompt' => $s['ai_prompt'] ?? null,
        ], $steps);

        try {
            $http->put("/api/v1/bloqs/{$boardId}/outreach-strategy-templates/{$strategyId}", [
                'steps' => $cleanSteps,
            ]);
            $io->success("Strategy updated! ({$strategy['name']}, " . count($cleanSteps) . " steps)");
            $io->text("Verify: <fg=cyan>php bin/iris som -c " . $this->findCampaignName($boardId) . "</>");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to update: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function findCampaignName(int $boardId): string
    {
        foreach (self::CAMPAIGNS as $name => $cfg) {
            if ($cfg['board'] === $boardId) return $name;
        }
        return '?';
    }

    private function readMultiline(SymfonyStyle $io, InputInterface $input): ?string
    {
        $helper = $this->getHelper('question');
        $question = new Question('> ');
        $question->setMultiline(true);

        $result = $helper->ask($input, $io->getOutput() ?? $io, $question);
        return is_string($result) ? $result : null;
    }
}
