<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use IRIS\SDK\IRIS;
use IRIS\SDK\Config;

/**
 * CLI command for managing composable landing pages.
 *
 * Usage:
 *   ./bin/iris pages                                    # List all pages
 *   ./bin/iris pages create                             # Interactive page creation
 *   ./bin/iris pages set my-page "theme.mode" "light"   # Atomic dot-notation update
 *   ./bin/iris pages get my-page "components.0.props"   # Read value at path
 *   ./bin/iris pages pull my-page                       # Download JSON locally
 *   ./bin/iris pages push my-page                       # Upload local JSON
 *   ./bin/iris pages diff my-page                       # Compare local vs remote
 *   ./bin/iris pages publish my-page --env=production   # Publish on production
 */
class PagesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('pages')
            ->setDescription('Manage composable landing pages')
            ->setHelp(<<<'HELP'
Manage composable landing pages with JSON-based components.

Usage:
  pages                                              List all pages
  pages create                                       Interactive page creation
  pages create --slug=my-page --title="My Page"     Create with options
  pages view <slug>                                  View page JSON content
  pages validate <slug>                              Validate page against ComponentRenderer schema
  pages publish <slug>                               Publish a page
  pages unpublish <slug>                             Unpublish (back to draft)
  pages delete <slug>                                Delete a page
  pages duplicate <slug> --new-slug=copy             Duplicate a page
  pages versions <slug>                              View version history
  pages rollback <slug> --page-version=2             Rollback to version

Atomic Updates (dot notation):
  pages set <slug> <path> <value>                    Set value at JSON path
  pages get <slug> <path>                            Read value at JSON path

Examples:
  pages set genesis "theme.mode" "light"
  pages set genesis "components.0.props.title" "New Hero Title"
  pages set genesis "theme.branding.primaryColor" "#10b981"
  pages get genesis "components.0.props.title"
  pages get genesis "theme"

Pull/Push (local file workflow):
  pages pull <slug>                                  Download page JSON to ./pages/<slug>.json
  pages push <slug>                                  Upload ./pages/<slug>.json to API
  pages diff <slug>                                  Compare local file vs remote

Component Management:
  pages components <slug>                            List components
  pages add-component <slug>                         Add a component
  pages update-component <slug> --component-id=xxx   Update a component
  pages remove-component <slug> --component-id=xxx   Remove a component

Audit:
  pages audit <slug>                                 Run validation + brand checks, compute score
  pages audit <slug> --visual                        Also run Playwright visual tests (screenshots, contrast, images)
  pages audit <slug> --from-lead=413                 Attach audit report as deliverable to lead
  pages audit <slug> --visual --from-lead=413        Full pipeline: validate + visual + attach

Brand Profile:
  pages brand <slug>                                 Show current brand settings
  pages brand <slug> --from-lead=413                 Extract brand from lead notes (dry-run)
  pages brand <slug> --from-lead=413 --apply         Apply brand to page + fix component themes

AI & Advanced:
  pages ai-edit <slug> --instructions="..."          AI-edit page with natural language
  pages compose --source-type=topic --source="..."   AI-compose a full page
  pages thumbnail <slug>                             Generate preview thumbnail
  pages analytics <slug>                             View page analytics
  pages submissions <slug>                           List form submissions
  pages submissions <slug> --format=csv              Export submissions as CSV

Commerce:
  pages checkout <slug> --package-id=X --buyer-email=Y  Generate Stripe checkout link
  pages monetize <slug> --package-name="Pro" --price=29 Add pricing to page

Domains:
  pages domains                                      List custom domain mappings
  pages domains map --domain=x.com --page-id=123     Map domain to page
  pages domains verify --mapping-id=5                Verify DNS propagation
  pages domains remove --mapping-id=5                Remove domain mapping

Environment:
  pages list --env=production                        Target production API
  pages list --env=local                             Target local API
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: list, create, view, validate, audit, brand, set, get, pull, push, diff, publish, unpublish, delete, duplicate, versions, rollback, components, add-component, update-component, remove-component, ai-edit, analytics, submissions, checkout, monetize, compose, thumbnail, domains')
            ->addArgument('slug', InputArgument::OPTIONAL, 'Page slug')
            ->addArgument('path', InputArgument::OPTIONAL, 'Dot-notation path (for set/get)')
            ->addArgument('value', InputArgument::OPTIONAL, 'Value to set (for set)')
            // Common options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment: local or production (overrides IRIS_ENV)')
            // Page options
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Page slug')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Page title')
            ->addOption('seo-title', null, InputOption::VALUE_REQUIRED, 'SEO title')
            ->addOption('seo-description', null, InputOption::VALUE_REQUIRED, 'SEO description')
            ->addOption('template', null, InputOption::VALUE_REQUIRED, 'Template: landing, product, about, contact')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Status: draft, published, archived', 'draft')
            ->addOption('new-slug', null, InputOption::VALUE_REQUIRED, 'New slug for duplication')
            ->addOption('page-version', null, InputOption::VALUE_REQUIRED, 'Version number for rollback')
            // Dot notation options (alternative to positional args)
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Dot-notation path (for set/get)')
            ->addOption('value', null, InputOption::VALUE_REQUIRED, 'Value to set (for set, supports JSON)')
            // Pull/push options
            ->addOption('dir', null, InputOption::VALUE_REQUIRED, 'Directory for pull/push files', './pages')
            // Component options
            ->addOption('add-hero', null, InputOption::VALUE_NONE, 'Add a Hero component')
            ->addOption('add-text', null, InputOption::VALUE_NONE, 'Add a TextBlock component')
            ->addOption('add-button', null, InputOption::VALUE_NONE, 'Add a ButtonCTA component')
            ->addOption('component-id', null, InputOption::VALUE_REQUIRED, 'Component ID for update/remove')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Component type: Hero, TextBlock, ButtonCTA')
            ->addOption('position', null, InputOption::VALUE_REQUIRED, 'Position to insert component (0-based index)')
            ->addOption('props', null, InputOption::VALUE_REQUIRED, 'Component props as JSON string')
            // Brand options
            ->addOption('from-lead', null, InputOption::VALUE_REQUIRED, 'Lead ID to pull brand specs from (for brand/audit action)')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply brand changes (for brand action, without this flag it is dry-run)')
            ->addOption('visual', null, InputOption::VALUE_NONE, 'Run Playwright visual tests (for audit action)')
            // Event options
            ->addOption('event-date', null, InputOption::VALUE_REQUIRED, 'Event date (e.g., "March 28, 2026")')
            ->addOption('event-type', null, InputOption::VALUE_REQUIRED, 'Event type: conference, concert, meetup, workshop, party, corporate, fundraiser, festival, wedding')
            ->addOption('venue', null, InputOption::VALUE_REQUIRED, 'Venue name/address')
            ->addOption('venue-map-url', null, InputOption::VALUE_REQUIRED, 'Google Maps or map link for the venue')
            ->addOption('speakers', null, InputOption::VALUE_REQUIRED, 'Speakers JSON array: [{"name":"Jane","role":"CEO"}]')
            ->addOption('schedule', null, InputOption::VALUE_REQUIRED, 'Schedule JSON array: [{"time":"9am","session":"Keynote","speaker":"Jane"}]')
            ->addOption('tickets', null, InputOption::VALUE_REQUIRED, 'Tickets JSON array: [{"name":"GA","price":50,"features":["All talks"]}]')
            ->addOption('no-registration', null, InputOption::VALUE_NONE, 'Skip the RSVP/registration form')
            // List filter options
            ->addOption('page-type', null, InputOption::VALUE_REQUIRED, 'Filter list by page template: event, landing, product, about, contact')
            // AI edit options
            ->addOption('instructions', null, InputOption::VALUE_REQUIRED, 'AI editing instructions (for ai-edit)')
            // Analytics / checkout / thumbnail options
            ->addOption('page-id', null, InputOption::VALUE_REQUIRED, 'Page ID (alternative to slug for analytics, checkout, thumbnail)')
            ->addOption('package-id', null, InputOption::VALUE_REQUIRED, 'Stripe package ID (for checkout)')
            ->addOption('buyer-email', null, InputOption::VALUE_REQUIRED, 'Buyer email (for checkout)')
            // Compose options
            ->addOption('source-type', null, InputOption::VALUE_REQUIRED, 'Source type: video, url, topic, content (for compose)')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Source URL or text (for compose)')
            ->addOption('style', null, InputOption::VALUE_REQUIRED, 'Page style: landing, article, product, portfolio (for compose)')
            ->addOption('theme-mode', null, InputOption::VALUE_REQUIRED, 'Theme: dark or light (for compose)')
            ->addOption('include-form', null, InputOption::VALUE_NONE, 'Include enrollment form (for compose)')
            // Domain options
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Custom domain name (for domains)')
            ->addOption('mapping-id', null, InputOption::VALUE_REQUIRED, 'Domain mapping ID (for domains verify/remove)')
            // Submissions options
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Export format: json, csv (for submissions)', 'json')
            // Monetization options
            ->addOption('package-name', null, InputOption::VALUE_REQUIRED, 'Pricing package name (for monetize)')
            ->addOption('price', null, InputOption::VALUE_REQUIRED, 'Package price (for monetize)')
            ->addOption('billing-type', null, InputOption::VALUE_REQUIRED, 'Billing: one_time, monthly, yearly (for monetize)', 'one_time')
            ->addOption('features', null, InputOption::VALUE_REQUIRED, 'Package features, comma-separated (for monetize)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'list';
        $slug = $input->getArgument('slug') ?? $input->getOption('slug');

        // Handle --env flag before loading config
        $env = $input->getOption('env');
        if ($env) {
            putenv("IRIS_ENV={$env}");
            $_ENV['IRIS_ENV'] = $env;
        }

        // Get API credentials
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID');

        // Try to load from .env if not provided
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
            $io->error([
                'Missing API credentials.',
                '',
                'Set environment variables or use options:',
                '  IRIS_API_KEY=your-api-key',
                '  IRIS_USER_ID=your-user-id',
            ]);
            return Command::FAILURE;
        }

        // Initialize SDK
        $iris = new IRIS([
            'api_key' => $apiKey,
            'user_id' => (int) $userId,
        ]);

        // Show environment info
        $currentEnv = $env ?: (getenv('IRIS_ENV') ?: 'production');
        $baseUrl = $iris->getConfig()->baseUrl;

        try {
            switch ($action) {
                case 'list':
                    return $this->listPages($iris, $io, $input, $currentEnv, $baseUrl);

                case 'create':
                    return $this->createPage($iris, $io, $input);

                case 'edit':
                    return $this->editPage($iris, $io, $input, $slug);

                case 'view':
                    return $this->viewPage($iris, $io, $input, $slug);

                case 'validate':
                    return $this->validatePage($iris, $io, $input, $slug);

                case 'set':
                    return $this->setPath($iris, $io, $input, $slug);

                case 'get':
                    return $this->getPath($iris, $io, $input, $slug);

                case 'pull':
                    return $this->pullPage($iris, $io, $input, $slug);

                case 'push':
                    return $this->pushPage($iris, $io, $input, $slug);

                case 'diff':
                    return $this->diffPage($iris, $io, $input, $slug);

                case 'publish':
                    return $this->publishPage($iris, $io, $slug);

                case 'unpublish':
                    return $this->unpublishPage($iris, $io, $slug);

                case 'delete':
                    return $this->deletePage($iris, $io, $input, $slug);

                case 'duplicate':
                    return $this->duplicatePage($iris, $io, $input, $slug);

                case 'versions':
                    return $this->viewVersions($iris, $io, $slug);

                case 'rollback':
                    return $this->rollbackPage($iris, $io, $input, $slug);

                case 'components':
                    return $this->listComponents($iris, $io, $slug);

                case 'add-component':
                    return $this->addComponent($iris, $io, $input, $slug);

                case 'update-component':
                    $componentId = $input->getOption('component-id');
                    return $this->updateComponent($iris, $io, $input, $slug, $componentId);

                case 'remove-component':
                    $componentId = $input->getOption('component-id');
                    return $this->removeComponent($iris, $io, $slug, $componentId);

                case 'audit':
                    return $this->auditPage($iris, $io, $input, $slug);

                case 'brand':
                    return $this->brandPage($iris, $io, $input, $slug);

                case 'ai-edit':
                    return $this->aiEditPage($iris, $io, $input, $slug);

                case 'analytics':
                    return $this->analyticsPage($iris, $io, $input, $slug);

                case 'submissions':
                    return $this->submissionsPage($iris, $io, $input, $slug);

                case 'checkout':
                    return $this->checkoutPage($iris, $io, $input, $slug);

                case 'monetize':
                    return $this->monetizePage($iris, $io, $input, $slug);

                case 'compose':
                    return $this->composeNewPage($iris, $io, $input);

                case 'thumbnail':
                    return $this->thumbnailPage($iris, $io, $input, $slug);

                case 'domains':
                    return $this->domainsPage($iris, $io, $input, $slug);

                default:
                    $io->error("Unknown action: {$action}");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    // ─── Atomic Dot-Notation Commands ─────────────────────────────────

    private function setPath(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages set <slug> <path> <value>');
            return Command::FAILURE;
        }

        $path = $input->getArgument('path') ?? $input->getOption('path');
        $value = $input->getArgument('value') ?? $input->getOption('value');

        if (!$path) {
            $io->error('Path is required. Usage: pages set <slug> <path> <value>');
            $io->text([
                'Examples:',
                '  pages set genesis "theme.mode" "light"',
                '  pages set genesis "components.0.props.title" "New Title"',
                '  pages set genesis "theme.branding.primaryColor" "#10b981"',
            ]);
            return Command::FAILURE;
        }

        if ($value === null) {
            $io->error('Value is required. Usage: pages set <slug> <path> <value>');
            return Command::FAILURE;
        }

        // Auto-detect JSON values
        $parsedValue = $this->parseValue($value);

        // Resolve page ID from slug
        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        // Perform the atomic update
        $result = $iris->pages->updatePath($page['id'], $path, $parsedValue);

        $displayValue = is_array($parsedValue) ? json_encode($parsedValue) : $parsedValue;
        $io->success("Updated {$slug} -> {$path} = {$displayValue}");

        return Command::SUCCESS;
    }

    private function getPath(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages get <slug> <path>');
            return Command::FAILURE;
        }

        $path = $input->getArgument('path') ?? $input->getOption('path');

        // Fetch page with JSON content
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;
        $jsonContent = $page['json_content'] ?? [];

        if (!$path) {
            // No path = show full JSON content
            $io->writeln(json_encode($jsonContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        // Navigate dot notation path
        $value = $this->getNestedValue($jsonContent, $path);

        if ($value === null) {
            $io->warning("Path '{$path}' not found in page '{$slug}'");
            return Command::FAILURE;
        }

        if (is_array($value)) {
            $io->writeln(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $io->writeln((string) $value);
        }

        return Command::SUCCESS;
    }

    // ─── Pull / Push / Diff Commands ──────────────────────────────────

    private function pullPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages pull <slug>');
            return Command::FAILURE;
        }

        $dir = $input->getOption('dir');

        // Fetch page with full JSON
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;

        // Ensure directory exists
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = rtrim($dir, '/') . "/{$slug}.json";

        // Build the export object (page metadata + JSON content)
        $export = [
            'id' => $page['id'],
            'slug' => $page['slug'],
            'title' => $page['title'],
            'seo_title' => $page['seo_title'] ?? null,
            'seo_description' => $page['seo_description'] ?? null,
            'og_image' => $page['og_image'] ?? null,
            'status' => $page['status'],
            'owner_type' => $page['owner_type'] ?? 'system',
            'owner_id' => $page['owner_id'] ?? null,
            'json_content' => $page['json_content'] ?? [],
        ];

        file_put_contents($filePath, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $componentCount = count($export['json_content']['components'] ?? []);
        $io->success("Pulled '{$slug}' -> {$filePath} ({$componentCount} components)");

        return Command::SUCCESS;
    }

    private function pushPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages push <slug>');
            return Command::FAILURE;
        }

        $dir = $input->getOption('dir');
        $filePath = rtrim($dir, '/') . "/{$slug}.json";

        if (!file_exists($filePath)) {
            $io->error("Local file not found: {$filePath}");
            $io->note("Pull first: ./bin/iris pages pull {$slug}");
            return Command::FAILURE;
        }

        $localJson = json_decode(file_get_contents($filePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON in {$filePath}: " . json_last_error_msg());
            return Command::FAILURE;
        }

        // Resolve page ID from slug (page must already exist remotely)
        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        // Build update payload
        $updateData = [];
        if (isset($localJson['title'])) {
            $updateData['title'] = $localJson['title'];
        }
        if (isset($localJson['seo_title'])) {
            $updateData['seo_title'] = $localJson['seo_title'];
        }
        if (isset($localJson['seo_description'])) {
            $updateData['seo_description'] = $localJson['seo_description'];
        }
        if (isset($localJson['og_image'])) {
            $updateData['og_image'] = $localJson['og_image'];
        }
        if (isset($localJson['json_content'])) {
            $updateData['json_content'] = $localJson['json_content'];
        }

        $result = $iris->pages->update($page['id'], $updateData);

        $componentCount = count($localJson['json_content']['components'] ?? []);
        $io->success("Pushed '{$slug}' from {$filePath} ({$componentCount} components)");
        $io->note("A new version has been created. Use 'pages versions {$slug}' to see history.");

        return Command::SUCCESS;
    }

    private function diffPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages diff <slug>');
            return Command::FAILURE;
        }

        $dir = $input->getOption('dir');
        $filePath = rtrim($dir, '/') . "/{$slug}.json";

        if (!file_exists($filePath)) {
            $io->error("Local file not found: {$filePath}");
            $io->note("Pull first: ./bin/iris pages pull {$slug}");
            return Command::FAILURE;
        }

        // Load local
        $localJson = json_decode(file_get_contents($filePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error("Invalid JSON in {$filePath}: " . json_last_error_msg());
            return Command::FAILURE;
        }

        // Fetch remote
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;

        $localContent = $localJson['json_content'] ?? [];
        $remoteContent = $page['json_content'] ?? [];

        $localEncoded = json_encode($localContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $remoteEncoded = json_encode($remoteContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($localEncoded === $remoteEncoded) {
            $io->success("No differences — local and remote are identical.");
            return Command::SUCCESS;
        }

        $io->title("Diff: {$slug}");

        // Compare metadata
        $metaFields = ['title', 'seo_title', 'seo_description'];
        $metaDiffs = [];
        foreach ($metaFields as $field) {
            $localVal = $localJson[$field] ?? null;
            $remoteVal = $page[$field] ?? null;
            if ($localVal !== $remoteVal) {
                $metaDiffs[] = [$field, $remoteVal ?? '(empty)', $localVal ?? '(empty)'];
            }
        }
        if (!empty($metaDiffs)) {
            $io->section('Metadata Changes');
            $io->table(['Field', 'Remote', 'Local'], $metaDiffs);
        }

        // Compare components
        $localComponents = $localContent['components'] ?? [];
        $remoteComponents = $remoteContent['components'] ?? [];

        $io->section('Components');
        $io->text("Remote: " . count($remoteComponents) . " components");
        $io->text("Local:  " . count($localComponents) . " components");

        // Show component-level diffs
        $maxCount = max(count($localComponents), count($remoteComponents));
        $diffs = [];
        for ($i = 0; $i < $maxCount; $i++) {
            $local = $localComponents[$i] ?? null;
            $remote = $remoteComponents[$i] ?? null;

            if ($local === null) {
                $diffs[] = [$i, '<fg=red>REMOVED</>', $remote['type'] ?? '?', '-'];
            } elseif ($remote === null) {
                $diffs[] = [$i, '<fg=green>ADDED</>', '-', $local['type'] ?? '?'];
            } elseif (json_encode($local) !== json_encode($remote)) {
                $diffs[] = [$i, '<fg=yellow>CHANGED</>', $remote['type'] ?? '?', $local['type'] ?? '?'];
            }
        }

        if (empty($diffs)) {
            $io->text('Component structure identical (possible whitespace/formatting differences).');
        } else {
            $io->table(['Index', 'Status', 'Remote Type', 'Local Type'], $diffs);
        }

        // Theme diff
        $localTheme = json_encode($localContent['theme'] ?? [], JSON_PRETTY_PRINT);
        $remoteTheme = json_encode($remoteContent['theme'] ?? [], JSON_PRETTY_PRINT);
        if ($localTheme !== $remoteTheme) {
            $io->section('Theme Changes');
            $io->text('<fg=red>Remote:</>');
            $io->writeln($remoteTheme);
            $io->text('<fg=green>Local:</>');
            $io->writeln($localTheme);
        }

        $io->note("To apply local changes: ./bin/iris pages push {$slug}");

        return Command::SUCCESS;
    }

    // ─── Existing Commands (with fixes) ───────────────────────────────

    private function listPages(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $env, string $baseUrl): int
    {
        $io->title("Pages [{$env}]");
        $io->text("<fg=gray>API: {$baseUrl}</>");

        $response = $iris->pages->list();
        $pages = $response['data'] ?? [];

        // Filter by page template type if --page-type is set
        $pageType = $input->getOption('page-type');
        if ($pageType) {
            $pages = array_filter($pages, function ($page) use ($pageType) {
                $template = $page['json_content']['meta']['template'] ?? null;
                $type = $page['json_content']['type'] ?? null;
                return $template === $pageType || $type === $pageType;
            });
            $pages = array_values($pages);
        }

        if (empty($pages)) {
            $io->info($pageType ? "No {$pageType} pages found." : 'No pages found.');
            return Command::SUCCESS;
        }

        if ($input->getOption('json')) {
            $io->writeln(json_encode($pages, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($pages as $page) {
            $template = $page['json_content']['meta']['template'] ?? $page['json_content']['type'] ?? '-';
            $rows[] = [
                $page['id'],
                $page['slug'],
                $page['title'],
                $template,
                $this->formatStatus($page['status']),
                $this->getPublicUrl($page['slug']),
            ];
        }

        $io->table(
            ['ID', 'Slug', 'Title', 'Type', 'Status', 'URL'],
            $rows
        );

        return Command::SUCCESS;
    }

    private function createPage(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('Create New Page');

        // Get basic info
        $slug = $input->getOption('slug') ?? $io->ask('Page slug (URL-friendly)', 'my-page');
        $title = $input->getOption('title') ?? $io->ask('Page title', 'My Landing Page');
        $seoTitle = $input->getOption('seo-title') ?? $io->ask('SEO title (optional)', $title);
        $seoDescription = $input->getOption('seo-description') ?? $io->ask('SEO description (optional)');

        // Ask about template
        $template = $input->getOption('template');
        if (!$template) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Choose a template (or skip to build custom)',
                ['Skip (custom)', 'landing', 'product', 'about', 'contact', 'event'],
                0
            );
            $template = $helper->ask($input, $io, $question);
            if ($template === 'Skip (custom)') {
                $template = null;
            }
        }

        $io->section('Building page...');

        // Create from template or custom
        if ($template === 'event') {
            $page = $this->createEventFromCli($iris, $io, $input, $slug, $title, $seoTitle, $seoDescription);
        } elseif ($template) {
            $page = $iris->pages->createFromTemplate($template, [
                'slug' => $slug,
                'title' => $title,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
                'status' => 'draft',
            ]);
        } else {
            // Interactive component builder
            $components = [];

            if ($input->getOption('add-hero') || $io->confirm('Add Hero section?', true)) {
                $components[] = $this->buildHeroComponent($io, $input);
            }

            if ($input->getOption('add-text') || $io->confirm('Add Text block?', true)) {
                $components[] = $this->buildTextComponent($io, $input);
            }

            if ($input->getOption('add-button') || $io->confirm('Add CTA button?', false)) {
                $components[] = $this->buildButtonComponent($io, $input);
            }

            $page = $iris->pages->create([
                'slug' => $slug,
                'title' => $title,
                'seo_title' => $seoTitle,
                'seo_description' => $seoDescription,
                'status' => 'draft',
                'theme' => [
                    'mode' => 'dark',
                    'branding' => [
                        'name' => $title,
                        'primaryColor' => '#6366f1',
                        'secondaryColor' => '#8b5cf6',
                    ],
                ],
                'components' => $components,
            ]);
        }

        $pageData = $page['data'] ?? $page;
        $publicUrl = $this->getPublicUrl($pageData['slug']);
        $io->success("Page created successfully!");
        $io->definitionList(
            ['ID' => $pageData['id']],
            ['Slug' => $pageData['slug']],
            ['Title' => $pageData['title']],
            ['Status' => $pageData['status']],
            ['Components' => count($pageData['json_content']['components'] ?? [])],
            ['URL' => $publicUrl]
        );

        $io->note([
            "URL: {$publicUrl}",
            "View: ./bin/iris pages view {$pageData['slug']}",
            "Publish: ./bin/iris pages publish {$pageData['slug']}",
        ]);

        return Command::SUCCESS;
    }

    /**
     * Collect event data from CLI options or interactive prompts, then create event page.
     */
    private function createEventFromCli(IRIS $iris, SymfonyStyle $io, InputInterface $input, string $slug, string $title, ?string $seoTitle, ?string $seoDescription): array
    {
        $io->section('Event Page Details');

        // Required: event date
        $eventDate = $input->getOption('event-date');
        if (!$eventDate) {
            $eventDate = $io->ask('Event date (e.g., March 28, 2026)');
        }
        if (!$eventDate) {
            throw new \RuntimeException('Event date is required. Use --event-date="March 28, 2026"');
        }

        // Optional: event type
        $eventType = $input->getOption('event-type');
        if (!$eventType && !$input->getOption('slug')) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Event type',
                ['conference', 'concert', 'meetup', 'workshop', 'party', 'corporate', 'fundraiser', 'festival', 'wedding'],
                0
            );
            $eventType = $helper->ask($input, $io, $question);
        }

        // Optional: venue
        $venue = $input->getOption('venue');
        $venueMapUrl = $input->getOption('venue-map-url');

        // Optional: description
        $description = $seoDescription;

        // Optional: speakers (JSON from CLI option)
        $speakers = null;
        $speakersJson = $input->getOption('speakers');
        if ($speakersJson) {
            $speakers = json_decode($speakersJson, true);
            if (!is_array($speakers)) {
                throw new \RuntimeException('Invalid --speakers JSON. Expected array: [{"name":"Jane","role":"CEO"}]');
            }
        }

        // Optional: schedule (JSON from CLI option)
        $schedule = null;
        $scheduleJson = $input->getOption('schedule');
        if ($scheduleJson) {
            $schedule = json_decode($scheduleJson, true);
            if (!is_array($schedule)) {
                throw new \RuntimeException('Invalid --schedule JSON. Expected array: [{"time":"9am","session":"Keynote"}]');
            }
        }

        // Optional: tickets (JSON from CLI option)
        $tickets = null;
        $ticketsJson = $input->getOption('tickets');
        if ($ticketsJson) {
            $tickets = json_decode($ticketsJson, true);
            if (!is_array($tickets)) {
                throw new \RuntimeException('Invalid --tickets JSON. Expected array: [{"name":"GA","price":50}]');
            }
        }

        $includeRegistration = !$input->getOption('no-registration');

        // Build event data
        $eventData = [
            'slug' => $slug,
            'title' => $title,
            'date' => $eventDate,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'event_type' => $eventType ?? 'conference',
            'include_registration' => $includeRegistration,
        ];

        if ($description) {
            $eventData['description'] = $description;
        }
        if ($venue) {
            $eventData['venue'] = $venue;
        }
        if ($venueMapUrl) {
            $eventData['venue_map_url'] = $venueMapUrl;
        }
        if ($speakers) {
            $eventData['speakers'] = $speakers;
        }
        if ($schedule) {
            $eventData['schedule'] = $schedule;
        }
        if ($tickets) {
            $eventData['tickets'] = $tickets;
        }

        $io->text([
            "Event: <info>{$title}</info>",
            "Date: <info>{$eventDate}</info>",
            "Type: <info>" . ($eventType ?? 'conference') . "</info>",
            $venue ? "Venue: <info>{$venue}</info>" : '',
            $speakers ? "Speakers: <info>" . count($speakers) . "</info>" : '',
            $schedule ? "Schedule items: <info>" . count($schedule) . "</info>" : '',
            $tickets ? "Ticket tiers: <info>" . count($tickets) . "</info>" : '',
            "Registration form: <info>" . ($includeRegistration ? 'Yes' : 'No') . "</info>",
        ]);

        return $iris->pages->createFromTemplate('event', $eventData);
    }

    private function buildHeroComponent(SymfonyStyle $io, InputInterface $input): array
    {
        $io->section('Hero Component');

        $title = $io->ask('Hero title', 'Welcome to Our Platform');
        $subtitle = $io->ask('Hero subtitle (optional)', 'Build amazing experiences');

        $helper = $this->getHelper('question');
        $gradientQuestion = new ChoiceQuestion(
            'Choose gradient preset',
            [
                'Purple (default)' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'Green-Blue' => 'linear-gradient(135deg, #10b981 0%, #3b82f6 100%)',
                'Orange-Pink' => 'linear-gradient(135deg, #f97316 0%, #ec4899 100%)',
                'Dark' => 'linear-gradient(to right, #1e293b, #334155)',
                'Custom' => 'custom',
            ],
            0
        );
        $gradient = $helper->ask($input, $io, $gradientQuestion);

        if ($gradient === 'custom') {
            $gradient = $io->ask('CSS gradient', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)');
        }

        return [
            'type' => 'Hero',
            'id' => 'hero-' . uniqid(),
            'props' => [
                'title' => $title,
                'subtitle' => $subtitle,
                'backgroundGradient' => $gradient,
                'titleColor' => '#ffffff',
                'subtitleColor' => 'rgba(255, 255, 255, 0.9)',
                'textAlign' => 'center',
                'minHeight' => '500px',
            ],
        ];
    }

    private function buildTextComponent(SymfonyStyle $io, InputInterface $input): array
    {
        $io->section('Text Block Component');

        $content = $io->ask('Markdown content', "## About Us\n\nWe provide cutting-edge solutions.");

        return [
            'type' => 'TextBlock',
            'id' => 'text-' . uniqid(),
            'props' => [
                'content' => $content,
                'markdown' => true,
                'textAlign' => 'center',
                'maxWidth' => '4xl',
                'themeMode' => 'dark',
            ],
        ];
    }

    private function buildButtonComponent(SymfonyStyle $io, InputInterface $input): array
    {
        $io->section('Button CTA Component');

        $text = $io->ask('Button text', 'Get Started');
        $href = $io->ask('Button URL', 'https://example.com/signup');

        return [
            'type' => 'ButtonCTA',
            'id' => 'btn-' . uniqid(),
            'props' => [
                'text' => $text,
                'href' => $href,
                'variant' => 'primary',
                'size' => 'lg',
            ],
        ];
    }

    private function viewPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;

        if ($input->getOption('json')) {
            $io->writeln(json_encode($page, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $io->title("Page: {$page['title']}");
        $publicUrl = $this->getPublicUrl($page['slug']);
        $io->definitionList(
            ['ID' => $page['id']],
            ['Slug' => $page['slug']],
            ['Title' => $page['title']],
            ['Status' => $this->formatStatus($page['status'])],
            ['Published' => $page['published_at'] ?? 'Not published'],
            ['URL' => $publicUrl]
        );

        $io->section('JSON Content');
        $io->writeln(json_encode($page['json_content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    private function publishPage(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->publish($page['id']);
        $publicUrl = $this->getPublicUrl($slug);
        $io->success("Page '{$slug}' published!");
        $io->text("URL: <href={$publicUrl}>{$publicUrl}</>");

        return Command::SUCCESS;
    }

    private function unpublishPage(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->unpublish($page['id']);
        $io->success("Page '{$slug}' unpublished (back to draft)");

        return Command::SUCCESS;
    }

    private function deletePage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion("Are you sure you want to delete '{$slug}'? [y/N] ", false);

        if (!$helper->ask($input, $io, $question)) {
            $io->info('Cancelled');
            return Command::SUCCESS;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $iris->pages->delete($page['id']);
        $io->success("Page '{$slug}' deleted");

        return Command::SUCCESS;
    }

    private function duplicatePage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $newSlug = $input->getOption('new-slug') ?? $io->ask('New slug for duplicate', $slug . '-copy');

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->duplicate($page['id'], $newSlug);
        $publicUrl = $this->getPublicUrl($newSlug);
        $io->success("Page duplicated as '{$newSlug}'!");
        $io->text("URL: {$publicUrl}");

        return Command::SUCCESS;
    }

    private function viewVersions(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $response = $iris->pages->versions($page['id']);
        $versions = $response['data'] ?? $response;

        $io->title("Version History: {$page['title']}");

        if (empty($versions)) {
            $io->info('No version history');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($versions as $version) {
            $rows[] = [
                $version['version_number'] ?? '?',
                $version['change_summary'] ?? 'No summary',
                isset($version['created_at']) ? date('Y-m-d H:i', strtotime($version['created_at'])) : '-',
                $version['changed_by'] ?? '-',
            ];
        }

        $io->table(['Version', 'Summary', 'Date', 'Changed By'], $rows);

        return Command::SUCCESS;
    }

    private function rollbackPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $version = $input->getOption('page-version');
        if (!$version) {
            $io->error('Version number is required (use --page-version=N)');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->rollback($page['id'], (int) $version);
        $io->success("Rolled back '{$slug}' to version {$version}");

        return Command::SUCCESS;
    }

    private function editPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $io->title("Edit Page: {$slug}");
        $io->text([
            'Use these commands for editing:',
            '',
            "  <fg=cyan>Atomic updates (dot notation):</>",
            "  ./bin/iris pages set {$slug} \"components.0.props.title\" \"New Title\"",
            "  ./bin/iris pages set {$slug} \"theme.mode\" \"light\"",
            '',
            "  <fg=cyan>Full JSON editing:</>",
            "  ./bin/iris pages pull {$slug}       # Download to ./pages/{$slug}.json",
            "  # Edit the file locally",
            "  ./bin/iris pages push {$slug}       # Upload changes",
            '',
            "  <fg=cyan>View current state:</>",
            "  ./bin/iris pages get {$slug} \"components.0.props\"",
            "  ./bin/iris pages view {$slug} --json",
        ]);

        return Command::SUCCESS;
    }

    private function formatStatus(string $status): string
    {
        return match($status) {
            'published' => '<fg=green>● Published</>',
            'draft' => '<fg=yellow>○ Draft</>',
            'archived' => '<fg=gray>◌ Archived</>',
            default => $status,
        };
    }

    /**
     * Get the public URL for a page by slug.
     */
    private function getPublicUrl(string $slug): string
    {
        $currentEnv = getenv('IRIS_ENV') ?: 'production';
        return $currentEnv === 'local'
            ? "http://local.iris.freelabel.net:9300/p/{$slug}"
            : "https://heyiris.io/p/{$slug}";
    }

    private function listComponents(IRIS $iris, SymfonyStyle $io, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $components = $iris->pages->getComponents($page['id']);

        $io->title("Components: {$page['title']}");

        if (empty($components)) {
            $io->info('No components found');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($components as $index => $component) {
            $preview = $component['props']['title']
                ?? $component['props']['text']
                ?? $component['props']['content']
                ?? 'N/A';
            $rows[] = [
                $index,
                $component['id'] ?? 'N/A',
                $component['type'],
                substr((string) $preview, 0, 50),
            ];
        }

        $io->table(['Index', 'ID', 'Type', 'Preview'], $rows);

        $io->text([
            '',
            "Update a component: ./bin/iris pages set {$slug} \"components.<index>.props.<key>\" \"<value>\"",
        ]);

        return Command::SUCCESS;
    }

    private function addComponent(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        // Get component type
        $type = $input->getOption('type');
        if (!$type) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Component type',
                ['Hero', 'TextBlock', 'ButtonCTA'],
                0
            );
            $type = $helper->ask($input, $io, $question);
        }

        // Build component based on type
        $component = match($type) {
            'Hero' => $this->buildHeroComponent($io, $input),
            'TextBlock' => $this->buildTextComponent($io, $input),
            'ButtonCTA' => $this->buildButtonComponent($io, $input),
            default => null,
        };

        if (!$component) {
            $io->error("Unknown component type: {$type}");
            return Command::FAILURE;
        }

        // Get position
        $position = $input->getOption('position');
        if ($position === null) {
            $position = $io->ask('Position (0 for start, leave empty for end)', '');
            if ($position === '') {
                $position = null;
            } else {
                $position = (int) $position;
            }
        }

        $result = $iris->pages->addComponent($page['id'], $component, $position);
        $io->success("Component added successfully!");

        return Command::SUCCESS;
    }

    private function updateComponent(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug, ?string $componentId): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        if (!$componentId) {
            $io->error('Component ID is required (use --component-id=xxx)');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;

        // Get props update from option or interactive
        $propsJson = $input->getOption('props');
        if ($propsJson) {
            $decoded = json_decode($propsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON: ' . json_last_error_msg());
                return Command::FAILURE;
            }
            $updates = ['props' => $decoded];
        } else {
            $io->section('Update Component Props');
            $io->note('Enter JSON object for props (e.g., {"title": "New Title"})');
            $propsJson = $io->ask('Props JSON', '{}');
            $decoded = json_decode($propsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON: ' . json_last_error_msg());
                return Command::FAILURE;
            }
            $updates = ['props' => $decoded];
        }

        $result = $iris->pages->updateComponentById($page['id'], $componentId, $updates);
        $io->success("Component updated successfully!");

        return Command::SUCCESS;
    }

    private function removeComponent(IRIS $iris, SymfonyStyle $io, ?string $slug, ?string $componentId): int
    {
        if (!$slug) {
            $io->error('Slug is required');
            return Command::FAILURE;
        }

        if (!$componentId) {
            $io->error('Component ID is required (use --component-id=xxx)');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->removeComponentById($page['id'], $componentId);
        $io->success("Component removed successfully!");

        return Command::SUCCESS;
    }

    // ─── Validate Command ────────────────────────────────────────────

    /**
     * Component type registry — maps each type to its expected properties.
     * Derived from ComponentRenderer.vue template bindings.
     */
    private function getComponentSchema(): array
    {
        return [
            // Primitives
            'heading' => ['text', 'level', 'align'],
            'paragraph' => ['text', 'align'],
            'image' => ['src', 'alt', 'caption', 'width'],
            'list' => ['items', 'ordered'],
            'button' => ['text', 'url', 'variant'],
            'video' => ['url', 'title', 'autoplay'],
            'carousel' => ['items', 'autoAdvance', 'interval'],
            'social-feed' => ['platform', 'handle', 'feedType'],
            'music-player' => ['tracks', 'title'],
            'contact-form' => ['fields', 'submitText', 'action'],
            'section' => ['backgroundColor'],
            'container' => ['maxWidth'],
            'row' => ['gap', 'align'],
            'column' => ['span', 'align'],
            'survey' => [],

            // Heroes
            'Hero' => ['title', 'subtitle', 'backgroundGradient', 'backgroundImage', 'overlayGradient', 'labelText', 'labelColor', 'primaryButtonText', 'primaryButtonUrl', 'primaryButtonVariant', 'secondaryButtonText', 'secondaryButtonUrl', 'secondaryButtonVariant', 'textAlign', 'fullHeight', 'showScrollIndicator', 'backgroundPattern'],
            'JumbotronHero' => ['title', 'subtitle', 'description', 'logo', 'backgroundImage', 'ctaText', 'ctaUrl'],
            'MarketingHero' => ['title', 'subtitle', 'logoImageUrl', 'backgroundImage', 'ctaText', 'ctaUrl', 'features'],

            // Content
            'TextBlock' => ['content', 'markdown', 'textAlign', 'maxWidth', 'themeMode'],
            'Callout' => ['content', 'markdown', 'type', 'title', 'icon', 'themeMode'],
            'CodeBlock' => ['code', 'language', 'title', 'themeMode'],
            'QuoteBlock' => ['title', 'subtitle', 'quote', 'attribution', 'labelText', 'labelColor', 'ctaText', 'ctaUrl', 'themeMode'],
            'ButtonCTA' => ['text', 'href', 'variant', 'size', 'icon', 'themeMode'],
            'VideoBlock' => ['videoUrl', 'title', 'description', 'themeMode'],

            // Navigation
            'SiteNavigation' => ['logo', 'links', 'ctaText', 'ctaUrl', 'ctaColor', 'ctaButton', 'ctaFilled', 'themeMode', 'transparent'],
            'SiteFooter' => ['columns', 'copyright', 'socialLinks', 'logo', 'themeMode'],

            // Grids & features
            'FeatureGrid' => ['heading', 'subheading', 'features', 'columns', 'themeMode'],
            'FeatureCardsGrid' => ['heading', 'subheading', 'features', 'themeMode'],
            'FeatureIconsGrid' => ['heading', 'subheading', 'features', 'themeMode'],
            'SkillsGrid' => ['heading', 'subheading', 'skills', 'themeMode'],
            'PortfolioGrid' => ['heading', 'subheading', 'items', 'themeMode'],
            'ProductGrid' => ['heading', 'subheading', 'profileHandle', 'themeMode'],
            'StatsCounter' => ['stats', 'themeMode'],
            'StatsSection' => ['heading', 'subheading', 'stats', 'layout', 'imageUrl', 'themeMode'],

            // Sections
            'AccordionFeatures' => ['heading', 'subheading', 'features', 'defaultOpen', 'themeMode'],
            'FeatureTabs' => ['heading', 'subheading', 'tabs', 'tabPosition', 'themeMode'],
            'ProcessSteps' => ['heading', 'subheading', 'label', 'steps', 'themeMode'],
            'ScrollShowcase' => ['heading', 'subheading', 'items', 'backgroundColors', 'maxWidth', 'imageHeight', 'imageRadius', 'themeMode'],
            'BenefitsSection' => ['heading', 'subheading', 'benefits', 'themeMode'],
            'GettingStartedSteps' => ['heading', 'subheading', 'steps', 'themeMode'],
            'ComparisonCards' => ['heading', 'subheading', 'cards', 'themeMode'],
            'PricingPlans' => ['heading', 'subheading', 'plans', 'themeMode'],
            'AgentExamples' => ['heading', 'subheading', 'examples', 'themeMode'],
            'AgentCompatibilityStrip' => ['heading', 'items', 'themeMode'],
            'CommunityCTA' => ['heading', 'subheading', 'primaryButtonText', 'primaryButtonUrl', 'secondaryButtonText', 'secondaryButtonUrl', 'themeMode'],

            // Navigation & Layout
            'IrisNavigation' => ['logo', 'links', 'ctaButton', 'themeMode'],
            'Section' => ['layout', 'columns', 'gap', 'padding', 'maxWidth', 'stackOnMobile', 'backgroundColor', 'themeMode'],
            'FilterTabBar' => ['tabs', 'themeMode'],

            // Editorial & Content
            'EditorialSection' => ['label', 'heading', 'body', 'stepNumber', 'borderAccent', 'borderWidth', 'linkText', 'linkUrl', 'linkAuthor', 'themeMode', 'backgroundColor', 'accentColor', 'maxWidth', 'headingSize', 'headingItalic', 'textAlign', 'paddingY', 'subItems', 'subItemsColumns', 'pipelineBar', 'quoteText', 'quoteSize', 'showTopRule', 'triggerItems', 'iconBadges', 'backgroundEffect'],
            'EditorialComparison' => ['columns', 'rows', 'themeMode', 'accentColor', 'backgroundColor', 'maxWidth', 'paddingY', 'title', 'label'],
            'SplitContent' => ['content', 'mediaUrl', 'mediaAlt', 'mediaPosition', 'title', 'markdown', 'verticalAlign', 'maxWidth', 'padding', 'rounded', 'themeMode'],
            'FAQAccordion' => ['title', 'subtitle', 'items', 'themeMode', 'accentColor', 'backgroundColor', 'maxWidth', 'paddingY'],
            'CodeShowcase' => ['title', 'subtitle', 'labelText', 'languages', 'themeMode'],

            // Media
            'ImageBlock' => ['src', 'alt', 'title', 'caption', 'aspectRatio', 'objectFit', 'maxWidth', 'alignment', 'rounded', 'shadow', 'themeMode'],
            'ImageGallery' => ['images', 'columns', 'gap', 'rounded', 'aspectRatio', 'title', 'subtitle', 'maxWidth', 'themeMode'],
            'LogoStrip' => ['title', 'subtitle', 'labelText', 'logos', 'logoSize', 'grayscale', 'borderless', 'themeMode'],

            // Charts & Data Visualization
            'ApexChart' => ['title', 'subtitle', 'chartType', 'series', 'categories', 'labels', 'height', 'stacked', 'colors', 'summaryItems', 'showToolbar', 'showGrid', 'showLegend', 'borderRadius', 'columnWidth', 'themeMode'],
            'FeatureComparisonTable' => ['title', 'subtitle', 'platforms', 'features', 'highlightPlatform', 'themeMode'],
            'EarningsTable' => ['title', 'commission', 'paths', 'themeMode'],

            // Cards & Grids
            'ServicesGrid' => ['sectionLabel', 'title', 'titleGradient', 'subtitle', 'services', 'themeMode'],
            'IntegrationsGrid' => ['title', 'subtitle', 'integrations', 'searchable', 'themeMode'],
            'NodeSpecsGrid' => ['title', 'subtitle', 'nodes', 'themeMode'],
            'AppDownloadCard' => ['brandId', 'brandName', 'tagline', 'description', 'logoUrl', 'installUrl', 'themeColor', 'themeMode'],
            'AppDownloadGrid' => ['title', 'subtitle', 'apps', 'columns', 'themeMode'],
            'RoleSelector' => ['title', 'subtitle', 'roles', 'themeMode'],
            'InstallInstructions' => ['title', 'subtitle', 'themeMode'],

            // Case Management
            'CaseCard' => ['title', 'subtitle', 'status', 'statusLabel', 'metrics', 'tags', 'progress', 'linkUrl', 'themeMode'],
            'AllCasesGrid' => ['title', 'subtitle', 'cases', 'searchable', 'themeMode'],
            'CasePipelineBoard' => ['title', 'columns', 'themeMode'],
            'CaseEconomics' => ['title', 'lineItems', 'totalLabel', 'totalValue', 'themeMode'],
            'DemandTracker' => ['title', 'caseTitle', 'entries', 'currentStatus', 'themeMode'],

            // Project & Task Management
            'KanbanBoard' => ['title', 'subtitle', 'columns', 'showAddCard', 'themeMode'],
            'ProjectTimeline' => ['title', 'threads', 'activeThread', 'labels', 'filterTabs', 'themeMode'],
            'ProgressTracker' => ['title', 'steps', 'orientation', 'showDates', 'themeMode'],
            'TaskQueueList' => ['title', 'tasks', 'maxItems', 'themeMode'],

            // Communication
            'ChatPanel' => ['title', 'conversations', 'messages', 'activeContact', 'themeMode'],

            // Feed System
            'FeedHero' => ['title', 'subtitle', 'showNewsletterCTA', 'newsletterText', 'newsletterSubtext', 'newsletterButtonText', 'newsletterButtonUrl', 'animatedOrbs', 'gridPattern', 'themeMode'],
            'FeedLayout' => ['feedItems', 'sidebarTags', 'topResearch', 'showSearch', 'searchPlaceholder', 'contentTypes', 'showSidebar', 'showPopularTags', 'showTopResearch', 'infiniteScroll', 'dataSource', 'themeMode'],
            'FeedCard' => ['type', 'title', 'description', 'date', 'imageUrl', 'linkUrl', 'variant', 'tags', 'accessLevel', 'accessBadgeText', 'chartData', 'truncateDescription', 'themeMode'],
            'FeedFilterBar' => ['showSearch', 'searchPlaceholder', 'contentTypes', 'initialSearchQuery', 'initialSelectedTypes', 'themeMode'],
            'FeedSidebar' => ['popularTags', 'topResearch', 'showPopularTags', 'showTopResearch', 'popularTagsTitle', 'topResearchTitle', 'themeMode'],

            // Forms & Input
            'EnrollmentForm' => ['title', 'subtitle', 'submitEndpoint', 'programId', 'programSlug', 'fields', 'buttonText', 'buttonVariant', 'successMessage', 'successRedirectUrl', 'themeMode', 'showPrivacyNote', 'privacyText', 'maxWidth', 'backgroundGradient', 'backgroundColor'],
            'Survey' => ['title', 'description', 'collectEmail', 'collectName', 'showProgressBar', 'showQuestionCount', 'submitButtonText', 'completionTitle', 'completionMessage', 'completionIcon', 'questions'],
            'BookingWizard' => ['title', 'subtitle', 'formSlug', 'bookingSlug', 'contactPhone', 'showInsuranceStep', 'services', 'locations', 'estimateRanges', 'geoapifyApiKey', 'wizardMode', 'stepLabelsOverride', 'successSteps', 'poweredByText', 'themeMode'],
            'BookingCalendar' => ['slug', 'title', 'subtitle', 'accentColor', 'showServicePicker', 'themeMode'],

            // Workflow & Automation
            'WorkflowTrigger' => ['title', 'workflowId', 'webhookSecret', 'apiBaseUrl', 'fields', 'triggerMode', 'buttonText', 'buttonVariant', 'showResults', 'showProgress', 'showArtifacts', 'resultDisplayMode', 'resultTitle', 'subtitle', 'irisApiBaseUrl', 'successMessage', 'loadingMessage', 'themeMode', 'maxWidth', 'backgroundGradient'],
            'WorkspaceStudio' => ['title', 'integrations', 'assets', 'workflowSteps', 'executionLog', 'themeMode'],

            // Dashboard Widgets & Data
            'DataChart' => ['title', 'subtitle', 'chartType', 'data', 'height', 'showLegend', 'color', 'themeMode'],
            'DataTable' => ['title', 'data', 'columns', 'searchable', 'sortable', 'paginated', 'pageSize', 'emptyMessage', 'maxWidth', 'rowClickEvent', 'rowClickKey', 'themeMode'],
            'ActivityFeed' => ['title', 'items', 'maxItems', 'showTimestamps', 'groupByDate', 'themeMode'],
            'QuickActions' => ['title', 'actions', 'columns', 'variant', 'themeMode'],
            'WidgetAreaChartCard' => ['title', 'chartColor', 'showRangeSelector', 'selectedRange', 'dataPoints', 'summaryLeft', 'summaryRight', 'themeMode'],
            'WidgetChecklistCard' => ['title', 'description', 'items', 'themeMode', 'interactive', 'appSlug', 'collection'],
            'WidgetProjectCard' => ['title', 'description', 'status', 'statusColor', 'progress', 'dueDate', 'stats', 'teamAvatars', 'themeMode'],
            'WidgetStatsRow' => ['stats', 'columns', 'themeMode'],
            'WidgetTeamGrid' => ['title', 'columns', 'members', 'themeMode'],
            'WidgetWorkspaceBanner' => ['title', 'subtitle', 'avatarUrl', 'avatarInitials', 'backgroundStyle', 'backgroundImageUrl', 'showDate', 'themeMode'],
        ];
    }

    /**
     * Known prop aliases — props that ComponentRenderer accepts under multiple names.
     * Maps from canonical name to all accepted alternatives.
     */
    private function getPropAliases(): array
    {
        return [
            'SiteNavigation' => [
                'logo.imageUrl' => 'logo.image',  // Both work (imageUrl || image)
            ],
            'Hero' => [
                'properties' => 'props',  // JSON uses "properties", older format uses "props"
            ],
        ];
    }

    // ─── Brand Profile ───────────────────────────────────────────────

    private function brandPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages brand <slug> [--from-lead=ID] [--apply]');
            return Command::FAILURE;
        }

        $fromLead = $input->getOption('from-lead');
        $shouldApply = $input->getOption('apply');
        $isJson = $input->getOption('json');

        // Fetch page
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;
        $pageId = $page['id'] ?? null;
        $jsonContent = $page['json_content'] ?? [];
        $theme = $jsonContent['theme'] ?? [];
        $branding = $theme['branding'] ?? [];
        $components = $jsonContent['components'] ?? [];

        // ─── Mode 1: Show current brand ────────────────────────────
        if (!$fromLead) {
            $io->title("Brand Profile: {$slug}");

            if (empty($branding)) {
                $io->warning('No branding configured. Use --from-lead=<ID> to import brand from a lead.');
                return Command::SUCCESS;
            }

            $rows = [];
            foreach ($branding as $key => $val) {
                $rows[] = [$key, $val];
            }
            $io->table(['Property', 'Value'], $rows);

            // Show components with explicit accentColor that matches or differs from brand
            $primaryColor = $branding['primaryColor'] ?? null;
            if ($primaryColor) {
                $matching = [];
                $different = [];
                $inheriting = [];
                foreach ($components as $idx => $comp) {
                    $props = $comp['properties'] ?? $comp['props'] ?? [];
                    $type = $comp['type'] ?? 'Unknown';
                    $hasColorProp = false;
                    foreach (['accentColor', 'labelColor', 'ctaColor'] as $colorProp) {
                        $val = $props[$colorProp] ?? null;
                        if ($val) {
                            $hasColorProp = true;
                            if (strtolower((string)$val) === strtolower($primaryColor)) {
                                $matching[] = "[{$idx}] {$type}: {$colorProp}={$val} (matches brand — redundant, can remove)";
                            } else {
                                $different[] = "[{$idx}] {$type}: {$colorProp}={$val} (custom override)";
                            }
                        }
                    }
                    if (!$hasColorProp) {
                        $inheriting[] = "[{$idx}] {$type}";
                    }
                }

                $io->newLine();
                if (!empty($inheriting)) {
                    $io->text("<fg=green>Inheriting brand color ({$primaryColor}):</>");
                    foreach ($inheriting as $msg) {
                        $io->writeln("  <fg=green>\xe2\x9c\x93</> {$msg}");
                    }
                }
                if (!empty($matching)) {
                    $io->newLine();
                    $io->text("<fg=yellow>Redundant accentColor (matches brand, can be removed):</>");
                    foreach ($matching as $msg) {
                        $io->writeln("  <fg=yellow>\xe2\x9a\xa0</> {$msg}");
                    }
                }
                if (!empty($different)) {
                    $io->newLine();
                    $io->text("<fg=cyan>Custom overrides (intentional):</>");
                    foreach ($different as $msg) {
                        $io->writeln("  <fg=cyan>i</> {$msg}");
                    }
                }
            }

            return Command::SUCCESS;
        }

        // ─── Mode 2: Extract brand from lead ───────────────────────
        $io->title("Brand Profile: {$slug} (from lead #{$fromLead})");
        $io->text('Fetching lead notes...');

        $leadObj = $iris->leads->get((int) $fromLead);
        $lead = $leadObj->toArray();
        $notes = $lead['notes'] ?? [];
        $companyName = $lead['company'] ?? $lead['name'] ?? $lead['nickname'] ?? null;

        // Search notes for brand guidelines
        $brandNote = null;
        foreach ($notes as $note) {
            $content = $note['content'] ?? '';
            // Look for notes with color hex codes + font mentions
            if (
                (stripos($content, 'brand') !== false || stripos($content, 'color') !== false) &&
                preg_match('/#[0-9A-Fa-f]{6}/', $content)
            ) {
                $brandNote = $content;
                break;
            }
        }

        if (!$brandNote) {
            $io->error("No brand guidelines found in lead #{$fromLead} notes. Add a note with brand colors (hex codes) and font family.");
            return Command::FAILURE;
        }

        // Parse brand from note text
        $extracted = $this->extractBrandFromNote($brandNote);

        if (empty($extracted['primaryColor'])) {
            $io->error("Could not extract a primary/accent color from lead notes. Ensure note contains hex colors like #FF192C.");
            return Command::FAILURE;
        }

        $io->section('Extracted Brand');
        $rows = [];
        foreach ($extracted as $key => $val) {
            if ($val !== null) {
                $rows[] = [$key, $val];
            }
        }
        $io->table(['Property', 'Value'], $rows);

        // ─── Calculate changes ──────────────────────────────────────
        $changes = [];

        // 1. Set theme.branding fields
        foreach ($extracted as $key => $val) {
            if ($val !== null) {
                $current = $branding[$key] ?? null;
                if ($current !== $val) {
                    $changes[] = [
                        'path' => "theme.branding.{$key}",
                        'from' => $current ?? '(unset)',
                        'to' => $val,
                    ];
                }
            }
        }

        // 2. Auto-fix component themes
        $primaryColor = $extracted['primaryColor'];
        foreach ($components as $idx => $comp) {
            $type = $comp['type'] ?? null;
            $props = $comp['properties'] ?? $comp['props'] ?? [];
            $propsKey = isset($comp['properties']) ? 'properties' : 'props';

            // Hero with backgroundImage should be dark
            if ($type === 'Hero' && !empty($props['backgroundImage'])) {
                $compTheme = $props['themeMode'] ?? null;
                if ($compTheme !== 'dark') {
                    $changes[] = [
                        'path' => "components.{$idx}.{$propsKey}.themeMode",
                        'from' => $compTheme ?? '(unset)',
                        'to' => 'dark',
                        'reason' => 'Hero has backgroundImage',
                    ];
                }
            }

            // Remove redundant color props that match brand
            foreach (['accentColor', 'labelColor', 'ctaColor'] as $colorProp) {
                $val = $props[$colorProp] ?? null;
                if ($val && strtolower((string)$val) === strtolower($primaryColor)) {
                    $changes[] = [
                        'path' => "components.{$idx}.{$propsKey}.{$colorProp}",
                        'from' => $val,
                        'to' => '(remove — inherits from brand)',
                        'reason' => 'Matches primaryColor, now inherited via CSS variable',
                    ];
                }
            }
        }

        if (empty($changes)) {
            $io->success('Page brand is already up to date!');
            return Command::SUCCESS;
        }

        // Show planned changes
        $io->section('Planned Changes');
        $changeRows = [];
        foreach ($changes as $c) {
            $changeRows[] = [
                $c['path'],
                $c['from'],
                $c['to'],
                $c['reason'] ?? '',
            ];
        }
        $io->table(['Path', 'Current', 'New', 'Reason'], $changeRows);

        if (!$shouldApply) {
            $io->note('Dry run — no changes applied. Add --apply to write these changes.');
            return Command::SUCCESS;
        }

        // ─── Apply changes ──────────────────────────────────────────
        $io->text('Applying changes...');
        $applied = 0;

        foreach ($changes as $c) {
            $path = $c['path'];
            $to = $c['to'];

            // Handle removals (set to null to remove from JSON)
            if (str_starts_with($to, '(remove')) {
                // For removals, we need to set the field to null
                // The API's updatePath should handle null as "remove key"
                try {
                    $iris->pages->updatePath($pageId, $path, null);
                    $applied++;
                } catch (\Exception $e) {
                    $io->warning("Failed to remove {$path}: " . $e->getMessage());
                }
            } else {
                try {
                    $iris->pages->updatePath($pageId, $path, $to);
                    $applied++;
                } catch (\Exception $e) {
                    $io->warning("Failed to update {$path}: " . $e->getMessage());
                }
            }
        }

        $io->success("Applied {$applied}/" . count($changes) . " changes to {$slug}");
        $io->text('Run <fg=cyan>pages validate ' . $slug . '</> to verify.');

        return Command::SUCCESS;
    }

    /**
     * Extract brand properties from a free-text note containing brand guidelines.
     */
    private function extractBrandFromNote(string $note): array
    {
        $brand = [
            'primaryColor' => null,
            'secondaryColor' => null,
            'fontFamily' => null,
            'buttonBg' => null,
            'buttonText' => null,
            'linkColor' => null,
            'accentColor' => null,
        ];

        // Extract all hex colors from note with their labels
        // Format: "Label: #XXXXXX" or "Label (#XXXXXX)" or "- Label: #XXXXXX"
        $colorMap = [];
        if (preg_match_all('/[-\s]*([A-Za-z\s()]+?)\s*[:=]\s*(#[0-9A-Fa-f]{6})\b/i', $note, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $label = strtolower(trim($m[1]));
                $hex = strtoupper($m[2]);
                $colorMap[$label] = $hex;
            }
        }
        // Also match "Label (description): #hex" format — e.g., "Red (brand accent): #FF192C"
        if (preg_match_all('/(#[0-9A-Fa-f]{6})/i', $note, $allHexes)) {
            // Already captured above
        }

        // Find accent/primary — look for labels containing "accent", "brand accent", "red", "primary"
        // Exclude #FFFFFF, #000000 as they're not accent colors
        $accentColor = null;
        foreach ($colorMap as $label => $hex) {
            if (in_array($hex, ['#FFFFFF', '#000000'])) continue;
            if (preg_match('/accent|brand|primary/i', $label)) {
                $accentColor = $hex;
                break;
            }
        }
        // Fallback: first non-white, non-black, non-gray hex color
        if (!$accentColor) {
            foreach ($colorMap as $label => $hex) {
                if (in_array($hex, ['#FFFFFF', '#000000'])) continue;
                // Skip grays (where R≈G≈B)
                $r = hexdec(substr($hex, 1, 2));
                $g = hexdec(substr($hex, 3, 2));
                $b = hexdec(substr($hex, 5, 2));
                if (max($r, $g, $b) - min($r, $g, $b) > 30) {
                    $accentColor = $hex;
                    break;
                }
            }
        }

        if ($accentColor) {
            $brand['primaryColor'] = $accentColor;
            $brand['accentColor'] = $accentColor;
            $brand['linkColor'] = $accentColor;
            $brand['buttonBg'] = $accentColor;
            $brand['buttonText'] = '#FFFFFF';
        }

        // Extract secondary color section
        // Look for "SECONDARY COLORS" section header, then find first non-gray colorful hex
        if (preg_match('/secondary\s+colors?[^:]*:?\s*\n((?:.*\n)*?)(?:\n[A-Z]|\z)/i', $note, $secSection)) {
            $secBlock = $secSection[1];
            if (preg_match_all('/(#[0-9A-Fa-f]{6})/i', $secBlock, $secHexes)) {
                foreach ($secHexes[1] as $hex) {
                    $hex = strtoupper($hex);
                    // Pick the lightest one for secondary (for backgrounds, etc.)
                    $brand['secondaryColor'] = $hex;
                    break;
                }
            }
        }

        // Extract font family — look for "font:" or "Primary font:" or "font-family:"
        if (preg_match('/(?:primary\s+)?font(?:\s*(?:family)?)\s*:\s*([A-Za-z\s]+?)(?:\s*\(|,|\n)/i', $note, $m)) {
            $brand['fontFamily'] = trim($m[1]);
        }

        // Clean out nulls
        return array_filter($brand, fn($v) => $v !== null);
    }

    // ─── Audit ─────────────────────────────────────────────────────────

    private function auditPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages audit <slug> [--visual] [--from-lead=ID]');
            return Command::FAILURE;
        }

        $isVisual = $input->getOption('visual');
        $fromLead = $input->getOption('from-lead');
        $isJson = $input->getOption('json');

        $io->title("Page Audit: {$slug}");

        // 1. Fetch page
        $response = $iris->pages->getBySlug($slug, true);
        $page = $response['data'] ?? $response;
        $jsonContent = $page['json_content'] ?? [];
        $theme = $jsonContent['theme'] ?? [];
        $branding = $theme['branding'] ?? [];
        $components = $jsonContent['components'] ?? [];
        $primaryColor = $branding['primaryColor'] ?? null;

        // 2. Run validation (same logic as validatePage)
        $validation = $this->runValidationChecks($jsonContent);

        // 3. Run brand check — count redundant color props
        $brandIssues = [];
        if ($primaryColor && !empty($components)) {
            foreach ($components as $idx => $comp) {
                $props = $comp['properties'] ?? $comp['props'] ?? [];
                $type = $comp['type'] ?? 'Unknown';
                foreach (['accentColor', 'labelColor', 'ctaColor'] as $colorProp) {
                    $val = $props[$colorProp] ?? null;
                    if ($val && strtolower((string)$val) === strtolower($primaryColor)) {
                        $brandIssues[] = "[{$idx}] {$type}: {$colorProp}={$val} (redundant — matches brand)";
                    }
                }
            }
        }

        // 4. Compute score (0-100)
        $errorCount = count($validation['errors']);
        $warningCount = count($validation['warnings']);
        $brandIssueCount = count($brandIssues);
        $score = max(0, 100 - ($errorCount * 10) - ($warningCount * 3) - ($brandIssueCount * 2));

        // 5. Display results
        $io->text("<fg=gray>Components: " . count($components) . "</>");
        $io->text("<fg=gray>Brand: " . ($primaryColor ?: 'not set') . "</>");
        $io->text("<fg=gray>Theme: " . ($theme['mode'] ?? 'not set') . "</>");
        $io->newLine();

        // Score badge
        if ($score >= 90) {
            $io->writeln("  <fg=green;options=bold>SCORE: {$score}/100</> — Excellent");
        } elseif ($score >= 70) {
            $io->writeln("  <fg=yellow;options=bold>SCORE: {$score}/100</> — Good (minor issues)");
        } else {
            $io->writeln("  <fg=red;options=bold>SCORE: {$score}/100</> — Needs attention");
        }
        $io->newLine();

        // Passes
        if (!empty($validation['passes'])) {
            $io->text('<fg=green>Passed:</>');
            foreach ($validation['passes'] as $msg) {
                $io->writeln("  <fg=green>\xe2\x9c\x93</> {$msg}");
            }
        }

        // Warnings
        if (!empty($validation['warnings'])) {
            $io->newLine();
            $io->text('<fg=yellow>Warnings:</>');
            foreach ($validation['warnings'] as $msg) {
                $io->writeln("  <fg=yellow>\xe2\x9a\xa0</> {$msg}");
            }
        }

        // Errors
        if (!empty($validation['errors'])) {
            $io->newLine();
            $io->text('<fg=red>Errors:</>');
            foreach ($validation['errors'] as $msg) {
                $io->writeln("  <fg=red>\xe2\x9c\x97</> {$msg}");
            }
        }

        // Brand issues
        if (!empty($brandIssues)) {
            $io->newLine();
            $io->text('<fg=cyan>Redundant brand props (removable):</>');
            foreach ($brandIssues as $msg) {
                $io->writeln("  <fg=cyan>i</> {$msg}");
            }
        }

        // 6. Generate markdown report
        $reportLines = [
            "# Page Audit Report — {$slug}",
            "",
            "**Generated:** " . date('Y-m-d H:i:s'),
            "**Score:** {$score}/100",
            "**Components:** " . count($components),
            "**Brand Color:** " . ($primaryColor ?: 'not set'),
            "**Theme Mode:** " . ($theme['mode'] ?? 'not set'),
            "",
        ];

        if (!empty($validation['errors'])) {
            $reportLines[] = "## Errors ({$errorCount})";
            $reportLines[] = "";
            foreach ($validation['errors'] as $msg) {
                $reportLines[] = "- {$msg}";
            }
            $reportLines[] = "";
        }

        if (!empty($validation['warnings'])) {
            $reportLines[] = "## Warnings ({$warningCount})";
            $reportLines[] = "";
            foreach ($validation['warnings'] as $msg) {
                $reportLines[] = "- {$msg}";
            }
            $reportLines[] = "";
        }

        if (!empty($brandIssues)) {
            $reportLines[] = "## Redundant Brand Props ({$brandIssueCount})";
            $reportLines[] = "";
            foreach ($brandIssues as $msg) {
                $reportLines[] = "- {$msg}";
            }
            $reportLines[] = "";
        }

        $reportLines[] = "## Passed (" . count($validation['passes']) . ")";
        $reportLines[] = "";
        foreach ($validation['passes'] as $msg) {
            $reportLines[] = "- {$msg}";
        }

        $report = implode("\n", $reportLines);

        // 7. Run Playwright visual tests if --visual
        if ($isVisual) {
            $io->newLine();
            $io->section('Visual Tests (Playwright)');

            // Find project root (go up from sdk/php/src/Console/Commands/)
            $projectRoot = realpath(__DIR__ . '/../../../../../..');
            if (!$projectRoot || !is_dir("{$projectRoot}/tests/e2e")) {
                $projectRoot = realpath(__DIR__ . '/../../../../..');
            }

            if (!$projectRoot || !file_exists("{$projectRoot}/playwright.config.ts")) {
                $io->warning("Could not locate playwright.config.ts — skipping visual tests. Run manually:\n  PAGE_SLUG={$slug} npx playwright test client-page-audit --project=local");
            } else {
                $envVars = "PAGE_SLUG=" . escapeshellarg($slug);
                if ($primaryColor) {
                    $envVars .= " PRIMARY_COLOR=" . escapeshellarg($primaryColor);
                }
                if (!empty($theme['mode'])) {
                    $envVars .= " THEME_MODE=" . escapeshellarg($theme['mode']);
                }

                $cmd = "cd " . escapeshellarg($projectRoot) . " && {$envVars} npx playwright test client-page-audit --project=local 2>&1";
                $io->text("<fg=gray>Running: {$cmd}</>");
                $io->newLine();

                $exitCode = 0;
                passthru($cmd, $exitCode);

                if ($exitCode === 0) {
                    $io->success('Visual tests passed');
                    $report .= "\n\n## Visual Tests\n\nAll 6 Playwright tests passed.\n";
                    $report .= "Screenshots: test-results/audit/{$slug}/\n";
                } else {
                    $io->warning("Visual tests had failures (exit code: {$exitCode})");
                    $report .= "\n\n## Visual Tests\n\nSome tests failed (exit code: {$exitCode}). Check test-results/audit/{$slug}/\n";
                }
            }
        }

        // 8. Attach report to lead if --from-lead
        if ($fromLead) {
            $io->newLine();
            $io->section('Attaching to Lead');

            // Write report file
            $dir = $input->getOption('dir') ?: './pages';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $reportPath = "{$dir}/{$slug}-audit-report.md";
            file_put_contents($reportPath, $report);
            $io->text("Report saved: {$reportPath}");

            try {
                // Upload as file deliverable
                $deliverable = $iris->leads->deliverables((int)$fromLead)->uploadFile(
                    realpath($reportPath),
                    ['title' => "Page Audit Report — {$slug} (Score: {$score}/100)"]
                );
                $io->success("Audit report attached as deliverable to lead #{$fromLead}");

                // Also create a link deliverable to the page itself
                $currentEnv = getenv('IRIS_ENV') ?: 'production';
                $pageUrl = $currentEnv === 'local'
                    ? "http://local.iris.freelabel.net:9300/p/{$slug}"
                    : "https://heyiris.io/p/{$slug}";

                $iris->leads->deliverables((int)$fromLead)->create([
                    'type' => 'link',
                    'title' => "Live Page — {$slug}",
                    'external_url' => $pageUrl,
                ]);
                $io->text("Page link deliverable created: {$pageUrl}");
            } catch (\Exception $e) {
                $io->warning("Could not attach to lead: " . $e->getMessage());
            }
        }

        // JSON output
        if ($isJson) {
            $io->writeln(json_encode([
                'slug' => $slug,
                'score' => $score,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'passes' => $validation['passes'],
                'brandIssues' => $brandIssues,
                'componentCount' => count($components),
                'primaryColor' => $primaryColor,
                'themeMode' => $theme['mode'] ?? null,
            ], JSON_PRETTY_PRINT));
        }

        $io->newLine();
        return $errorCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Run validation checks and return structured results (no output).
     * Shared by both validatePage and auditPage.
     */
    private function runValidationChecks(array $jsonContent): array
    {
        $schema = $this->getComponentSchema();
        $theme = $jsonContent['theme'] ?? [];
        $components = $jsonContent['components'] ?? [];

        $errors = [];
        $warnings = [];
        $passes = [];

        // ─── Theme validation ────────────────────────────────────────
        $themeMode = $theme['mode'] ?? null;
        if ($themeMode) {
            $passes[] = "Theme mode set: {$themeMode}";
        } else {
            $warnings[] = 'No theme.mode set — components will use their own themeMode or default to dark';
        }

        $branding = $theme['branding'] ?? [];
        if (!empty($branding['primaryColor'])) {
            $passes[] = "Brand color: {$branding['primaryColor']}";
        } else {
            $warnings[] = 'No theme.branding.primaryColor — components will use default indigo (#4f46e5)';
        }
        if (!empty($branding['fontFamily'])) {
            $passes[] = "Font family: {$branding['fontFamily']}";
        } else {
            $warnings[] = 'No theme.branding.fontFamily — page will use system fonts';
        }

        // ─── Redundant accentColor props ────────────────────────────
        $primaryColor = $branding['primaryColor'] ?? null;
        if ($primaryColor && !empty($components)) {
            $redundant = [];
            foreach ($components as $idx => $comp) {
                $props = $comp['properties'] ?? $comp['props'] ?? [];
                $type = $comp['type'] ?? 'Unknown';
                foreach (['accentColor', 'labelColor', 'ctaColor'] as $colorProp) {
                    $val = $props[$colorProp] ?? null;
                    if ($val && strtolower((string)$val) === strtolower($primaryColor)) {
                        $redundant[] = "[{$idx}] {$type}: {$colorProp}={$val}";
                    }
                }
            }
            if (!empty($redundant)) {
                $warnings[] = count($redundant) . " component(s) have redundant color props matching brand ({$primaryColor}) — these now inherit via CSS variable and can be removed:\n    " . implode("\n    ", $redundant) . "\n    Run: pages brand <slug> --from-lead=<ID> --apply (or remove manually)";
            }
        }

        // ─── Theme mode consistency ──────────────────────────────────
        if ($themeMode && !empty($components)) {
            $mismatched = [];
            foreach ($components as $idx => $comp) {
                $props = $comp['properties'] ?? $comp['props'] ?? [];
                $compTheme = $props['themeMode'] ?? null;
                if ($compTheme && $compTheme !== $themeMode) {
                    $mismatched[] = "[{$idx}] {$comp['type']}: themeMode={$compTheme}";
                }
            }
            if (!empty($mismatched)) {
                $warnings[] = "Mixed theme modes — page is \"{$themeMode}\" but " . count($mismatched) . " component(s) override:\n    " . implode("\n    ", $mismatched) . "\n    (This is fine if those components have dark backgrounds)";
            } else {
                $passes[] = 'Theme modes consistent across all components';
            }
        }

        // ─── Contrast & visibility checks ────────────────────────────
        foreach ($components as $idx => $comp) {
            $type = $comp['type'] ?? null;
            $props = $comp['properties'] ?? $comp['props'] ?? [];
            $compTheme = $props['themeMode'] ?? $themeMode ?? 'dark';

            if ($type === 'Hero' && !empty($props['backgroundImage'])) {
                if ($compTheme === 'light') {
                    $errors[] = "[{$idx}] Hero: has backgroundImage but themeMode is \"light\" — dark text will be invisible on dark photo. Set themeMode: \"dark\"";
                } else {
                    $passes[] = "[{$idx}] Hero: backgroundImage + dark theme — text visible";
                }
                $hasOverlay = !empty($props['overlayGradient']) || (isset($props['overlayOpacity']) && $props['overlayOpacity'] > 0);
                if (!$hasOverlay) {
                    $warnings[] = "[{$idx}] Hero: backgroundImage without overlay — text may be hard to read. Add overlayOpacity or overlayGradient";
                }
            }

            if ($type === 'Hero' && isset($props['overlayOpacity']) && $props['overlayOpacity'] > 0.5 && $compTheme === 'light') {
                $errors[] = "[{$idx}] Hero: overlayOpacity={$props['overlayOpacity']} (dark overlay) but themeMode=\"light\" — dark text on dark overlay. Set themeMode: \"dark\"";
            }

            if ($type === 'SiteNavigation' && !empty($props['transparent'])) {
                $navTheme = $props['themeMode'] ?? null;
                if (!$navTheme && $themeMode === 'light') {
                    $warnings[] = "[{$idx}] SiteNavigation: transparent=true on light page without explicit themeMode";
                } elseif ($navTheme === 'light' && $themeMode === 'light') {
                    $warnings[] = "[{$idx}] SiteNavigation: transparent=true with themeMode=\"light\" — if Hero has dark background, nav links invisible";
                } else {
                    $passes[] = "[{$idx}] SiteNavigation: transparent nav theme configured correctly";
                }
            }
        }

        // ─── Component validation ────────────────────────────────────
        if (empty($components)) {
            $warnings[] = 'Page has no components';
        }

        foreach ($components as $idx => $comp) {
            $type = $comp['type'] ?? null;
            $props = $comp['properties'] ?? $comp['props'] ?? [];

            if (!$type) {
                $errors[] = "[{$idx}] Component missing 'type' field";
                continue;
            }

            if (!isset($schema[$type])) {
                $errors[] = "[{$idx}] Unknown component type: \"{$type}\" — will show 'not implemented' fallback";
                continue;
            }

            $passes[] = "[{$idx}] {$type} — type recognized";

            if (!isset($comp['properties']) && !isset($comp['props'])) {
                $errors[] = "[{$idx}] {$type}: missing both \"properties\" and \"props\" keys";
            }

            if (empty($comp['id'])) {
                $warnings[] = "[{$idx}] {$type}: missing 'id' field";
            }

            // Type-specific checks
            if ($type === 'SiteNavigation' && isset($props['logo'])) {
                $logo = $props['logo'];
                if (is_array($logo) && empty($logo['imageUrl']) && empty($logo['image'])) {
                    $warnings[] = "[{$idx}] SiteNavigation: logo object has no imageUrl or image";
                }
            }

            if ($type === 'Hero') {
                if (empty($props['backgroundImage']) && empty($props['backgroundGradient'])) {
                    $warnings[] = "[{$idx}] Hero: no backgroundImage or backgroundGradient";
                }
            }

            if ($type === 'JumbotronHero' && isset($props['logo']) && is_array($props['logo'])) {
                $errors[] = "[{$idx}] JumbotronHero: logo should be a string URL, not an object";
            }

            // Check array props
            $arrayProps = [
                'FeatureTabs' => 'tabs', 'ProcessSteps' => 'steps', 'AccordionFeatures' => 'features',
                'FeatureGrid' => 'features', 'FeatureCardsGrid' => 'features', 'FeatureIconsGrid' => 'features',
                'SkillsGrid' => 'skills', 'ScrollShowcase' => 'items', 'StatsSection' => 'stats',
                'StatsCounter' => 'stats', 'PricingPlans' => 'plans', 'BenefitsSection' => 'benefits',
                'GettingStartedSteps' => 'steps', 'ComparisonCards' => 'cards', 'PortfolioGrid' => 'items',
                'AgentExamples' => 'examples', 'SiteNavigation' => 'links', 'SiteFooter' => 'columns',
            ];

            if (isset($arrayProps[$type])) {
                $arrayKey = $arrayProps[$type];
                if (empty($props[$arrayKey])) {
                    $warnings[] = "[{$idx}] {$type}: \"{$arrayKey}\" is empty or missing";
                } elseif (!is_array($props[$arrayKey])) {
                    $errors[] = "[{$idx}] {$type}: \"{$arrayKey}\" should be an array";
                }
            }

            // Unknown props
            $expectedProps = $schema[$type];
            $actualProps = array_keys($props);
            $unknown = array_diff($actualProps, $expectedProps);
            $harmless = ['themeMode', 'backgroundColor', 'textColor', 'accentColor', 'id'];
            $unknown = array_diff($unknown, $harmless);
            if (!empty($unknown)) {
                $warnings[] = "[{$idx}] {$type}: unrecognized props: " . implode(', ', $unknown);
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'passes' => $passes];
    }

    private function validatePage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages validate <slug>');
            return Command::FAILURE;
        }

        $isJson = $input->getOption('json');

        // Try local file first, fall back to API
        $dir = $input->getOption('dir');
        $filePath = rtrim($dir, '/') . "/{$slug}.json";
        $source = 'api';

        if (file_exists($filePath)) {
            $localJson = json_decode(file_get_contents($filePath), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error("Invalid JSON in {$filePath}: " . json_last_error_msg());
                return Command::FAILURE;
            }
            $jsonContent = $localJson['json_content'] ?? $localJson;
            $source = $filePath;
        } else {
            $response = $iris->pages->getBySlug($slug, true);
            $page = $response['data'] ?? $response;
            $jsonContent = $page['json_content'] ?? [];
        }

        $components = $jsonContent['components'] ?? [];

        // Run shared validation logic
        $result = $this->runValidationChecks($jsonContent);
        $errors = $result['errors'];
        $warnings = $result['warnings'];
        $passes = $result['passes'];

        // ─── Output ──────────────────────────────────────────────────

        if ($isJson) {
            $io->writeln(json_encode([
                'slug' => $slug,
                'source' => $source,
                'errors' => $errors,
                'warnings' => $warnings,
                'passes' => $passes,
                'score' => count($passes) . '/' . (count($passes) + count($errors)),
            ], JSON_PRETTY_PRINT));
            return empty($errors) ? Command::SUCCESS : Command::FAILURE;
        }

        $io->title("Page Validate: {$slug}");
        $io->text("<fg=gray>Source: {$source}</>");
        $io->text("<fg=gray>Components: " . count($components) . "</>");
        $io->newLine();

        // Passes
        if (!empty($passes)) {
            foreach ($passes as $msg) {
                $io->writeln("  <fg=green>\xe2\x9c\x93</> {$msg}");
            }
        }

        // Warnings
        if (!empty($warnings)) {
            $io->newLine();
            foreach ($warnings as $msg) {
                $io->writeln("  <fg=yellow>\xe2\x9a\xa0</> {$msg}");
            }
        }

        // Errors
        if (!empty($errors)) {
            $io->newLine();
            foreach ($errors as $msg) {
                $io->writeln("  <fg=red>\xe2\x9c\x97</> {$msg}");
            }
        }

        $io->newLine();
        $total = count($passes) + count($errors);
        $score = $total > 0 ? count($passes) . '/' . $total : '0/0';

        if (empty($errors) && empty($warnings)) {
            $io->success("All checks passed ({$score})");
        } elseif (empty($errors)) {
            $io->success("Score: {$score} — " . count($warnings) . " warning(s)");
        } else {
            $io->error("Score: {$score} — " . count($errors) . " error(s), " . count($warnings) . " warning(s)");
        }

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }

    // ─── Genesis Integration Commands ───────────────────────────────

    private function aiEditPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages ai-edit <slug> --instructions="..."');
            return Command::FAILURE;
        }

        $instructions = $input->getOption('instructions');
        if (!$instructions) {
            $instructions = $io->ask('What changes should the AI make?');
        }
        if (!$instructions) {
            $io->error('--instructions is required.');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $io->text("AI editing <info>{$slug}</info>...");
        $result = $iris->pages->aiEdit($page['id'], $instructions);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $io->success($result['message'] ?? 'AI edits applied');

        if (!empty($data['changes'])) {
            $io->section('Changes Applied');
            foreach ($data['changes'] as $change) {
                $io->writeln("  - {$change}");
            }
        }

        $io->note("View result: ./bin/iris pages view {$slug}");
        return Command::SUCCESS;
    }

    private function analyticsPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        $pageId = $input->getOption('page-id');

        if (!$slug && !$pageId) {
            $io->error('Slug or --page-id is required. Usage: pages analytics <slug>');
            return Command::FAILURE;
        }

        if (!$pageId) {
            $response = $iris->pages->getBySlug($slug, false);
            $page = $response['data'] ?? $response;
            $pageId = $page['id'];
        }

        $result = $iris->pages->analytics((int) $pageId);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $io->title('Analytics: ' . ($slug ?? "Page #{$pageId}"));

        $definitions = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            }
            $label = ucwords(str_replace('_', ' ', $key));
            $definitions[] = [$label => (string) ($value ?? '-')];
        }

        if (!empty($definitions)) {
            $io->definitionList(...$definitions);
        } else {
            $io->info('No analytics data available yet.');
        }

        return Command::SUCCESS;
    }

    private function submissionsPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages submissions <slug>');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;
        $format = $input->getOption('format') ?? 'json';

        if ($format === 'csv') {
            $result = $iris->pages->submissions($page['id'], 'export', 'csv');
            $data = $result['data'] ?? $result;
            $csv = $data['csv'] ?? '';
            if ($csv) {
                $io->writeln($csv);
            } else {
                $io->info('No submissions to export.');
            }
            return Command::SUCCESS;
        }

        $result = $iris->pages->submissions($page['id']);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $submissions = $data['submissions'] ?? [];
        $count = $data['count'] ?? count($submissions);

        $io->title("Submissions: {$slug}");

        if (empty($submissions)) {
            $io->info('No submissions yet.');
            return Command::SUCCESS;
        }

        // Build table from submission data
        $rows = [];
        $headers = null;
        foreach ($submissions as $sub) {
            $rowData = $sub['data'] ?? $sub;
            if ($headers === null) {
                $headers = array_map(fn($k) => ucwords(str_replace('_', ' ', $k)), array_keys($rowData));
            }
            $rows[] = array_map(fn($v) => is_array($v) ? json_encode($v) : (string) ($v ?? '-'), array_values($rowData));
        }

        $io->table($headers ?? ['Data'], $rows);
        $io->text("{$count} submission(s)");

        return Command::SUCCESS;
    }

    private function checkoutPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages checkout <slug> --package-id=X --buyer-email=Y');
            return Command::FAILURE;
        }

        $packageId = $input->getOption('package-id');
        $buyerEmail = $input->getOption('buyer-email');

        if (!$packageId) {
            $packageId = $io->ask('Package ID');
        }
        if (!$buyerEmail) {
            $buyerEmail = $io->ask('Buyer email');
        }
        if (!$packageId || !$buyerEmail) {
            $io->error('--package-id and --buyer-email are required.');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        $result = $iris->pages->createCheckoutLink($page['id'], $packageId, $buyerEmail);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $checkoutUrl = $data['checkout_url'] ?? null;

        if ($checkoutUrl) {
            $io->success('Checkout link created');
            $io->writeln($checkoutUrl);
        } else {
            $io->warning('No checkout URL returned.');
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT));
        }

        return Command::SUCCESS;
    }

    private function monetizePage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        if (!$slug) {
            $io->error('Slug is required. Usage: pages monetize <slug> --package-name="Pro" --price=29');
            return Command::FAILURE;
        }

        $response = $iris->pages->getBySlug($slug, false);
        $page = $response['data'] ?? $response;

        // Build package from options or interactive
        $packageName = $input->getOption('package-name');
        $price = $input->getOption('price');

        if (!$packageName) {
            $packageName = $io->ask('Package name', 'Basic');
        }
        if (!$price) {
            $price = $io->ask('Price', '0');
        }

        $billingType = $input->getOption('billing-type') ?? 'one_time';
        $features = [];
        if ($featuresStr = $input->getOption('features')) {
            $features = array_map('trim', explode(',', $featuresStr));
        }

        $packages = [[
            'name' => $packageName,
            'price' => (float) $price,
            'billingType' => $billingType,
            'features' => $features,
        ]];

        $result = $iris->pages->setupMonetization($page['id'], $packages);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $io->success($result['message'] ?? "Pricing added to {$slug}");
        $io->definitionList(
            ['Package' => $packageName],
            ['Price' => '$' . number_format((float) $price, 2)],
            ['Billing' => $billingType],
            ['Features' => !empty($features) ? implode(', ', $features) : '-'],
            ['URL' => $data['url'] ?? $this->getPublicUrl($slug)]
        );

        return Command::SUCCESS;
    }

    private function composeNewPage(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $sourceType = $input->getOption('source-type');
        $source = $input->getOption('source');

        if (!$sourceType) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'What source should the AI use?',
                ['topic', 'url', 'video', 'content'],
                0
            );
            $sourceType = $helper->ask($input, $io, $question);
        }

        if (!$source) {
            $prompts = [
                'topic' => 'Describe the page topic',
                'url' => 'Enter the website URL',
                'video' => 'Enter the video URL',
                'content' => 'Paste the content',
            ];
            $source = $io->ask($prompts[$sourceType] ?? 'Enter source');
        }
        if (!$source) {
            $io->error('--source is required.');
            return Command::FAILURE;
        }

        $options = [];
        if ($style = $input->getOption('style')) {
            $options['style'] = $style;
        }
        if ($themeMode = $input->getOption('theme-mode')) {
            $options['theme_mode'] = $themeMode;
        }
        if ($input->getOption('include-form')) {
            $options['include_form'] = true;
        }
        if ($title = $input->getOption('title')) {
            $options['title'] = $title;
        }

        $io->text("AI composing page from <info>{$sourceType}</info>...");
        $result = $iris->pages->composePage($sourceType, $source, $options);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $io->success($result['message'] ?? 'Page composed');

        $pageUrl = $data['page_url'] ?? $data['url'] ?? null;
        $pageId = $data['page_id'] ?? $data['id'] ?? null;
        $pageSlug = $data['slug'] ?? null;

        $definitions = [];
        if ($pageId) {
            $definitions[] = ['Page ID' => $pageId];
        }
        if ($pageSlug) {
            $definitions[] = ['Slug' => $pageSlug];
        }
        if ($pageUrl) {
            $definitions[] = ['URL' => $pageUrl];
        } elseif ($pageSlug) {
            $definitions[] = ['URL' => $this->getPublicUrl($pageSlug)];
        }
        if (!empty($definitions)) {
            $io->definitionList(...$definitions);
        }

        return Command::SUCCESS;
    }

    private function thumbnailPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        $pageId = $input->getOption('page-id');

        if (!$slug && !$pageId) {
            $io->error('Slug or --page-id is required. Usage: pages thumbnail <slug>');
            return Command::FAILURE;
        }

        if (!$pageId) {
            $response = $iris->pages->getBySlug($slug, false);
            $page = $response['data'] ?? $response;
            $pageId = $page['id'];
        }

        $io->text("Generating thumbnail for <info>" . ($slug ?? "Page #{$pageId}") . "</info>...");
        $result = $iris->pages->generateThumbnail((int) $pageId);

        if ($input->getOption('json')) {
            $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $data = $result['data'] ?? $result;
        $thumbnailUrl = $data['thumbnail_url'] ?? $data['url'] ?? null;

        $io->success($result['message'] ?? 'Thumbnail generation started');
        if ($thumbnailUrl) {
            $io->writeln("Thumbnail: {$thumbnailUrl}");
        }

        return Command::SUCCESS;
    }

    private function domainsPage(IRIS $iris, SymfonyStyle $io, InputInterface $input, ?string $slug): int
    {
        // slug doubles as sub-action for domains: list, map, verify, remove
        $subAction = $slug ?? 'list';

        switch ($subAction) {
            case 'list':
                $result = $iris->pages->domains('list');

                if ($input->getOption('json')) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
                    return Command::SUCCESS;
                }

                $data = $result['data'] ?? $result;
                $mappings = is_array($data) ? $data : [];

                $io->title('Custom Domain Mappings');

                if (empty($mappings)) {
                    $io->info('No domain mappings found.');
                    return Command::SUCCESS;
                }

                $rows = [];
                foreach ($mappings as $m) {
                    $rows[] = [
                        $m['id'] ?? '-',
                        $m['domain'] ?? '-',
                        $m['page_id'] ?? $m['site_id'] ?? '-',
                        $m['dns_status'] ?? $m['status'] ?? '-',
                    ];
                }
                $io->table(['ID', 'Domain', 'Page/Site ID', 'DNS Status'], $rows);
                return Command::SUCCESS;

            case 'map':
                $domain = $input->getOption('domain');
                $pageId = $input->getOption('page-id');

                if (!$domain) {
                    $domain = $io->ask('Custom domain');
                }
                if (!$domain) {
                    $io->error('--domain is required.');
                    return Command::FAILURE;
                }

                $params = ['domain' => $domain];
                if ($pageId) {
                    $params['page_id'] = (int) $pageId;
                }

                $result = $iris->pages->domains('map', $params);

                if ($input->getOption('json')) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
                    return Command::SUCCESS;
                }

                $io->success($result['message'] ?? "Domain '{$domain}' mapped");
                $io->note([
                    "Add a CNAME record pointing {$domain} to your origin server.",
                    "Then verify: ./bin/iris pages domains verify --mapping-id=<id>",
                ]);
                return Command::SUCCESS;

            case 'verify':
                $mappingId = $input->getOption('mapping-id');
                if (!$mappingId) {
                    $mappingId = $io->ask('Mapping ID');
                }
                if (!$mappingId) {
                    $io->error('--mapping-id is required.');
                    return Command::FAILURE;
                }

                $result = $iris->pages->domains('verify', ['mapping_id' => (int) $mappingId]);

                if ($input->getOption('json')) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
                    return Command::SUCCESS;
                }

                $io->success($result['message'] ?? 'DNS verification initiated');
                return Command::SUCCESS;

            case 'remove':
                $mappingId = $input->getOption('mapping-id');
                if (!$mappingId) {
                    $mappingId = $io->ask('Mapping ID');
                }
                if (!$mappingId) {
                    $io->error('--mapping-id is required.');
                    return Command::FAILURE;
                }

                if (!$io->confirm("Remove domain mapping #{$mappingId}?", false)) {
                    return Command::SUCCESS;
                }

                $result = $iris->pages->domains('remove', ['mapping_id' => (int) $mappingId]);

                if ($input->getOption('json')) {
                    $io->writeln(json_encode($result, JSON_PRETTY_PRINT));
                    return Command::SUCCESS;
                }

                $io->success($result['message'] ?? "Domain mapping #{$mappingId} removed");
                return Command::SUCCESS;

            default:
                $io->error("Unknown domains action: {$subAction}");
                $io->text('Available: list, map, verify, remove');
                return Command::FAILURE;
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────

    /**
     * Parse a value string, auto-detecting JSON objects/arrays.
     */
    private function parseValue(string $value): mixed
    {
        // Try JSON decode first (for objects, arrays, booleans, numbers)
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && (is_array($decoded) || is_bool($decoded))) {
            return $decoded;
        }

        // Check for numeric
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        // Check for boolean strings
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        if (strtolower($value) === 'null') return null;

        // Return as string
        return $value;
    }

    /**
     * Get a nested value from an array using dot notation.
     */
    private function getNestedValue(array $array, string $path): mixed
    {
        // Normalize bracket notation: components[0] → components.0
        $path = preg_replace('/\[(\d+)\]/', '.$1', $path);
        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return null;
            }
        }

        return $current;
    }
}
