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
 * Demo showcase command — creates real data to demonstrate SDK capabilities.
 *
 * Usage:
 *   iris demo:showcase --type=service-provider
 *   iris demo:showcase --type=creator
 *   iris demo:showcase --type=artist
 *   iris demo:showcase --list
 */
class DemoShowcaseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('demo:showcase')
            ->setDescription('Create a demo showcase with profile, services, content, and playlists')
            ->setHelp(<<<HELP
Demonstrates the full IRIS SDK by creating real profiles, services,
articles, videos, playlists, and BloqItems in a single command.

<info>Available Presets:</info>
  service-provider   Profile + 4 services + article (detailing business)
  creator            Bloq + list + text/markdown/spreadsheet/mixed items
  artist             Profile + video + article + playlist

<info>Examples:</info>
  iris demo:showcase --type=service-provider --name="B&C Detailing"
  iris demo:showcase --type=creator --name="My Knowledge Base"
  iris demo:showcase --type=artist --name="DJ Nova"
  iris demo:showcase --list

<info>Environment Variables:</info>
  IRIS_API_KEY    Your API key
  IRIS_USER_ID    Your user ID
HELP
            )
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Preset type: service-provider, creator, artist')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name for the profile/bloq')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List available presets')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('list')) {
            return $this->listPresets($io);
        }

        $type = $input->getOption('type');
        if (!$type) {
            $io->error('Please specify a preset type with --type. Use --list to see options.');
            return Command::FAILURE;
        }

        if (!in_array($type, ['service-provider', 'creator', 'artist'])) {
            $io->error("Unknown preset type: {$type}. Use --list to see available presets.");
            return Command::FAILURE;
        }

        // Load credentials
        $store = new CredentialStore();
        $apiKey = $input->getOption('api-key') ?: getenv('IRIS_API_KEY') ?: $store->get('api_key');
        $userId = $input->getOption('user-id') ?: getenv('IRIS_USER_ID') ?: $store->get('user_id');

        if (!$apiKey || !$userId) {
            $io->error('Missing credentials. Set IRIS_API_KEY and IRIS_USER_ID or run: iris setup');
            return Command::FAILURE;
        }

        $iris = new IRIS([
            'api_key' => (string) $apiKey,
            'user_id' => (int) $userId,
        ]);

        $name = $input->getOption('name');

        return match ($type) {
            'service-provider' => $this->runServiceProvider($io, $iris, $name),
            'creator'          => $this->runCreator($io, $iris, $name),
            'artist'           => $this->runArtist($io, $iris, $name),
        };
    }

    private function listPresets(SymfonyStyle $io): int
    {
        $io->title('Demo Showcase Presets');

        $io->table(
            ['Preset', 'Description', 'Creates'],
            [
                [
                    'service-provider',
                    'Local business with services',
                    'Profile, 4 Services, 1 Article',
                ],
                [
                    'creator',
                    'Knowledge base with mixed content',
                    'Bloq, List, Text/Markdown/Spreadsheet/Mixed items',
                ],
                [
                    'artist',
                    'Artist/creator showcase',
                    'Profile, 1 Video, 1 Article, 1 Playlist',
                ],
            ]
        );

        $io->note('Run: iris demo:showcase --type=<preset> --name="Your Name"');

        return Command::SUCCESS;
    }

    // =========================================================================
    // SERVICE PROVIDER PRESET
    // =========================================================================

    private function runServiceProvider(SymfonyStyle $io, IRIS $iris, ?string $name): int
    {
        $name = $name ?: 'B&C Detailing';
        $io->title("Service Provider Demo: {$name}");

        $created = [];

        // Step 1: Create profile
        $io->section('Step 1/3 - Creating profile');
        try {
            $profile = $iris->profiles->create([
                'name'         => $name,
                'bio'          => "Professional mobile detailing service. We come to you with top-tier products and equipment.",
                'city'         => 'Fort Worth',
                'state'        => 'TX',
                'country'      => 'United States',
                'country_code' => 'US',
                'phone'        => '817-555-0199',
                'website_url'  => 'https://example-detailing.com',
                'instagram'    => 'bcdetailing',
            ]);
            $profileId = $profile->id ?? ($profile->toArray()['id'] ?? null);
            $io->text("  Profile created (ID: {$profileId})");
            $created['profile'] = $profileId;
        } catch (\Exception $e) {
            $io->error("Failed to create profile: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Step 2: Create 4 services
        $io->section('Step 2/3 - Creating services');
        $serviceData = [
            [
                'title'              => 'Basic Exterior Wash',
                'description'        => 'Hand wash, dry, and tire shine. Quick turnaround for daily drivers.',
                'price'              => 49.99,
                'delivery_amount'    => 1,
                'delivery_frequency' => 'hours',
                'checklist'          => ['Hand wash', 'Tire shine', 'Window cleaning', 'Air freshener'],
            ],
            [
                'title'              => 'Interior Deep Clean',
                'description'        => 'Full vacuum, dashboard conditioning, leather treatment, and odor removal.',
                'price'              => 129.99,
                'delivery_amount'    => 3,
                'delivery_frequency' => 'hours',
                'checklist'          => ['Full vacuum', 'Dashboard conditioning', 'Leather treatment', 'Odor removal', 'Mat cleaning'],
                'addons'             => [
                    ['title' => 'Pet hair removal', 'price' => 25],
                    ['title' => 'Stain treatment', 'price' => 35],
                ],
            ],
            [
                'title'              => 'Full Detail Package',
                'description'        => 'Complete interior and exterior detail. Clay bar, polish, wax, and full interior treatment.',
                'price'              => 249.99,
                'delivery_amount'    => 1,
                'delivery_frequency' => 'days',
                'checklist'          => ['Clay bar treatment', 'Machine polish', 'Carnauba wax', 'Interior deep clean', 'Engine bay cleaning'],
                'addons'             => [
                    ['title' => 'Ceramic coating upgrade', 'price' => 150],
                    ['title' => 'Headlight restoration', 'price' => 45],
                ],
            ],
            [
                'title'              => 'Paint Correction & Ceramic',
                'description'        => 'Multi-stage paint correction with professional ceramic coating. Lasts 2+ years.',
                'price'              => 599.99,
                'delivery_amount'    => 2,
                'delivery_frequency' => 'days',
                'checklist'          => ['Paint inspection', 'Multi-stage correction', 'IPA wipe-down', 'Ceramic coating application', '24hr cure time'],
            ],
        ];

        foreach ($serviceData as $i => $svc) {
            try {
                $svc['profile_id'] = $profileId;
                $service = $iris->services->create($svc);
                $svcId = $service->id ?? ($service->toArray()['id'] ?? 'unknown');
                $io->text("  [{$svcId}] {$svc['title']} - \${$svc['price']}");
                $created['services'][] = $svcId;
            } catch (\Exception $e) {
                $io->warning("  Failed to create service '{$svc['title']}': {$e->getMessage()}");
            }
        }

        // Step 3: Create article about the business
        $io->section('Step 3/3 - Creating article');
        try {
            $article = $iris->articles->create([
                'profile_id' => $profileId,
                'title'      => "Why Mobile Detailing Beats the Car Wash Every Time",
                'content'    => "<h2>The {$name} Difference</h2>"
                    . "<p>When it comes to keeping your vehicle looking its best, convenience and quality matter. "
                    . "At {$name}, we bring professional-grade detailing directly to your driveway.</p>"
                    . "<h3>What Sets Us Apart</h3>"
                    . "<ul>"
                    . "<li><strong>Convenience</strong> - We come to you. No waiting rooms, no shuttle rides.</li>"
                    . "<li><strong>Premium Products</strong> - We use only professional-grade products that protect your investment.</li>"
                    . "<li><strong>Attention to Detail</strong> - Our trained technicians spend the time your vehicle deserves.</li>"
                    . "<li><strong>Transparent Pricing</strong> - No hidden fees. What you see is what you pay.</li>"
                    . "</ul>"
                    . "<p>Book your first detail today and see why our customers keep coming back.</p>",
                'status'     => 1,
            ]);
            $articleId = $article->id ?? ($article->toArray()['id'] ?? null);
            $io->text("  Article created (ID: {$articleId})");
            $created['article'] = $articleId;
        } catch (\Exception $e) {
            $io->warning("  Failed to create article: {$e->getMessage()}");
        }

        // Summary
        $this->printSummary($io, 'service-provider', $created);

        return Command::SUCCESS;
    }

    // =========================================================================
    // CREATOR PRESET
    // =========================================================================

    private function runCreator(SymfonyStyle $io, IRIS $iris, ?string $name): int
    {
        $name = $name ?: 'Research Notebook';
        $io->title("Creator Demo: {$name}");

        $created = [];

        // Step 1: Create a Bloq
        $io->section('Step 1/4 - Creating Bloq');
        try {
            $bloq = $iris->bloqs->create($name, [
                'description' => 'Demo knowledge base created by IRIS SDK showcase',
                'color'       => '#6366f1',
            ]);
            $bloqId = $bloq->id ?? ($bloq->toArray()['id'] ?? null);
            $io->text("  Bloq created (ID: {$bloqId})");
            $created['bloq'] = $bloqId;
        } catch (\Exception $e) {
            $io->error("Failed to create Bloq: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Step 2: Create a List inside the Bloq
        $io->section('Step 2/4 - Creating list');
        try {
            $list = $iris->bloqs->lists($bloqId)->create([
                'title' => 'SDK Demo Content',
            ]);
            $listId = $list->id ?? ($list->toArray()['id'] ?? null);
            $io->text("  List created (ID: {$listId})");
            $created['list'] = $listId;
        } catch (\Exception $e) {
            $io->error("Failed to create list: {$e->getMessage()}");
            return Command::FAILURE;
        }

        $items = $iris->bloqs->items($listId);

        // Step 3: Add various content types
        $io->section('Step 3/4 - Adding content items');

        // 3a. Text item
        try {
            $textItem = $items->addText(
                'Getting Started with IRIS SDK',
                'The IRIS SDK provides a unified interface for managing profiles, services, articles, videos, and more. '
                . 'This demo showcases the different content types you can store in a BloqItem.'
            );
            $textId = $textItem->id ?? ($textItem->toArray()['id'] ?? null);
            $io->text("  [Text]        Item created (ID: {$textId})");
            $created['items'][] = ['type' => 'text', 'id' => $textId];
        } catch (\Exception $e) {
            $io->warning("  Failed to add text item: {$e->getMessage()}");
        }

        // 3b. Markdown item
        try {
            $mdItem = $items->addMarkdown(
                'API Reference Notes',
                "# IRIS SDK API Reference\n\n"
                . "## Core Resources\n\n"
                . "| Resource | Methods |\n"
                . "|----------|--------|\n"
                . "| Profiles | list, create, get, update, delete |\n"
                . "| Services | list, create, get, update, delete |\n"
                . "| Articles | list, create, get, update, delete, generate |\n"
                . "| Videos   | list, create, get, update, delete, upload |\n\n"
                . "## Quick Example\n\n"
                . "```php\n"
                . "\$iris = new IRIS(['api_key' => 'your-key', 'user_id' => 193]);\n"
                . "\$profile = \$iris->profiles->create(['name' => 'Demo']);\n"
                . "```\n"
            );
            $mdId = $mdItem->id ?? ($mdItem->toArray()['id'] ?? null);
            $io->text("  [Markdown]    Item created (ID: {$mdId})");
            $created['items'][] = ['type' => 'markdown', 'id' => $mdId];
        } catch (\Exception $e) {
            $io->warning("  Failed to add markdown item: {$e->getMessage()}");
        }

        // 3c. Spreadsheet item
        try {
            $sheetItem = $items->addSpreadsheet('Q1 Revenue Report', [
                ['month' => 'January',  'revenue' => 12500, 'clients' => 48, 'growth' => '—'],
                ['month' => 'February', 'revenue' => 14200, 'clients' => 55, 'growth' => '+13.6%'],
                ['month' => 'March',    'revenue' => 18900, 'clients' => 72, 'growth' => '+33.1%'],
            ]);
            $sheetId = $sheetItem->id ?? ($sheetItem->toArray()['id'] ?? null);
            $io->text("  [Spreadsheet] Item created (ID: {$sheetId})");
            $created['items'][] = ['type' => 'spreadsheet', 'id' => $sheetId];
        } catch (\Exception $e) {
            $io->warning("  Failed to add spreadsheet item: {$e->getMessage()}");
        }

        // 3d. Mixed item
        try {
            $mixedItem = $items->addMixed(
                'Client Onboarding Checklist',
                "## New Client Onboarding\n\n"
                . "Follow these steps for every new client:\n\n"
                . "1. Send welcome email with login credentials\n"
                . "2. Schedule kickoff call within 48 hours\n"
                . "3. Create project Bloq with shared access\n"
                . "4. Add client to billing system\n"
                . "5. Assign account manager\n",
                [
                    'category'   => 'operations',
                    'priority'   => 'high',
                    'tags'       => ['onboarding', 'process', 'clients'],
                ]
            );
            $mixedId = $mixedItem->id ?? ($mixedItem->toArray()['id'] ?? null);
            $io->text("  [Mixed]       Item created (ID: {$mixedId})");
            $created['items'][] = ['type' => 'mixed', 'id' => $mixedId];
        } catch (\Exception $e) {
            $io->warning("  Failed to add mixed item: {$e->getMessage()}");
        }

        // Step 4: Verify by listing items
        $io->section('Step 4/4 - Verifying');
        try {
            $allItems = $items->list();
            $io->text("  List contains {$allItems->count()} item(s)");
        } catch (\Exception $e) {
            $io->warning("  Could not verify items: {$e->getMessage()}");
        }

        $this->printSummary($io, 'creator', $created);

        return Command::SUCCESS;
    }

    // =========================================================================
    // ARTIST PRESET
    // =========================================================================

    private function runArtist(SymfonyStyle $io, IRIS $iris, ?string $name): int
    {
        $name = $name ?: 'DJ Nova';
        $io->title("Artist Demo: {$name}");

        $created = [];

        // Step 1: Create profile
        $io->section('Step 1/4 - Creating profile');
        try {
            $profile = $iris->profiles->create([
                'name'         => $name,
                'bio'          => "Producer, DJ, and visual artist. Blending electronic music with cinematic storytelling.",
                'city'         => 'Los Angeles',
                'state'        => 'CA',
                'country'      => 'United States',
                'country_code' => 'US',
                'instagram'    => strtolower(str_replace(' ', '', $name)),
                'spotify'      => strtolower(str_replace(' ', '', $name)),
                'soundcloud'   => strtolower(str_replace(' ', '', $name)),
            ]);
            $profileId = $profile->id ?? ($profile->toArray()['id'] ?? null);
            $io->text("  Profile created (ID: {$profileId})");
            $created['profile'] = $profileId;
        } catch (\Exception $e) {
            $io->error("Failed to create profile: {$e->getMessage()}");
            return Command::FAILURE;
        }

        // Step 2: Create a video
        $io->section('Step 2/4 - Creating video');
        try {
            $video = $iris->videos->create([
                'profile_id'  => $profileId,
                'title'       => "{$name} - Live at Sunset Sessions 2025",
                'description' => "Full live set from Sunset Sessions festival. Featuring new tracks from the upcoming EP.",
                'media_id'    => 'demo_sunset_sessions_2025',
            ]);
            $videoId = $video->id ?? ($video->toArray()['id'] ?? null);
            $io->text("  Video created (ID: {$videoId})");
            $created['video'] = $videoId;
        } catch (\Exception $e) {
            $io->warning("  Failed to create video: {$e->getMessage()}");
        }

        // Step 3: Create an article
        $io->section('Step 3/4 - Creating article');
        try {
            $article = $iris->articles->create([
                'profile_id' => $profileId,
                'title'      => "Behind the Scenes: Making the Sunset Sessions EP",
                'content'    => "<h2>The Creative Process</h2>"
                    . "<p>The new EP started as a collection of field recordings from last summer's festival tour. "
                    . "Each track captures a different moment — the energy of a packed tent, a quiet sunrise "
                    . "after an all-night set, the rumble of bass through concrete.</p>"
                    . "<h3>Production Notes</h3>"
                    . "<p>The entire EP was produced using a hybrid setup: Ableton Live for arrangement, "
                    . "analog synths for texture, and AI-assisted mastering through IRIS.</p>"
                    . "<p>Five tracks, each telling a story. Available everywhere on release day.</p>",
                'status'     => 1,
            ]);
            $articleId = $article->id ?? ($article->toArray()['id'] ?? null);
            $io->text("  Article created (ID: {$articleId})");
            $created['article'] = $articleId;
        } catch (\Exception $e) {
            $io->warning("  Failed to create article: {$e->getMessage()}");
        }

        // Step 4: Create a playlist
        $io->section('Step 4/4 - Creating playlist');
        try {
            $playlist = $iris->profiles->createPlaylist([
                'title'       => "{$name} - Sunset Sessions EP",
                'description' => 'The complete Sunset Sessions EP tracklist.',
                'profile_id'  => $profileId,
            ]);
            $playlistId = $playlist->id ?? ($playlist->toArray()['id'] ?? null);
            $io->text("  Playlist created (ID: {$playlistId})");
            $created['playlist'] = $playlistId;

            // Add the video to the playlist if both were created
            if (isset($created['video']) && $playlistId) {
                try {
                    $iris->profiles->addToPlaylist($playlistId, (int) $created['video'], 'video');
                    $io->text("  Video added to playlist");
                } catch (\Exception $e) {
                    $io->warning("  Could not add video to playlist: {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            $io->warning("  Failed to create playlist: {$e->getMessage()}");
        }

        $this->printSummary($io, 'artist', $created);

        return Command::SUCCESS;
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    private function printSummary(SymfonyStyle $io, string $type, array $created): void
    {
        $io->newLine();
        $io->success("Demo showcase ({$type}) completed!");

        $rows = [];

        if (isset($created['profile'])) {
            $rows[] = ['Profile', (string) $created['profile']];
        }
        if (isset($created['bloq'])) {
            $rows[] = ['Bloq', (string) $created['bloq']];
        }
        if (isset($created['list'])) {
            $rows[] = ['List', (string) $created['list']];
        }
        if (!empty($created['services'])) {
            $rows[] = ['Services', implode(', ', $created['services'])];
        }
        if (isset($created['article'])) {
            $rows[] = ['Article', (string) $created['article']];
        }
        if (isset($created['video'])) {
            $rows[] = ['Video', (string) $created['video']];
        }
        if (isset($created['playlist'])) {
            $rows[] = ['Playlist', (string) $created['playlist']];
        }
        if (!empty($created['items'])) {
            foreach ($created['items'] as $item) {
                $rows[] = ["Item ({$item['type']})", (string) $item['id']];
            }
        }

        if ($rows) {
            $io->table(['Resource', 'ID'], $rows);
        }
    }
}
