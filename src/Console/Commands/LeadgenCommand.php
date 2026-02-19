<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use IRIS\SDK\IRIS;

/**
 * Import and enrich leads from social media usernames.
 *
 * Usage:
 *   iris leads:discover @user1,@user2,@user3 --board=38
 *   iris leads:discover ./discovered-profiles.txt --board=80 --enrich
 *   iris leads:discover @creator1,@creator2 --board=80 --enrich-react --tag=creators
 */
class LeadgenCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('leads:discover')
            ->setDescription('Import and enrich leads from social media usernames')
            ->addArgument('usernames', InputArgument::REQUIRED, 'Comma-separated usernames or path to a file (one per line)')
            ->addOption('board', 'b', InputOption::VALUE_REQUIRED, 'Board/bloq ID (required)')
            ->addOption('enrich', null, InputOption::VALUE_NONE, 'Auto-enrich after creation (Instagram profile fetch, Tavily, etc.)')
            ->addOption('enrich-react', null, InputOption::VALUE_NONE, 'Use ReAct enrichment (deeper, AI-driven)')
            ->addOption('source', 's', InputOption::VALUE_OPTIONAL, 'Source platform label', 'instagram')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview what would be created without making API calls')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON')
            ->addOption('tag', 't', InputOption::VALUE_OPTIONAL, 'Tag name to apply to all created leads')
            ->addOption('skip-dupes', null, InputOption::VALUE_NONE, 'Silently skip duplicates instead of reporting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $boardId = $input->getOption('board');
        if (!$boardId) {
            $output->writeln('<error>--board is required. Specify the bloq/board ID to add leads to.</error>');
            return Command::FAILURE;
        }
        $boardId = (int) $boardId;

        $iris = new IRIS([
            'api_key' => $_ENV['IRIS_API_KEY'] ?? null,
            'user_id' => $_ENV['IRIS_USER_ID'] ?? null,
            'environment' => $_ENV['IRIS_ENV'] ?? 'production',
        ]);

        $enrich = $input->getOption('enrich');
        $enrichReact = $input->getOption('enrich-react');
        $source = $input->getOption('source') ?: 'instagram';
        $dryRun = $input->getOption('dry-run');
        $jsonOutput = $input->getOption('json');
        $tagName = $input->getOption('tag');
        $skipDupes = $input->getOption('skip-dupes');

        // ── Parse usernames ──
        $raw = $input->getArgument('usernames');
        $usernames = $this->parseUsernames($raw);

        if (empty($usernames)) {
            $output->writeln('<error>No valid usernames found.</error>');
            return Command::FAILURE;
        }

        if (!$jsonOutput) {
            $output->writeln('');
            $output->writeln('<info>  ┌──────────────────────────────────────────────┐</info>');
            $output->writeln('<info>  │  L E A D S : D I S C O V E R                │</info>');
            $output->writeln('<info>  └──────────────────────────────────────────────┘</info>');
            $output->writeln("  Board:     {$boardId}");
            $output->writeln("  Source:    {$source}");
            $output->writeln("  Usernames: " . count($usernames));
            if ($enrich) $output->writeln('  Enrich:    YES');
            if ($enrichReact) $output->writeln('  Enrich:    ReAct (AI-driven)');
            if ($dryRun) $output->writeln('  DRY RUN:   preview only');
            if ($tagName) $output->writeln("  Tag:       {$tagName}");
            $output->writeln('');
        }

        // ── Pre-flight dedup ──
        $existingHandles = new \ArrayObject();
        if (!$dryRun) {
            try {
                $existingLeads = $iris->leads->search([
                    'bloq_id' => $boardId,
                    'per_page' => 500,
                ]);
                $leads = is_array($existingLeads) ? $existingLeads : [];
                foreach ($leads as $lead) {
                    $leadData = is_object($lead) ? (array) $lead : $lead;
                    $ig = $leadData['contact_info']['instagram'] ?? null;
                    if ($ig) $existingHandles[strtolower(ltrim($ig, '@'))] = true;
                    $nickname = $leadData['nickname'] ?? '';
                    if (str_starts_with($nickname, '@')) {
                        $existingHandles[strtolower(ltrim($nickname, '@'))] = true;
                    }
                }
                if (!$jsonOutput) {
                    $output->writeln("  Found " . count($existingHandles) . " existing leads on board");
                }
            } catch (\Exception $e) {
                if (!$jsonOutput) {
                    $output->writeln("<comment>  Warning: Could not fetch existing leads: {$e->getMessage()}</comment>");
                }
            }
        }

        // ── Process usernames ──
        $stats = ['created' => 0, 'duplicates' => 0, 'enriched' => 0, 'errors' => 0];
        $results = [];

        foreach ($usernames as $i => $username) {
            $handle = strtolower(ltrim($username, '@'));
            $num = $i + 1;
            $total = count($usernames);

            // Dedup check
            if (isset($existingHandles[$handle])) {
                $stats['duplicates']++;
                if (!$skipDupes && !$jsonOutput) {
                    $output->writeln("  [{$num}/{$total}] @{$username} — duplicate (skip)");
                }
                $results[] = ['username' => $username, 'status' => 'duplicate', 'lead_id' => null];
                continue;
            }

            if ($dryRun) {
                if (!$jsonOutput) {
                    $output->writeln("  [{$num}/{$total}] @{$username} — would create");
                }
                $results[] = ['username' => $username, 'status' => 'would_create', 'lead_id' => null];
                continue;
            }

            if (!$jsonOutput) {
                $output->write("  [{$num}/{$total}] @{$username}");
            }

            try {
                $lead = $iris->leads->create([
                    'name' => "@{$username}",
                    'bloq_id' => $boardId,
                    'source' => "leadgen:{$source}",
                    'contact_info' => [$source => $username],
                    'status' => 'Prospected',
                ]);

                $leadId = $lead->id ?? null;
                $stats['created']++;
                $existingHandles[$handle] = true;

                if (!$jsonOutput) {
                    $output->writeln(" — created #{$leadId}");
                }

                // Enrich if requested
                if ($leadId && ($enrich || $enrichReact)) {
                    try {
                        if ($enrichReact) {
                            $iris->leads->enrichReAct((int) $leadId, ['goal' => 'all']);
                        } else {
                            $iris->leads->enrich((int) $leadId);
                        }
                        $stats['enriched']++;
                        if (!$jsonOutput) {
                            $output->writeln("    Enriched");
                        }
                    } catch (\Exception $e) {
                        if (!$jsonOutput) {
                            $output->writeln("    <comment>Enrich failed: " . substr($e->getMessage(), 0, 60) . "</comment>");
                        }
                    }
                }

                $results[] = ['username' => $username, 'status' => 'created', 'lead_id' => $leadId];

            } catch (\Exception $e) {
                $msg = $e->getMessage();
                // Check if it's a duplicate error from the backend
                if (stripos($msg, 'duplicate') !== false) {
                    $stats['duplicates']++;
                    $existingHandles[$handle] = true;
                    if (!$jsonOutput) {
                        $output->writeln(" — duplicate");
                    }
                    $results[] = ['username' => $username, 'status' => 'duplicate', 'lead_id' => null];
                } else {
                    $stats['errors']++;
                    if (!$jsonOutput) {
                        $output->writeln(" — <error>error: " . substr($msg, 0, 60) . "</error>");
                    }
                    $results[] = ['username' => $username, 'status' => 'error', 'lead_id' => null, 'error' => $msg];
                }
            }
        }

        // ── Output ──
        if ($jsonOutput) {
            $output->writeln(json_encode([
                'board_id' => $boardId,
                'source' => $source,
                'total' => count($usernames),
                'stats' => $stats,
                'results' => $results,
            ], JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        // Summary
        $output->writeln('');
        $output->writeln('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $output->writeln($dryRun ? '  DRY RUN COMPLETE' : '  DISCOVERY COMPLETE');
        $output->writeln('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $output->writeln("  Total:      " . count($usernames));
        $output->writeln("  Created:    {$stats['created']}");
        $output->writeln("  Duplicates: {$stats['duplicates']}");
        if ($stats['enriched'] > 0) $output->writeln("  Enriched:   {$stats['enriched']}");
        if ($stats['errors'] > 0) $output->writeln("  Errors:     {$stats['errors']}");
        $output->writeln('  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * Parse usernames from a comma-separated string or a file path.
     */
    private function parseUsernames(string $raw): array
    {
        // Check if it's a file path
        if (file_exists($raw)) {
            $lines = file($raw, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return array_filter(array_map(fn($line) => $this->cleanUsername(trim($line)), $lines));
        }

        // Comma-separated
        $parts = explode(',', $raw);
        return array_filter(array_map(fn($part) => $this->cleanUsername(trim($part)), $parts));
    }

    /**
     * Clean a username: strip @, extract from URL, trim whitespace.
     */
    private function cleanUsername(string $input): string
    {
        $input = trim($input);
        if (empty($input) || str_starts_with($input, '#')) return ''; // Skip comments

        // Strip @ prefix
        if (str_starts_with($input, '@')) $input = substr($input, 1);

        // Extract from Instagram URL
        if (preg_match('/instagram\.com\/([a-zA-Z0-9._]+)/', $input, $m)) {
            $input = $m[1];
        }

        // Filter out non-user paths
        if (in_array($input, ['explore', 'accounts', 'p', 'reel', 'stories', 'direct'])) return '';

        return $input;
    }
}
