<?php

declare(strict_types=1);

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;
use IRIS\SDK\Auth\CredentialStore;

/**
 * SOM Campaign Overview — see all outreach campaigns at a glance.
 *
 * Shows every active SOM campaign with its strategy, scripts, hooks,
 * audience, lead counts, and step details in one view.
 */
class SomOverviewCommand extends Command
{
    // Campaign registry — mirrors tests/e2e/som.js
    private const CAMPAIGNS = [
        'courses' => ['board' => 38, 'strategy' => 'AI Course | V3', 'ig' => 'heyiris.io', 'label' => 'AI Course Outreach', 'audience' => 'AI builders, tech founders'],
        'creators' => ['board' => 80, 'strategy' => 'Creator Outreach | V1', 'ig' => 'thediscoverpage_', 'label' => 'Creator Outreach', 'audience' => 'Artists, creators, hip-hop culture'],
        'beatbox' => ['board' => 224, 'strategy' => 'DJ Outreach | V1', 'ig' => 'thebeatbox__', 'label' => 'DJ Outreach', 'audience' => 'DJs, producers, beatmakers'],
        'venues' => ['board' => 292, 'strategy' => 'Venue Partnership | V1', 'ig' => 'freelabelnet', 'label' => 'Venue Partnership', 'audience' => 'Cafes, venues, event spaces'],
    ];

    protected function configure(): void
    {
        $this
            ->setName('som:overview')
            ->setAliases(['som', 'reachr:overview'])
            ->setDescription('View all SOM outreach campaigns, strategies, and scripts at a glance')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('campaign', 'c', InputOption::VALUE_REQUIRED, 'Show only one campaign (courses|creators|beatbox|venues)')
            ->addOption('scripts', 's', InputOption::VALUE_NONE, 'Show full script text (default: truncated)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key override')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID override');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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
        $showFull = $input->getOption('scripts');
        $filterCampaign = $input->getOption('campaign');

        $campaigns = self::CAMPAIGNS;
        if ($filterCampaign) {
            if (!isset($campaigns[$filterCampaign])) {
                $io->error("Unknown campaign: {$filterCampaign}. Options: " . implode(', ', array_keys($campaigns)));
                return Command::FAILURE;
            }
            $campaigns = [$filterCampaign => $campaigns[$filterCampaign]];
        }

        $allData = [];

        foreach ($campaigns as $name => $cfg) {
            $boardId = $cfg['board'];
            $strategyName = $cfg['strategy'];

            // Fetch strategies for this board
            try {
                $response = $http->get("/api/v1/bloqs/{$boardId}/outreach-strategy-templates");
                $templates = $response['templates'] ?? $response['data']['templates'] ?? [];
            } catch (\Exception $e) {
                $templates = [];
            }

            // Find the active strategy by name
            $active = null;
            foreach ($templates as $t) {
                if ($t['name'] === $strategyName) {
                    $active = $t;
                    break;
                }
            }

            // Fetch lead count for the board
            try {
                $leadsResp = $http->get("/api/v1/leads", ['bloq_id' => $boardId, 'per_page' => 1]);
                $totalLeads = $leadsResp['total'] ?? 0;
            } catch (\Exception $e) {
                $totalLeads = '?';
            }

            $allData[$name] = [
                'config' => $cfg,
                'strategy' => $active,
                'total_strategies' => count($templates),
                'total_leads' => $totalLeads,
            ];
        }

        // JSON output
        if ($input->getOption('json')) {
            $io->writeln(json_encode($allData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return Command::SUCCESS;
        }

        // Display
        $io->title('SOM — Outreach Campaign Overview');

        foreach ($allData as $name => $data) {
            $cfg = $data['config'];
            $active = $data['strategy'];
            $strats = $data['total_strategies'];
            $leads = $data['total_leads'];

            $io->section(strtoupper($name) . " — {$cfg['label']}");

            $io->definitionList(
                ['Board' => "#{$cfg['board']}"],
                ['Instagram' => "@{$cfg['ig']}"],
                ['Audience' => $cfg['audience']],
                ['Leads' => "{$leads} total"],
                ['Strategies' => "{$strats} templates"],
                ['Active' => $active ? "{$active['name']} (id:{$active['id']}, {$active['usage_count']} uses)" : '<error>NOT FOUND</error>'],
            );

            if (!$active) {
                $io->warning("Strategy \"{$cfg['strategy']}\" not found on board {$cfg['board']}!");
                continue;
            }

            $steps = $active['steps'] ?? [];
            if (empty($steps)) {
                $io->warning('No steps defined — outreach will fail!');
                continue;
            }

            $io->text("<fg=cyan>Steps ({$active['name']}):</>");
            foreach ($steps as $step) {
                $order = ($step['order'] ?? 0) + 1;
                $title = $step['title'] ?? '?';
                $type = $step['type'] ?? '?';
                $delay = ($step['delay_hours'] ?? 0) > 0 ? " <fg=yellow>(+{$step['delay_hours']}h)</>" : '';
                $typeLabel = match($type) {
                    'instagram' => 'IG DM',
                    'email' => 'Email',
                    'sms' => 'SMS',
                    'phone' => 'Phone',
                    default => ucfirst($type),
                };

                $io->text("  <fg=white>Step {$order}:</> {$title} <fg=gray>[{$typeLabel}]</>{$delay}");

                $script = trim($step['instructions'] ?? '');
                if ($script) {
                    if ($showFull) {
                        // Word-wrap for readability
                        $wrapped = wordwrap($script, 90, "\n    ");
                        $io->text("    <fg=green>Script:</> {$wrapped}");
                    } else {
                        $preview = mb_strlen($script) > 120 ? mb_substr($script, 0, 120) . '...' : $script;
                        // Extract just the hook (first sentence)
                        $hook = preg_split('/[.!?—]\s/', $script, 2)[0] ?? $preview;
                        $io->text("    <fg=green>Hook:</> {$hook}");
                        $io->text("    <fg=gray>Script:</> {$preview}");
                    }
                } else {
                    $io->text("    <fg=red>(no script)</>");
                }

                $prompt = trim($step['ai_prompt'] ?? '');
                if ($prompt) {
                    $promptPreview = mb_strlen($prompt) > 80 ? mb_substr($prompt, 0, 80) . '...' : $prompt;
                    $io->text("    <fg=blue>AI:</> {$promptPreview}");
                }
            }

            $io->newLine();
        }

        return Command::SUCCESS;
    }
}
