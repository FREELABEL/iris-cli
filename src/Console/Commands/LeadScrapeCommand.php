<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scrape Instagram comments/followers and create leads on a board.
 *
 * Usage:
 *   iris leads:scrape --url=https://www.instagram.com/p/DOgSXrCju2y/ --board=42
 *   iris leads:scrape --url=https://www.instagram.com/p/DOgSXrCju2y/ --board=42 --limit=100
 *   iris leads:scrape --url=https://www.instagram.com/p/DOgSXrCju2y/ --board=42 --mode=followers --dry-run
 *   iris leads:scrape --url=https://www.instagram.com/p/DOgSXrCju2y/ --board=42 --limit=200 --enrich
 */
class LeadScrapeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('leads:scrape')
            ->setDescription('Scrape Instagram comments/followers and create leads on a board')
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'Instagram post or profile URL to scrape')
            ->addOption('board', 'b', InputOption::VALUE_REQUIRED, 'Board/bloq ID to add leads to')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Max number of leads to create', '50')
            ->addOption('mode', 'm', InputOption::VALUE_OPTIONAL, 'Discovery mode: comments, followers, profiles', 'comments')
            ->addOption('ig-account', null, InputOption::VALUE_OPTIONAL, 'Instagram account for session cookies', 'heyiris.io')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Scrape only — no API calls')
            ->addOption('enrich', null, InputOption::VALUE_NONE, 'Auto-enrich leads after creation')
            ->addOption('label', null, InputOption::VALUE_OPTIONAL, 'Campaign label for this run')
            ->addOption('batch-size', null, InputOption::VALUE_OPTIONAL, 'API batch concurrency', '5')
            ->addOption('scroll-delay', null, InputOption::VALUE_OPTIONAL, 'Delay between scroll attempts (ms)', '2000')
            ->addOption('resume', null, InputOption::VALUE_NONE, 'Resume a previously interrupted run')
            ->addOption('headed', null, InputOption::VALUE_NONE, 'Show browser window (default: headless)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getOption('url');
        $boardId = $input->getOption('board');

        if (!$url) {
            $output->writeln('<error>--url is required. Provide an Instagram post or profile URL.</error>');
            return Command::FAILURE;
        }
        if (!$boardId) {
            $output->writeln('<error>--board is required. Specify the bloq/board ID to add leads to.</error>');
            return Command::FAILURE;
        }

        // Resolve project root (where npx playwright can run)
        $projectRoot = $this->findProjectRoot();
        if (!$projectRoot) {
            $output->writeln('<error>Could not find project root (looking for tests/e2e/leadgen-scraper.spec.ts)</error>');
            $output->writeln('<comment>Make sure you\'re running from within the freelabel project directory.</comment>');
            return Command::FAILURE;
        }

        // Get API token from SDK env
        $apiToken = $_ENV['IRIS_API_KEY'] ?? getenv('IRIS_API_KEY') ?: null;
        if (!$apiToken && !$input->getOption('dry-run')) {
            $output->writeln('<error>IRIS_API_KEY not set. Run `iris setup` or set it in .env</error>');
            return Command::FAILURE;
        }

        // Build env vars for the Playwright test
        $limit = $input->getOption('limit');
        $mode = $input->getOption('mode');
        $igAccount = $input->getOption('ig-account');
        $dryRun = $input->getOption('dry-run');
        $enrich = $input->getOption('enrich');
        $label = $input->getOption('label') ?: "CLI Scrape — {$mode}";
        $batchSize = $input->getOption('batch-size');
        $scrollDelay = $input->getOption('scroll-delay');
        $resume = $input->getOption('resume');
        $headed = $input->getOption('headed');

        $env = [
            'TARGET_URL' => $url,
            'BOARD_ID' => $boardId,
            'LIMIT' => $limit,
            'DISCOVERY_MODE' => $mode,
            'IG_ACCOUNT' => $igAccount,
            'CAMPAIGN_LABEL' => $label,
            'API_BATCH_SIZE' => $batchSize,
            'SCROLL_DELAY' => $scrollDelay,
        ];

        if ($apiToken) {
            $env['HEYIRIS_TOKEN'] = $apiToken;
        }
        if ($dryRun) {
            $env['DRY_RUN'] = '1';
        }
        if ($enrich) {
            $env['AUTO_ENRICH'] = '1';
        }
        if ($resume) {
            $env['RESUME'] = '1';
        }

        // Build the command
        $envStr = '';
        foreach ($env as $key => $value) {
            $envStr .= escapeshellarg($key) . '=' . escapeshellarg($value) . ' ';
        }

        $playwrightFlags = '--timeout 600000';
        if ($headed) {
            $playwrightFlags .= ' --headed';
        }

        $cmd = "cd {$projectRoot} && {$envStr}npx playwright test tests/e2e/leadgen-scraper.spec.ts {$playwrightFlags} 2>&1";

        // Show what we're doing
        $output->writeln('');
        $output->writeln('<info>  ┌──────────────────────────────────────────────┐</info>');
        $output->writeln('<info>  │  L E A D S : S C R A P E                    │</info>');
        $output->writeln('<info>  └──────────────────────────────────────────────┘</info>');
        $output->writeln("  URL:       {$url}");
        $output->writeln("  Board:     {$boardId}");
        $output->writeln("  Mode:      {$mode}");
        $output->writeln("  Limit:     {$limit}");
        if ($dryRun) $output->writeln('  DRY RUN:   scrape only');
        if ($enrich) $output->writeln('  Enrich:    YES');
        if ($resume) $output->writeln('  Resume:    YES');
        $output->writeln('');

        // Stream output in real-time
        $process = popen($cmd, 'r');
        if (!$process) {
            $output->writeln('<error>Failed to start Playwright process</error>');
            return Command::FAILURE;
        }

        while (!feof($process)) {
            $line = fgets($process);
            if ($line !== false) {
                // Pass through Playwright output, stripping ANSI for cleaner CLI display
                $output->write($line);
            }
        }

        $exitCode = pclose($process);

        if ($exitCode !== 0) {
            $output->writeln('');
            $output->writeln('<error>Scraper exited with code ' . $exitCode . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Walk up from cwd to find the project root containing the scraper spec.
     */
    private function findProjectRoot(): ?string
    {
        $specPath = 'tests/e2e/leadgen-scraper.spec.ts';

        // Try common locations
        $candidates = [
            getcwd(),
            dirname(getcwd()),
            dirname(getcwd(), 2),
            dirname(getcwd(), 3),
            dirname(__DIR__, 5), // sdk/php/src/Console/Commands -> freelabel root
            $_ENV['FREELABEL_ROOT'] ?? '',
            getenv('FREELABEL_ROOT') ?: '',
        ];

        foreach ($candidates as $dir) {
            if ($dir && file_exists($dir . '/' . $specPath)) {
                return escapeshellarg($dir);
            }
        }

        return null;
    }
}
