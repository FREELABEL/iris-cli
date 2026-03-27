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
use IRIS\SDK\Config;

/**
 * CLI command for managing platform pricing packages.
 *
 * Usage:
 *   ./bin/iris packages                                        # List current packages
 *   ./bin/iris packages pull                                   # Download to ./packages/packages.json
 *   ./bin/iris packages push                                   # Upload from local JSON
 *   ./bin/iris packages diff                                   # Compare local vs remote
 *   ./bin/iris packages set elon-growth-monthly price 250      # Quick field update
 *   ./bin/iris packages list --env=production                  # Target production
 */
class PackagesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('packages')
            ->setDescription('Manage platform pricing packages (pull/push sync)')
            ->setHelp(<<<'HELP'
Manage platform pricing packages with local JSON as source of truth.

Usage:
  packages                                              List all packages
  packages list                                         List all packages
  packages pull                                         Download packages to ./packages/packages.json
  packages push                                         Upload ./packages/packages.json to API
  packages diff                                         Compare local file vs remote
  packages set <slug> <field> <value>                   Update a single field on a package
  packages set <slug> <dot.path> <value>                Set nested value (e.g. features.displayFeatures.0)
  packages get <slug> <dot.path>                        Read nested value
  packages features <slug>                              View feature list for a package

Options:
  --env=local|production                                Target environment
  --platform=elon|iris|freelabel                        Filter by platform (default: elon)
  --with-stripe                                         Enable Stripe price sync on push
  --dir=./packages                                      Directory for pull/push files
  --json                                                Output as JSON

Examples:
  packages pull --env=production                        Download production packages
  packages set elon-growth-monthly price 250            Update Growth price to $250
  packages set elon-growth-monthly "features.displayFeatures.0" "500 AI agents"
  packages get elon-growth-monthly "features.displayFeatures"
  packages features elon-growth-monthly                 View features for Growth
  packages push --env=production                        Push local JSON to production
  packages push --env=production --with-stripe          Push + sync Stripe prices
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, pull, push, diff, set, get, features', 'list')
            ->addArgument('slug', InputArgument::OPTIONAL, 'Package slug (for set)')
            ->addArgument('field', InputArgument::OPTIONAL, 'Field name (for set)')
            ->addArgument('value', InputArgument::OPTIONAL, 'New value (for set)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment: local or production')
            ->addOption('platform', null, InputOption::VALUE_REQUIRED, 'Platform filter', 'elon')
            ->addOption('with-stripe', null, InputOption::VALUE_NONE, 'Enable Stripe sync on push')
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Directory for pull/push files', './packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';

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
            $io->error(['Missing API credentials.', '', 'Run: php bin/iris setup']);
            return Command::FAILURE;
        }

        $iris = new IRIS([
            'api_key' => $apiKey,
            'user_id' => (int) $userId,
        ]);

        $currentEnv = $env ?: (getenv('IRIS_ENV') ?: 'production');
        $baseUrl = $iris->getConfig()->flApiUrl ?? $iris->getConfig()->baseUrl;

        try {
            switch ($action) {
                case 'list':
                    return $this->listPackages($iris, $io, $input, $currentEnv, $baseUrl);
                case 'pull':
                    return $this->pullPackages($iris, $io, $input, $currentEnv);
                case 'push':
                    return $this->pushPackages($iris, $io, $input, $currentEnv);
                case 'diff':
                    return $this->diffPackages($iris, $io, $input, $currentEnv);
                case 'set':
                    return $this->setField($iris, $io, $input, $currentEnv);
                case 'get':
                    return $this->getField($iris, $io, $input, $currentEnv);
                case 'features':
                    return $this->showFeatures($iris, $io, $input, $currentEnv);
                default:
                    $io->error("Unknown action: {$action}. Use: list, pull, push, diff, set");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * List current packages from API
     */
    private function listPackages(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env, string $baseUrl): int
    {
        $platform = $input->getOption('platform');
        $io->title("Platform Packages [{$env}]");
        $io->text("API: {$baseUrl}");
        $io->newLine();

        $response = $iris->packages->list(['platform' => $platform]);
        $packages = $response['data'] ?? $response;

        if (empty($packages)) {
            $io->warning("No packages found for platform '{$platform}'");
            return Command::SUCCESS;
        }

        if ($input->getOption('json')) {
            $io->writeln(json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($packages as $pkg) {
            $rows[] = [
                $pkg['id'] ?? '-',
                $pkg['title'] ?? '-',
                $pkg['slug'] ?? '-',
                '$' . ($pkg['price'] ?? '0') . '/' . ($pkg['billing_period'] ?? 'mo'),
                ($pkg['popular'] ?? false) ? 'Y' : '',
                ($pkg['public'] ?? false) ? 'Y' : '',
                $pkg['stripe_package_id'] ?? '-',
            ];
        }

        $io->table(['ID', 'Title', 'Slug', 'Price', 'Popular', 'Public', 'Stripe ID'], $rows);
        $io->text(count($packages) . ' packages total');

        return Command::SUCCESS;
    }

    /**
     * Pull packages from API to local JSON file
     */
    private function pullPackages(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env): int
    {
        $platform = $input->getOption('platform');
        $dir = $input->getOption('dir');

        $io->title("Pull Packages [{$env}]");

        $response = $iris->packages->list(['platform' => $platform]);
        $packages = $response['data'] ?? $response;

        if (empty($packages)) {
            $io->warning("No packages found for platform '{$platform}'");
            return Command::SUCCESS;
        }

        // Ensure directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = rtrim($dir, '/') . '/packages.json';
        $json = json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($filePath, $json);

        $io->success("Pulled " . count($packages) . " packages to {$filePath}");

        // Show summary
        foreach ($packages as $pkg) {
            $io->text("  {$pkg['slug']} — \${$pkg['price']}/{$pkg['billing_period']}");
        }

        return Command::SUCCESS;
    }

    /**
     * Push local JSON to API
     */
    private function pushPackages(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env): int
    {
        $dir = $input->getOption('dir');
        $syncStripe = $input->getOption('with-stripe');
        $filePath = rtrim($dir, '/') . '/packages.json';

        $io->title("Push Packages [{$env}]");

        if (!file_exists($filePath)) {
            $io->error("File not found: {$filePath}");
            $io->text("Run 'packages pull' first to download current packages.");
            return Command::FAILURE;
        }

        $json = file_get_contents($filePath);
        $packages = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON in {$filePath}: " . json_last_error_msg());
            return Command::FAILURE;
        }

        if (empty($packages)) {
            $io->warning("No packages found in {$filePath}");
            return Command::SUCCESS;
        }

        $io->text("Pushing " . count($packages) . " packages from {$filePath}");
        $io->text("Stripe sync: " . ($syncStripe ? 'ENABLED' : 'OFF (use --with-stripe to enable)'));
        $io->newLine();

        // Show what will be synced
        foreach ($packages as $pkg) {
            $io->text("  {$pkg['slug']} — \${$pkg['price']}/{$pkg['billing_period']}");
        }
        $io->newLine();

        $result = $iris->packages->sync($packages, $syncStripe);
        $data = $result['data'] ?? $result;

        $created = $data['created'] ?? 0;
        $updated = $data['updated'] ?? 0;
        $errors = $data['errors'] ?? [];

        $io->success("Sync complete: {$created} created, {$updated} updated");

        if (!empty($errors)) {
            $io->warning('Errors:');
            foreach ($errors as $err) {
                $io->text("  - {$err}");
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Compare local JSON vs remote API
     */
    private function diffPackages(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env): int
    {
        $platform = $input->getOption('platform');
        $dir = $input->getOption('dir');
        $filePath = rtrim($dir, '/') . '/packages.json';

        $io->title("Diff Packages [{$env}]");

        if (!file_exists($filePath)) {
            $io->error("Local file not found: {$filePath}");
            $io->text("Run 'packages pull' first.");
            return Command::FAILURE;
        }

        // Load local
        $localPackages = json_decode(file_get_contents($filePath), true);
        if (!$localPackages) {
            $io->error("Invalid JSON in {$filePath}");
            return Command::FAILURE;
        }

        // Fetch remote
        $response = $iris->packages->list(['platform' => $platform]);
        $remotePackages = $response['data'] ?? $response;

        // Index by slug
        $localBySlug = [];
        foreach ($localPackages as $pkg) {
            $localBySlug[$pkg['slug']] = $pkg;
        }
        $remoteBySlug = [];
        foreach ($remotePackages as $pkg) {
            $remoteBySlug[$pkg['slug']] = $pkg;
        }

        $allSlugs = array_unique(array_merge(array_keys($localBySlug), array_keys($remoteBySlug)));
        sort($allSlugs);

        $diffs = [];
        $fieldsToCompare = ['title', 'price', 'billing_period', 'popular', 'public', 'sort_order', 'enable_free_trial', 'free_trial_days'];

        foreach ($allSlugs as $slug) {
            $local = $localBySlug[$slug] ?? null;
            $remote = $remoteBySlug[$slug] ?? null;

            if (!$remote) {
                $diffs[] = [$slug, 'NEW', '-', $local['price'] ?? '-', 'Only in local'];
                continue;
            }
            if (!$local) {
                $diffs[] = [$slug, 'REMOVED', $remote['price'] ?? '-', '-', 'Only on remote'];
                continue;
            }

            foreach ($fieldsToCompare as $field) {
                $localVal = $local[$field] ?? null;
                $remoteVal = $remote[$field] ?? null;

                // Normalize booleans for comparison
                if (is_bool($localVal)) $localVal = $localVal ? '1' : '0';
                if (is_bool($remoteVal)) $remoteVal = $remoteVal ? '1' : '0';

                if ((string) $localVal !== (string) $remoteVal) {
                    $diffs[] = [$slug, $field, (string) $remoteVal, (string) $localVal, 'CHANGED'];
                }
            }
        }

        if (empty($diffs)) {
            $io->success('No differences found — local and remote are in sync.');
            return Command::SUCCESS;
        }

        if ($input->getOption('json')) {
            $io->writeln(json_encode($diffs, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->table(['Slug', 'Field', 'Remote', 'Local', 'Status'], $diffs);
        $io->text(count($diffs) . ' difference(s) found');

        return Command::SUCCESS;
    }

    /**
     * Set a field or dot-notation path on a package
     */
    private function setField(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env): int
    {
        $slug = $input->getArgument('slug');
        $field = $input->getArgument('field');
        $value = $input->getArgument('value');
        $syncStripe = $input->getOption('with-stripe');

        if (!$slug || !$field || $value === null) {
            $io->error('Usage: packages set <slug> <field> <value>');
            $io->text('Examples:');
            $io->text('  packages set elon-growth-monthly price 250');
            $io->text('  packages set elon-growth-monthly "features.displayFeatures.0" "500 AI agents"');
            return Command::FAILURE;
        }

        // Try to parse value as JSON (for arrays/objects)
        $parsedValue = $value;
        if (($value[0] ?? '') === '[' || ($value[0] ?? '') === '{') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $parsedValue = $decoded;
            }
        }

        $io->title("Set Package Field [{$env}]");
        $io->text("Package: {$slug}");
        $io->text("Path: {$field}");
        $io->text("Value: " . (is_array($parsedValue) ? json_encode($parsedValue) : $parsedValue));
        $io->newLine();

        // Use dot-notation API for nested paths, bulk sync for simple fields
        $isDotPath = str_contains($field, '.');

        if ($isDotPath) {
            $result = $iris->packages->setPath($slug, $field, $parsedValue);
            $data = $result['data'] ?? $result;
            $io->success("Updated {$slug}: {$field}");
        } else {
            $package = ['slug' => $slug, $field => $parsedValue];
            $result = $iris->packages->sync([$package], $syncStripe);
            $data = $result['data'] ?? $result;
            $updated = $data['updated'] ?? 0;
            if ($updated > 0) {
                $io->success("Updated {$slug}: {$field} = {$value}");
            } else {
                $io->warning('No changes made');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Get a field or dot-notation path from a package
     */
    private function getField(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env): int
    {
        $slug = $input->getArgument('slug');
        $path = $input->getArgument('field');

        if (!$slug) {
            $io->error('Usage: packages get <slug> [path]');
            return Command::FAILURE;
        }

        $response = $iris->packages->get($slug);
        $package = $response['data'] ?? $response;

        if (empty($package)) {
            $io->error("Package not found: {$slug}");
            return Command::FAILURE;
        }

        // If no path, show entire package
        if (!$path) {
            $io->writeln(json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        // Traverse dot path
        $segments = explode('.', $path);
        $value = $package;
        foreach ($segments as $seg) {
            if (is_array($value) && (isset($value[$seg]) || isset($value[(int)$seg]))) {
                $value = is_numeric($seg) ? $value[(int)$seg] : $value[$seg];
            } else {
                $io->error("Path not found: {$path}");
                return Command::FAILURE;
            }
        }

        if (is_array($value)) {
            $io->writeln(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $io->writeln((string)$value);
        }

        return Command::SUCCESS;
    }

    /**
     * Show features for a package in a readable format
     */
    private function showFeatures(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env): int
    {
        $slug = $input->getArgument('slug');

        if (!$slug) {
            $io->error('Usage: packages features <slug>');
            return Command::FAILURE;
        }

        $response = $iris->packages->get($slug);
        $package = $response['data'] ?? $response;

        if (empty($package)) {
            $io->error("Package not found: {$slug}");
            return Command::FAILURE;
        }

        $features = $package['features'] ?? [];
        $title = $package['title'] ?? $slug;
        $price = $package['price'] ?? '?';

        $io->title("{$title} — \${$price}/mo [{$env}]");

        if ($input->getOption('json')) {
            $io->writeln(json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        // Display features
        $displayFeatures = $features['displayFeatures'] ?? [];
        if (!empty($displayFeatures)) {
            $io->section('Display Features (pricing page)');
            foreach ($displayFeatures as $i => $feat) {
                $io->text("  [{$i}] {$feat}");
            }
        }

        // Resource limits
        $limits = [];
        $limitKeys = ['workflows', 'contacts', 'bloqBoards', 'bloqItems'];
        foreach ($limitKeys as $key) {
            if (isset($features[$key])) {
                $val = $features[$key] == -1 ? 'Unlimited' : $features[$key];
                $limits[] = [$key, $val];
            }
        }
        if (isset($features['agents'])) {
            $agents = $features['agents'];
            $val = is_array($agents) ? ($agents['active'] == -1 ? 'Unlimited' : $agents['active']) : $agents;
            $limits[] = ['agents', $val];
        }

        if (!empty($limits)) {
            $io->section('Resource Limits');
            $io->table(['Resource', 'Limit'], $limits);
        }

        // Usage limits
        $usage = $features['usage'] ?? [];
        if (!empty($usage)) {
            $io->section('Usage Limits');
            $usageRows = [];
            foreach ($usage as $type => $vals) {
                $daily = $vals['daily'] ?? '-';
                $monthly = $vals['monthly'] ?? '-';
                $daily = $daily == -1 ? 'Unlimited' : $daily;
                $monthly = $monthly == -1 ? 'Unlimited' : $monthly;
                $usageRows[] = [$type, $daily, $monthly];
            }
            $io->table(['Type', 'Daily', 'Monthly'], $usageRows);
        }

        // Model access
        $modelAccess = $features['modelAccess'] ?? [];
        if (!empty($modelAccess) && isset($modelAccess['models'])) {
            $io->section('Model Access');
            $io->text('  Models: ' . implode(', ', $modelAccess['models']));
            $io->text('  Daily limit: ' . ($modelAccess['dailyLimit'] == -1 ? 'Unlimited' : $modelAccess['dailyLimit']));
            $io->text('  Monthly limit: ' . ($modelAccess['monthlyLimit'] == -1 ? 'Unlimited' : $modelAccess['monthlyLimit']));
        }

        // Credit tiers (PAYG)
        $creditTiers = $features['creditTiers'] ?? [];
        if (!empty($creditTiers)) {
            $io->section('Credit Tiers');
            $tierRows = [];
            foreach ($creditTiers as $tier) {
                $tierRows[] = [
                    '$' . $tier['price'],
                    number_format($tier['credits']),
                    $tier['label'] ?? '-',
                    $tier['actionsEstimate'] ?? '-',
                ];
            }
            $io->table(['Price', 'Credits', 'Label', 'Estimate'], $tierRows);
        }

        // Raw features keys for reference
        $io->section('All Feature Keys');
        $io->text('  ' . implode(', ', array_keys($features)));

        return Command::SUCCESS;
    }
}
