<?php

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
 * CLI command for invoking Neuron AI tools.
 *
 * Usage:
 *   ./bin/iris tools                                    # List available tools
 *   ./bin/iris tools recruitment --file=job.pdf         # Generate recruitment queries
 *   ./bin/iris tools recruitment --job-description="..." # From text
 *   ./bin/iris tools candidate-score --data='[...]'     # Score candidates
 */
class ToolsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('tools')
            ->setDescription('Invoke Neuron AI tools (recruitment, candidate scoring, lead enrichment, newsletter, demand packages, YouTube audio, clip cutting, beatbox showcase, beatbox submission)')
            ->setHelp(<<<'HELP'
Usage:
  tools                                              List available tools
  tools recruitment [options]                        Generate recruitment queries
  tools candidate-score [options]                    Score candidates against requirements
  tools lead-enrich [options]                        Enrich a lead with contact info
  tools newsletter-research [options]                Research topic and generate outline options
  tools newsletter-write [options]                   Generate newsletter from selected outline
  tools demand-package [options]                     Generate legal demand packages
  tools youtube-audio [options]                      Download YouTube audio as MP3
  tools clip-cut [options]                           Cut video clip from YouTube video
  tools beatbox-publish [options]                    Publish beat to Beatbox showcase
  tools beatbox-submit [options]                     Submit producer beat to Beatbox

Examples:
  ./bin/iris tools
  ./bin/iris tools recruitment --file=/path/to/job.pdf --platform=linkedin
  ./bin/iris tools recruitment --job-description="Senior Engineer..." --location="Austin, TX"
  ./bin/iris tools candidate-score --data='[{"name":"Jane",...}]' --requirements='{"must_have_skills":[...]}'
  ./bin/iris tools lead-enrich --lead-id=510 --goal=email
  ./bin/iris tools newsletter-research --topic="AI trends 2025" --audience="tech professionals"
  ./bin/iris tools demand-package --case-id="Richard Ramos" --ai-model=gpt-5-nano
  ./bin/iris tools youtube-audio --url="https://www.youtube.com/watch?v=abc123" --agent-id=11
  ./bin/iris tools clip-cut --url="https://www.youtube.com/watch?v=abc123" --start-time="0:10" --duration="90s" --agent-id=11
  ./bin/iris tools clip-cut --url="https://www.youtube.com/watch?v=abc123" --start-time="0:10" --duration="60s" --publish-social --platforms="instagram,tiktok"
  ./bin/iris tools beatbox-publish --beatbox-url="https://www.youtube.com/watch?v=abc123" --beatbox-start="0:10" --beatbox-duration="90s"
  ./bin/iris tools beatbox-submit --producer-name="DJ Fire" --producer-email="dj@example.com" --instagram-handle="@djfire" --beatbox-url="https://youtube.com/watch?v=xyz" --beat-title="Fire Trap Beat" --genre="Trap"
HELP
            )
            ->addArgument('tool', InputArgument::OPTIONAL, 'Tool name: recruitment, candidate-score, lead-enrich, newsletter-research, newsletter-write, article, demand-package, youtube-audio, clip-cut, beatbox-publish, beatbox-submit')
            // Common options
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key (overrides .env)')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID (overrides .env)')
            // Recruitment options
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to PDF/DOCX job description file')
            ->addOption('job-description', 'd', InputOption::VALUE_REQUIRED, 'Job description text')
            ->addOption('platform', 'p', InputOption::VALUE_REQUIRED, 'Target platform: linkedin, github, twitter', 'linkedin')
            ->addOption('location', 'l', InputOption::VALUE_REQUIRED, 'Target location (e.g., "Austin, TX")')
            ->addOption('experience', 'e', InputOption::VALUE_REQUIRED, 'Experience level: entry, mid, senior, lead, executive')
            // Candidate scoring options
            ->addOption('data', null, InputOption::VALUE_REQUIRED, 'Candidate data JSON array')
            ->addOption('requirements', null, InputOption::VALUE_REQUIRED, 'Job requirements JSON object')
            // Lead enrichment options
            ->addOption('lead-id', null, InputOption::VALUE_REQUIRED, 'Lead ID to enrich')
            ->addOption('goal', null, InputOption::VALUE_REQUIRED, 'Enrichment goal: email, phone, website, all')
            // Article generation options
            ->addOption('url', 'u', InputOption::VALUE_REQUIRED, 'YouTube URL or webpage URL')
            ->addOption('topic', 't', InputOption::VALUE_REQUIRED, 'Topic for research-based article')
            ->addOption('content', null, InputOption::VALUE_REQUIRED, 'Inline content for research-notes or draft')
            ->addOption('source-type', 's', InputOption::VALUE_REQUIRED, 'Source type: video, topic, webpage, rss, research, notes, research-notes, draft', 'video')
            ->addOption('length', null, InputOption::VALUE_REQUIRED, 'Article length: short, medium, long', 'medium')
            ->addOption('style', null, InputOption::VALUE_REQUIRED, 'Article style: informative, editorial, newsletter, analysis', 'informative')
            ->addOption('profile-id', null, InputOption::VALUE_REQUIRED, 'Profile ID for publishing')
            ->addOption('edits', null, InputOption::VALUE_REQUIRED, 'Editing instructions for draft mode (e.g., "Make more casual, add examples")')
            ->addOption('draft', null, InputOption::VALUE_NONE, 'Save as draft (unpublished)')
            ->addOption('publish', null, InputOption::VALUE_NONE, 'Publish to Freelabel')
            ->addOption('no-publish', null, InputOption::VALUE_NONE, 'Do not publish (test mode, no save)')
            // Demand package options
            ->addOption('case-id', 'c', InputOption::VALUE_REQUIRED, 'Case ID or patient name (e.g., "Richard Ramos", "CAS12345")')
            ->addOption('ai-model', 'm', InputOption::VALUE_REQUIRED, 'AI model to use: gpt-4o, gpt-5-nano, claude-3-5-sonnet', 'gpt-5-nano')
            ->addOption('upload-to-gcs', null, InputOption::VALUE_NONE, 'Upload to Google Cloud Storage (default: true)')
            ->addOption('use-cache', null, InputOption::VALUE_NONE, 'Use cached results if available')
            // YouTube audio options
            ->addOption('agent-id', 'a', InputOption::VALUE_REQUIRED, 'Agent ID for YouTube audio download', '11')
            ->addOption('output-filename', 'o', InputOption::VALUE_REQUIRED, 'Custom output filename (without .mp3 extension)')
            // Clip cutting options
            ->addOption('start-time', null, InputOption::VALUE_REQUIRED, 'Start timestamp (e.g., "0:10", "1:30", "0:00")')
            ->addOption('duration', null, InputOption::VALUE_REQUIRED, 'Clip duration (e.g., "90s", "60s", "120s")')
            ->addOption('publish-social', null, InputOption::VALUE_NONE, 'Publish clip to social media (Instagram/TikTok/X)')
            ->addOption('platforms', null, InputOption::VALUE_REQUIRED, 'Social platforms: instagram,tiktok,x (comma-separated)', 'instagram,tiktok')
            ->addOption('caption', null, InputOption::VALUE_REQUIRED, 'Custom caption for social media (auto-generated if not provided)')
            // Newsletter options
            ->addOption('audience', null, InputOption::VALUE_REQUIRED, 'Target audience for newsletter')
            ->addOption('tone', null, InputOption::VALUE_REQUIRED, 'Newsletter tone: professional, casual, educational, thought-leadership', 'professional')
            ->addOption('newsletter-length', null, InputOption::VALUE_REQUIRED, 'Newsletter length: brief, standard, detailed', 'standard')
            ->addOption('selected-option', null, InputOption::VALUE_REQUIRED, 'Selected outline option (1, 2, or 3) for newsletter-write')
            ->addOption('outline-json', null, InputOption::VALUE_REQUIRED, 'Outline options JSON from newsletter-research')
            ->addOption('context-json', null, InputOption::VALUE_REQUIRED, 'Context JSON from newsletter-research')
            ->addOption('customization', null, InputOption::VALUE_REQUIRED, 'Customization notes for newsletter')
            ->addOption('recipient-email', null, InputOption::VALUE_REQUIRED, 'Recipient email for newsletter')
            ->addOption('recipient-name', null, InputOption::VALUE_REQUIRED, 'Recipient name for newsletter')
            ->addOption('sender-name', null, InputOption::VALUE_REQUIRED, 'Sender name for newsletter')
            // Multi-modal ingestion options
            ->addOption('videos', null, InputOption::VALUE_REQUIRED, 'YouTube video URLs for transcript extraction (comma or newline separated)')
            ->addOption('links', null, InputOption::VALUE_REQUIRED, 'Web URLs to scrape for content (comma or newline separated)')
            // Beatbox showcase options
            ->addOption('beatbox-url', null, InputOption::VALUE_REQUIRED, 'YouTube URL for Beatbox showcase')
            ->addOption('beatbox-start', null, InputOption::VALUE_REQUIRED, 'Start time for clip (default: 0:10)')
            ->addOption('beatbox-duration', null, InputOption::VALUE_REQUIRED, 'Clip duration (default: 90s)')
            ->addOption('beatbox-caption-prompt', null, InputOption::VALUE_REQUIRED, 'Custom prompt for caption AI')
            ->addOption('beatbox-platforms', null, InputOption::VALUE_REQUIRED, 'Social platforms: instagram,tiktok,x (comma-separated, default: all)')
            ->addOption('beatbox-dry-run', null, InputOption::VALUE_NONE, 'Test mode: only generate caption (for debugging)')
            ->addOption('beatbox-dry-run-download', null, InputOption::VALUE_NONE, 'Dry run + download audio (requires --beatbox-dry-run)')
            // Beatbox submission options
            ->addOption('producer-name', null, InputOption::VALUE_REQUIRED, 'Producer/artist name for submission')
            ->addOption('producer-email', null, InputOption::VALUE_REQUIRED, 'Producer email for submission')
            ->addOption('instagram-handle', null, InputOption::VALUE_REQUIRED, 'Instagram handle for submission')
            ->addOption('beat-title', null, InputOption::VALUE_REQUIRED, 'Beat title for submission')
            ->addOption('genre', null, InputOption::VALUE_REQUIRED, 'Genre/style for submission')
            ->addOption('bpm', null, InputOption::VALUE_REQUIRED, 'BPM for submission (optional)')
            ->addOption('notes', null, InputOption::VALUE_REQUIRED, 'Additional notes for submission (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $toolName = $input->getArgument('tool');

        try {
            // Build config from CLI flags
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int) $userId;
            }

            $iris = new IRIS($configOptions);

            // If no tool specified, list available tools
            if (!$toolName) {
                return $this->listTools($iris, $io, $input);
            }

            // Dispatch to specific tool handler
            return match ($toolName) {
                'recruitment', 'recruit' => $this->runRecruitment($iris, $input, $output, $io),
                'candidate-score', 'score' => $this->runCandidateScore($iris, $input, $output, $io),
                'lead-enrich', 'enrich' => $this->runLeadEnrich($iris, $input, $output, $io),
                'newsletter-research', 'newsletter', 'nl-research' => $this->runNewsletterResearch($iris, $input, $output, $io),
                'newsletter-write', 'nl-write' => $this->runNewsletterWrite($iris, $input, $output, $io),
                'article', 'generate-article' => $this->runArticleGeneration($iris, $input, $output, $io),
                'demand-package', 'demand' => $this->runDemandPackage($iris, $input, $output, $io),
                'youtube-audio', 'yt-audio' => $this->runYouTubeAudio($iris, $input, $output, $io),
                'clip-cut', 'cut-clip', 'clip' => $this->runClipCut($iris, $input, $output, $io),
                'beatbox-publish', 'beatbox' => $this->runBeatboxPublish($iris, $input, $output, $io),
                'beatbox-submit', 'submit' => $this->runBeatboxSubmit($iris, $input, $output, $io),
                default => $this->unknownTool($toolName, $io),
            };
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function listTools(IRIS $iris, SymfonyStyle $io, InputInterface $input): int
    {
        $io->title('Available Neuron AI Tools');

        try {
            $tools = $iris->tools->list();

            if ($input->getOption('json')) {
                $io->writeln(json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Display tools in a formatted list
            if (isset($tools['tools']) && is_array($tools['tools'])) {
                foreach ($tools['tools'] as $tool) {
                    $name = $tool['name'] ?? 'unknown';
                    $description = $tool['description'] ?? '';
                    $io->writeln("<fg=cyan>{$name}</>");
                    if ($description) {
                        $io->writeln("  {$description}");
                    }
                    $io->newLine();
                }
            } else {
                // Fallback to hardcoded list if API doesn't return tools
                $io->listing([
                    '<fg=cyan>recruitment</> - Generate search queries from job descriptions',
                    '<fg=cyan>candidate-score</> - Score candidates against requirements',
                    '<fg=cyan>lead-enrich</> - Enrich leads with contact information',
                    '<fg=cyan>article</> - Generate articles from YouTube videos, topics, or webpages',
                    '<fg=cyan>demand-package</> - Generate legal demand packages from case data',
                    '<fg=cyan>youtube-audio</> - Download YouTube audio as MP3 (320kbps)',
                    '<fg=cyan>clip-cut</> - Cut video clips from YouTube videos',
                ]);
            }

            $io->section('Quick Examples');
            $io->text([
                './bin/iris tools recruitment --file=job.pdf --platform=linkedin',
                './bin/iris tools recruitment --job-description="Senior Engineer at..." --location="Austin, TX"',
                './bin/iris tools candidate-score --data=\'[...]\' --requirements=\'[...]\'',
                './bin/iris tools lead-enrich --lead-id=510 --goal=email',
                './bin/iris tools article --url="https://www.youtube.com/watch?v=abc123" --length=medium',
                './bin/iris tools article --topic="AI trends 2025" --style=analysis',
                './bin/iris tools demand-package --case-id="Richard Ramos" --ai-model=gpt-5-nano',
                './bin/iris tools youtube-audio --url="https://www.youtube.com/watch?v=R2ZsTB09kb4" --agent-id=11',
                './bin/iris tools clip-cut --url="https://www.youtube.com/watch?v=abc123" --start-time="0:10" --duration="90s" --agent-id=11',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            // If API fails, show hardcoded list
            $io->listing([
                '<fg=cyan>recruitment</> - Generate search queries from job descriptions',
                '<fg=cyan>candidate-score</> - Score candidates against requirements',
                '<fg=cyan>lead-enrich</> - Enrich leads with contact information',
                '<fg=cyan>demand-package</> - Generate legal demand packages from case data',
                '<fg=cyan>youtube-audio</> - Download YouTube audio as MP3 (320kbps)',
            ]);
            return Command::SUCCESS;
        }
    }

    private function runRecruitment(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $file = $input->getOption('file');
        $jobDescription = $input->getOption('job-description');
        $platform = $input->getOption('platform') ?: 'linkedin';
        $location = $input->getOption('location');
        $experience = $input->getOption('experience');

        // Validate inputs
        if (!$file && !$jobDescription) {
            $io->error('Please provide either --file or --job-description');
            return Command::FAILURE;
        }

        if ($file && !file_exists($file)) {
            $io->error("File not found: {$file}");
            return Command::FAILURE;
        }

        $io->text('Generating recruitment queries...');
        $io->newLine();

        // Build params
        $params = [
            'platform' => $platform,
        ];

        if ($jobDescription) {
            $params['job_description'] = $jobDescription;
        }
        if ($file) {
            // Read file and send content (SDK will handle via API)
            $params['job_description_file'] = $file;
        }
        if ($location) {
            $params['location'] = $location;
        }
        if ($experience) {
            $params['experience_level'] = $experience;
        }

        $result = $iris->tools->recruitment($params);

        // JSON output
        if ($input->getOption('json')) {
            $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        // Formatted output
        $this->displayRecruitmentResult($result, $output, $io);
        return Command::SUCCESS;
    }

    private function displayRecruitmentResult($result, OutputInterface $output, SymfonyStyle $io): void
    {
        // Title
        if ($result->title) {
            $io->success("Job Title: {$result->title}");
        }

        // Requirements
        if ($result->requirements) {
            $io->section('Extracted Requirements');

            $reqs = $result->requirements;

            if (!empty($reqs['must_have_skills'])) {
                $io->writeln('<fg=green>Must-Have Skills:</>');
                foreach ($reqs['must_have_skills'] as $skill) {
                    $io->writeln("  • {$skill}");
                }
            }

            if (!empty($reqs['nice_to_have_skills'])) {
                $io->newLine();
                $io->writeln('<fg=yellow>Nice-to-Have Skills:</>');
                foreach ($reqs['nice_to_have_skills'] as $skill) {
                    $io->writeln("  • {$skill}");
                }
            }

            if (!empty($reqs['title_keywords'])) {
                $io->newLine();
                $io->writeln('<fg=cyan>Title Keywords:</>');
                $keywords = $reqs['title_keywords'];
                if (is_array($keywords)) {
                    $io->writeln('  ' . implode(', ', $keywords));
                } else {
                    $io->writeln("  {$keywords}");
                }
            }

            if (isset($reqs['experience_years'])) {
                $io->newLine();
                $exp = $reqs['experience_years'];
                if (is_array($exp)) {
                    $min = $exp['min'] ?? null;
                    $max = $exp['max'] ?? null;
                    if ($min && $max) {
                        $io->writeln("Experience: {$min}-{$max} years");
                    } elseif ($min) {
                        $io->writeln("Experience: {$min}+ years");
                    } elseif ($max) {
                        $io->writeln("Experience: Up to {$max} years");
                    }
                } else {
                    $io->writeln("Experience: {$exp} years");
                }
            }
        }

        // Search URLs
        if (!empty($result->searchUrls)) {
            $io->newLine();
            $io->section('Search URLs');
            foreach ($result->searchUrls as $urlData) {
                $label = $urlData['label'] ?? 'Search';
                $url = $urlData['url'] ?? '';
                $io->writeln("<fg=blue>{$label}:</>");
                $io->writeln("  {$url}");
                $io->newLine();
            }
        }

        // Boolean Queries
        if (!empty($result->booleanQueries)) {
            $io->section('Boolean Queries');
            foreach ($result->booleanQueries as $queryData) {
                $label = $queryData['label'] ?? 'Query';
                $query = $queryData['query'] ?? '';
                $io->writeln("<fg=magenta>{$label}:</>");
                $io->writeln("  {$query}");
                $io->newLine();
            }
        }

        // Extraction Script (truncated)
        if ($result->extractionScript) {
            $io->section('Browser Extraction Script');
            $io->writeln('<fg=gray>Copy this JavaScript into browser console on LinkedIn search results:</>');
            $io->newLine();
            $script = $result->extractionScript;
            if (strlen($script) > 500) {
                $io->writeln(substr($script, 0, 500) . '...');
                $io->writeln('<fg=gray>(Script truncated. Use --json for full output)</>');
            } else {
                $io->writeln($script);
            }
        }

        // Instructions
        if ($result->instructions) {
            $io->section('Instructions');
            $io->writeln($result->instructions);
        }
    }

    private function runCandidateScore(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $dataJson = $input->getOption('data');
        $requirementsJson = $input->getOption('requirements');

        if (!$dataJson) {
            $io->error('Please provide --data with candidate JSON array');
            return Command::FAILURE;
        }

        $data = json_decode($dataJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid JSON in --data: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        $params = ['candidate_data' => $dataJson];

        if ($requirementsJson) {
            $requirements = json_decode($requirementsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON in --requirements: ' . json_last_error_msg());
                return Command::FAILURE;
            }
            $params['requirements'] = $requirements;
        }

        $io->text('Scoring candidates...');
        $io->newLine();

        $result = $iris->tools->scoreCandidates($params);

        // JSON output
        if ($input->getOption('json')) {
            $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        // Formatted output
        $this->displayCandidateScoreResult($result, $output, $io);
        return Command::SUCCESS;
    }

    private function displayCandidateScoreResult($result, OutputInterface $output, SymfonyStyle $io): void
    {
        $io->success('Candidate Scoring Complete');

        // Summary
        if ($result->strongMatches) {
            $io->writeln('<fg=green>Strong Matches:</> ' . count($result->strongMatches));
        }
        if ($result->moderateMatches) {
            $io->writeln('<fg=yellow>Moderate Matches:</> ' . count($result->moderateMatches));
        }
        if ($result->weakMatches) {
            $io->writeln('<fg=gray>Weak Matches:</> ' . count($result->weakMatches));
        }

        // Ranked candidates
        if (!empty($result->rankedCandidates)) {
            $io->newLine();
            $io->section('Ranked Candidates');
            foreach ($result->rankedCandidates as $candidate) {
                $rank = $candidate['rank'] ?? '?';
                $name = $candidate['name'] ?? 'Unknown';
                $score = $candidate['overall_score'] ?? 0;
                $io->writeln("<fg=cyan>{$rank}.</> {$name} - <fg=green>{$score}%</>");
            }
        }

        // Report
        if ($result->report) {
            $io->newLine();
            $io->section('Analysis Report');
            $io->writeln($result->report);
        }
    }

    private function runLeadEnrich(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $leadId = $input->getOption('lead-id');
        $goal = $input->getOption('goal') ?: 'email';

        if (!$leadId) {
            $io->error('Please provide --lead-id');
            return Command::FAILURE;
        }

        $io->text("Enriching lead #{$leadId} (goal: {$goal})...");
        $io->newLine();

        $result = $iris->tools->enrichLead((int) $leadId, ['goal' => $goal]);

        // JSON output
        if ($input->getOption('json')) {
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::SUCCESS;
        }

        // Formatted output
        if (isset($result['success']) && $result['success']) {
            $io->success('Lead enriched successfully!');
            if (isset($result['data'])) {
                foreach ($result['data'] as $key => $value) {
                    $io->writeln("<fg=cyan>{$key}:</> {$value}");
                }
            }
        } else {
            $io->warning('Enrichment completed with limited results');
            if (isset($result['message'])) {
                $io->writeln($result['message']);
            }
        }

        return Command::SUCCESS;
    }

    private function runArticleGeneration(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $url = $input->getOption('url');
        $topic = $input->getOption('topic');
        $content = $input->getOption('content');
        $file = $input->getOption('file');
        $sourceType = $input->getOption('source-type') ?: 'video';
        $length = $input->getOption('length') ?: 'medium';
        $style = $input->getOption('style') ?: 'informative';
        $profileId = $input->getOption('profile-id');
        $edits = $input->getOption('edits');
        $draft = $input->getOption('draft');
        $publish = $input->getOption('publish');
        $noPublish = $input->getOption('no-publish');

        // Normalize source type aliases
        if (in_array($sourceType, ['research', 'notes'])) {
            $sourceType = 'research-notes';
        }

        // Warn if --edits provided with non-draft source type
        if ($edits && $sourceType !== 'draft') {
            $io->warning("--edits is only used with --source-type=draft. Your editing instructions will be ignored.");
            $io->text("To polish a draft with editing instructions, use:");
            $io->text("  ./bin/iris tools article --source-type=draft --content=\"...\" --edits=\"{$edits}\"");
            $io->newLine();
        }

        // Determine source based on source type
        $source = null;

        if (in_array($sourceType, ['research-notes', 'draft'])) {
            // For research-notes and draft, content or file is required
            if ($content) {
                $source = $content;
            } elseif ($file) {
                if (!file_exists($file)) {
                    $io->error("File not found: {$file}");
                    return Command::FAILURE;
                }
                $source = file_get_contents($file);
                $io->text("Reading from file: {$file}");
            } else {
                $io->error("Source type '{$sourceType}' requires --content or --file parameter");
                $io->text("Examples:");
                $io->text("  ./bin/iris tools article --source-type=research --content=\"AI trends: ...\"");
                $io->text("  ./bin/iris tools article --source-type=draft --file=/path/to/draft.md");
                return Command::FAILURE;
            }
        } else {
            // For video, topic, webpage, rss
            $source = $url ?: $topic;
            if (!$source) {
                $io->error('Please provide either --url (for video/webpage) or --topic (for research)');
                return Command::FAILURE;
            }

            // Determine source type based on input if not explicit
            if (!$url && $topic) {
                $sourceType = 'topic';
            }
        }

        // Build params
        $params = [
            'source_type' => $sourceType,
            'source' => $source,
            'content' => in_array($sourceType, ['research-notes', 'draft']) ? $source : null,
            'article_length' => $length,
            'article_style' => $style,
            'publish_to_fl' => $noPublish ? false : true, // Default to publish
            'article_status' => $draft ? 0 : 1, // 0=draft, 1=published
            'publish_to_social' => false,
        ];

        // Add editing instructions for draft mode
        if ($edits && $sourceType === 'draft') {
            $params['editing_instructions'] = $edits;
        }

        if ($profileId) {
            $params['profile_id'] = (int) $profileId;
        }

        // Determine status label for display
        $statusLabel = $noPublish ? 'No (test mode)' : ($draft ? 'Draft (unpublished)' : 'Published');

        $io->title('Article Generation');
        $io->writeln("<fg=cyan>Source Type:</> {$sourceType}");

        if (in_array($sourceType, ['research-notes', 'draft'])) {
            $io->writeln("<fg=cyan>Content Length:</> " . strlen($source) . " chars");
        } else {
            $io->writeln("<fg=cyan>Source:</> {$source}");
        }

        $io->writeln("<fg=cyan>Length:</> {$length}");
        $io->writeln("<fg=cyan>Style:</> {$style}");
        $io->writeln("<fg=cyan>Status:</> {$statusLabel}");

        if ($edits && $sourceType === 'draft') {
            $io->writeln("<fg=cyan>Editing Instructions:</> {$edits}");
        }

        $io->newLine();
        $io->text('Dispatching article generation job...');
        $io->newLine();

        try {
            $result = $iris->articles->generate($params);

            // JSON output
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Success output
            $io->success('Article generation job dispatched!');
            $io->writeln('<fg=yellow>The article is being generated in the background.</>');
            $io->newLine();
            $io->writeln('Job Details:');

            if (isset($result['message'])) {
                $io->writeln("  <fg=green>Message:</> {$result['message']}");
            }
            if (isset($result['queue'])) {
                $io->writeln("  <fg=cyan>Queue:</> {$result['queue']}");
            }
            if (isset($result['source_type'])) {
                $io->writeln("  <fg=cyan>Source Type:</> {$result['source_type']}");
            }

            $io->newLine();

            // Show different notes based on source type
            if ($sourceType === 'research-notes') {
                $io->writeln('<fg=gray>Note: Research notes article generation takes 1-2 minutes.</>');
            } elseif ($sourceType === 'draft') {
                $io->writeln('<fg=gray>Note: Draft polishing takes ~1 minute.</>');
            } else {
                $io->writeln('<fg=gray>Note: Article generation takes 1-3 minutes. Check your dashboard for the result.</>');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to dispatch article generation: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function runDemandPackage(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $caseId = $input->getOption('case-id');
        $aiModel = $input->getOption('ai-model') ?: 'gpt-5-nano';
        $uploadToGcs = !$input->getOption('no-publish'); // Default true unless no-publish
        $useCache = $input->getOption('use-cache');

        // Validate inputs
        if (!$caseId) {
            $io->error('Please provide --case-id (patient name or case number)');
            $io->text('Example: ./bin/iris tools demand-package --case-id="Richard Ramos"');
            return Command::FAILURE;
        }

        $io->section('Generating Legal Demand Package');
        $io->text([
            "Case ID: {$caseId}",
            "AI Model: {$aiModel}",
            "Upload to GCS: " . ($uploadToGcs ? 'Yes' : 'No'),
            "Use Cache: " . ($useCache ? 'Yes' : 'No'),
        ]);
        $io->newLine();
        $io->text('⏳ Generating demand package via ServisAI...');
        $io->newLine();

        $startTime = microtime(true);

        try {
            // Call ServisAI integration's create_demand_package function
            $result = $iris->integrations->execute('servis-ai', 'create_demand_package', [
                'case_id' => $caseId,
                'options' => [
                    'ai_model' => $aiModel,
                    'upload_to_gcs' => $uploadToGcs,
                    'use_cache' => $useCache,
                ],
            ]);

            $elapsedTime = round(microtime(true) - $startTime, 1);

            if ($input->getOption('json')) {
                $io->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Format output
            if (isset($result['success']) && $result['success']) {
                $io->success('Demand package generated successfully!');
                
                $io->section('Results');
                $io->definitionList(
                    ['Case ID' => $result['case_id'] ?? 'N/A'],
                    ['Output Type' => $result['output_type'] ?? 'demand_package'],
                    ['AI Model' => $result['ai_model'] ?? $aiModel],
                    ['Execution Time' => $elapsedTime . 's'],
                    ['Total Billing' => '$' . ($result['total_billing'] ?? '0.00')],
                );

                if (isset($result['gcs_url'])) {
                    $io->section('Download');
                    $io->writeln("📄 <href={$result['gcs_url']}>{$result['gcs_url']}</>");
                }

                if (isset($result['components'])) {
                    $io->section('Components Generated');
                    $components = [];
                    if ($result['components']['summary'] ?? false) $components[] = '✓ Case Summary';
                    if ($result['components']['chronology'] ?? false) $components[] = '✓ Medical Chronology';
                    if ($result['components']['patient_details'] ?? false) $components[] = '✓ Patient Details';
                    if ($result['components']['services'] ?? false) $components[] = '✓ Medical Services';
                    $io->listing($components);
                }

                if (isset($result['markdown']) && strlen($result['markdown']) > 0) {
                    $io->section('Preview (First 500 chars)');
                    $io->text(substr($result['markdown'], 0, 500) . '...');
                    $io->text("Full length: " . number_format(strlen($result['markdown'])) . ' characters');
                }

                return Command::SUCCESS;
            } else {
                $io->error('Demand package generation failed');
                if (isset($result['error'])) {
                    $io->text('Error: ' . $result['error']);
                }
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Failed to generate demand package: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function runYouTubeAudio(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $youtubeUrl = $input->getOption('url');
        $agentId = $input->getOption('agent-id') ?: 11;
        $outputFilename = $input->getOption('output-filename');

        // Validate inputs
        if (!$youtubeUrl) {
            $io->error('Please provide --url with a YouTube URL');
            $io->text('Example: ./bin/iris tools youtube-audio --url="https://www.youtube.com/watch?v=abc123"');
            return Command::FAILURE;
        }

        if (!preg_match('/youtube\.com|youtu\.be/', $youtubeUrl)) {
            $io->error('Invalid YouTube URL format');
            return Command::FAILURE;
        }

        $io->text("🎵 Downloading YouTube audio...");
        $io->text("URL: {$youtubeUrl}");
        $io->text("Agent ID: {$agentId}");
        $io->newLine();

        try {
            // Build params
            $params = [
                'youtube_url' => $youtubeUrl,
                'upload_to_gcs' => false, // Default to local storage
            ];

            if ($outputFilename) {
                $params['output_filename'] = $outputFilename;
            }

            // Call agent integration
            $result = $iris->agents->callIntegration($agentId, 'copycat-ai', 'download_youtube_audio', $params);

            // JSON output
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Formatted output
            if (isset($result['result']) && ($result['result']['success'] ?? false)) {
                $data = $result['result'];
                
                $io->success('YouTube audio downloaded successfully!');
                $io->newLine();
                
                $io->definitionList(
                    ['Title' => $data['title'] ?? 'N/A'],
                    ['Download URL' => $data['download_url'] ?? 'N/A'],
                    ['File Name' => $data['file_name'] ?? 'N/A'],
                    ['File Size' => ($data['file_size'] ?? 'N/A') . ' MB'],
                    ['Format' => $data['format'] ?? 'mp3'],
                    ['Quality' => $data['quality'] ?? '320kbps'],
                    ['Storage' => $data['storage_provider'] ?? 'local'],
                );

                $io->newLine();
                $io->text('🎧 You can access your file at:');
                $io->text('  • Web: ' . ($data['download_url'] ?? 'N/A'));
                $io->text('  • Local: fl-api/storage/app/public/' . ($data['file_name'] ?? ''));
                
                return Command::SUCCESS;
            } else {
                $io->error('YouTube audio download failed');
                if (isset($result['result']['error'])) {
                    $io->text('Error: ' . $result['result']['error']);
                }
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Failed to download YouTube audio: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function runNewsletterResearch(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $topic = $input->getOption('topic');
        $sourceUrl = $input->getOption('url');
        $audience = $input->getOption('audience');
        $tone = $input->getOption('tone') ?: 'professional';
        $length = $input->getOption('newsletter-length') ?: 'standard';
        $videos = $input->getOption('videos');
        $links = $input->getOption('links');

        // Validate inputs - need at least topic OR videos OR links
        if (!$topic && !$sourceUrl && !$videos && !$links) {
            $io->error('Please provide --topic, --url, --videos, or --links for newsletter research');
            $io->text([
                'Examples:',
                '  ./bin/iris tools newsletter-research --topic="AI trends 2025" --audience="tech professionals"',
                '',
                '  # Multi-modal ingestion with videos and links:',
                '  ./bin/iris tools newsletter-research --topic="graphic design" \\',
                '    --videos="https://www.youtube.com/watch?v=abc123,https://www.youtube.com/watch?v=xyz789" \\',
                '    --links="https://example.com/article1,https://example.com/article2"',
            ]);
            return Command::FAILURE;
        }

        // Parse video URLs
        $videoUrls = $videos ? array_filter(array_map('trim', preg_split('/[\n,]+/', $videos))) : [];
        $linkUrls = $links ? array_filter(array_map('trim', preg_split('/[\n,]+/', $links))) : [];

        $io->section('Newsletter Research');
        $io->text([
            "Topic: " . ($topic ?: 'From provided sources'),
            "Videos: " . (count($videoUrls) > 0 ? count($videoUrls) . ' video(s)' : 'None'),
            "Links: " . (count($linkUrls) > 0 ? count($linkUrls) . ' link(s)' : 'None'),
            "Source URL: " . ($sourceUrl ?: 'None'),
            "Audience: " . ($audience ?: 'General'),
            "Tone: {$tone}",
            "Length: {$length}",
        ]);

        // Show video URLs if provided
        if (count($videoUrls) > 0) {
            $io->newLine();
            $io->text('Videos for transcript extraction:');
            foreach ($videoUrls as $i => $url) {
                $io->text("  " . ($i + 1) . ". {$url}");
            }
        }

        // Show link URLs if provided
        if (count($linkUrls) > 0) {
            $io->newLine();
            $io->text('Links for content scraping:');
            foreach ($linkUrls as $i => $url) {
                $io->text("  " . ($i + 1) . ". {$url}");
            }
        }

        $io->newLine();
        $io->text('Researching topic and generating outline options...');
        if (count($videoUrls) > 0) {
            $io->text('<fg=yellow>Extracting video transcripts (this may take a moment)...</>');
        }
        if (count($linkUrls) > 0) {
            $io->text('<fg=yellow>Scraping web content...</>');
        }
        $io->newLine();

        try {
            $params = [
                'topic' => $topic ?: 'Content from provided sources',
                'tone' => $tone,
                'newsletter_length' => $length,
            ];

            if ($sourceUrl) {
                $params['source_url'] = $sourceUrl;
            }
            if ($audience) {
                $params['audience'] = $audience;
            }
            if ($videos) {
                $params['videos'] = $videos;
            }
            if ($links) {
                $params['links'] = $links;
            }

            $result = $iris->tools->newsletterResearch($params);

            // JSON output
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Formatted output
            if ($result->success) {
                $io->success('Research completed!');

                // Show sources used (if available)
                if (!empty($result->sourcesUsed)) {
                    $io->section('Sources Used');
                    $sources = $result->sourcesUsed;
                    $io->text([
                        "Video transcripts: " . ($sources['video_transcripts'] ?? 0),
                        "Web pages scraped: " . ($sources['web_pages_scraped'] ?? 0),
                        "Web search results: " . ($sources['web_search_results'] ?? 0),
                        "Total sources: " . ($sources['total_sources'] ?? 0),
                    ]);
                }

                // Show themes
                if (!empty($result->themes)) {
                    $io->section('Extracted Themes');
                    foreach ($result->themes as $i => $theme) {
                        $io->writeln("<fg=cyan>" . ($i + 1) . ". {$theme['name']}</>");
                        if (isset($theme['description'])) {
                            $io->writeln("   {$theme['description']}");
                        }
                    }
                }

                // Show outline options
                $io->section('Newsletter Outline Options');
                foreach ($result->outlineOptions as $option) {
                    $num = $option['option_number'] ?? '?';
                    $title = $option['title'] ?? 'Untitled';
                    $approach = $option['approach'] ?? '';
                    $readTime = $option['estimated_reading_time'] ?? '?';

                    $io->writeln("<fg=green>Option {$num}: {$title}</>");
                    $io->writeln("  Approach: {$approach}");
                    $io->writeln("  Reading time: {$readTime} min");

                    if (!empty($option['sections'])) {
                        $io->writeln("  Sections:");
                        foreach ($option['sections'] as $section) {
                            $io->writeln("    - {$section['name']}");
                        }
                    }
                    $io->newLine();
                }

                // Show next steps
                $io->section('Next Steps');
                $io->text([
                    'To generate the newsletter, run:',
                    '',
                    '  ./bin/iris tools newsletter-write \\',
                    '    --selected-option=2 \\',
                    "    --outline-json='" . json_encode($result->outlineOptions) . "' \\",
                    "    --context-json='" . json_encode($result->context) . "' \\",
                    '    --recipient-email="john@example.com"',
                    '',
                    'Or use --json flag to get the full data for programmatic use.',
                ]);

                return Command::SUCCESS;
            } else {
                $io->error('Newsletter research failed');
                if ($result->error) {
                    $io->text('Error: ' . $result->error);
                }
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Failed to research newsletter: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function runNewsletterWrite(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $selectedOption = $input->getOption('selected-option');
        $outlineJson = $input->getOption('outline-json');
        $contextJson = $input->getOption('context-json');
        $customization = $input->getOption('customization');
        $recipientEmail = $input->getOption('recipient-email');
        $recipientName = $input->getOption('recipient-name');
        $senderName = $input->getOption('sender-name');
        $leadId = $input->getOption('lead-id');

        // Validate inputs
        if (!$selectedOption || !$outlineJson || !$contextJson) {
            $io->error('Missing required options for newsletter-write');
            $io->text([
                'Required options:',
                '  --selected-option=N    Selected outline option (1, 2, or 3)',
                '  --outline-json=\'...\'   Outline options JSON from newsletter-research',
                '  --context-json=\'...\'   Context JSON from newsletter-research',
                '',
                'Tip: Run newsletter-research first, then use the JSON output.',
            ]);
            return Command::FAILURE;
        }

        // Parse JSON inputs
        $outlineOptions = json_decode($outlineJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid JSON in --outline-json: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        $context = json_decode($contextJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid JSON in --context-json: ' . json_last_error_msg());
            return Command::FAILURE;
        }

        $io->section('Newsletter Generation');
        $io->text([
            "Selected Option: {$selectedOption}",
            "Customization: " . ($customization ?: 'None'),
            "Recipient: " . ($recipientEmail ?: 'No email'),
        ]);
        $io->newLine();
        $io->text('Generating newsletter (this runs as a background job)...');
        $io->newLine();

        try {
            $params = [
                'selected_option' => (int) $selectedOption,
                'outline_options' => $outlineOptions,
                'context' => $context,
            ];

            if ($customization) {
                $params['customization_notes'] = $customization;
            }
            if ($recipientEmail) {
                $params['recipient_email'] = $recipientEmail;
            }
            if ($recipientName) {
                $params['recipient_name'] = $recipientName;
            }
            if ($senderName) {
                $params['sender_name'] = $senderName;
            }
            if ($leadId) {
                $params['lead_id'] = (int) $leadId;
            }

            $result = $iris->tools->newsletterWrite($params);

            // JSON output
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Formatted output
            if (isset($result['success']) && $result['success']) {
                $io->success('Newsletter generation job dispatched!');
                $io->writeln('<fg=yellow>The newsletter is being generated in the background.</>');
                $io->newLine();

                if (isset($result['job_id'])) {
                    $io->writeln("Job ID: {$result['job_id']}");
                }
                if (isset($result['message'])) {
                    $io->writeln("Message: {$result['message']}");
                }

                $io->newLine();
                $io->text('The newsletter will be saved to cloud storage and emailed (if recipient provided).');

                return Command::SUCCESS;
            } else {
                $io->error('Newsletter generation failed');
                if (isset($result['error'])) {
                    $io->text('Error: ' . $result['error']);
                }
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Failed to generate newsletter: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function runClipCut(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $youtubeUrl = $input->getOption('url');
        $startTime = $input->getOption('start-time');
        $duration = $input->getOption('duration');
        $agentId = (int) $input->getOption('agent-id');
        $publishSocial = $input->getOption('publish-social');
        $platforms = $input->getOption('platforms');
        $caption = $input->getOption('caption');

        // Validate required parameters
        if (!$youtubeUrl) {
            $io->error('YouTube URL is required. Use --url="https://www.youtube.com/watch?v=..."');
            return Command::FAILURE;
        }

        if (!$startTime) {
            $io->error('Start time is required. Use --start-time="0:10" (format: M:SS or MM:SS)');
            return Command::FAILURE;
        }

        if (!$duration) {
            $io->error('Duration is required. Use --duration="90s" (e.g., 30s, 60s, 90s)');
            return Command::FAILURE;
        }

        // Validate YouTube URL format
        if (!preg_match('/youtube\.com|youtu\.be/', $youtubeUrl)) {
            $io->error('Invalid YouTube URL format');
            return Command::FAILURE;
        }

        // Require social media publishing target
        if (!$publishSocial) {
            $io->error('Delivery target required. You must specify where to publish the clip.');
            $io->newLine();
            $io->text([
                'Add --publish-social with your target platforms:',
                '',
                '  ./bin/iris tools clip-cut \\',
                '    --url="' . $youtubeUrl . '" \\',
                '    --start-time="' . ($startTime ?: '0:10') . '" \\',
                '    --duration="' . ($duration ?: '60s') . '" \\',
                '    --publish-social \\',
                '    --platforms="instagram,tiktok,x"',
                '',
                'Supported platforms: instagram, tiktok, x, threads',
                '',
                'Note: Clips are also saved to your Cloud Files for dashboard access.',
            ]);
            return Command::FAILURE;
        }

        // Validate platforms are specified
        $platformList = array_map('trim', explode(',', $platforms));
        $validPlatforms = ['instagram', 'tiktok', 'x', 'twitter', 'threads'];
        $invalidPlatforms = array_diff($platformList, $validPlatforms);

        if (!empty($invalidPlatforms)) {
            $io->error('Invalid platform(s): ' . implode(', ', $invalidPlatforms));
            $io->text('Supported platforms: instagram, tiktok, x, threads');
            return Command::FAILURE;
        }

        try {
            $io->section('🎬 Cutting Video Clip');
            $displayInfo = [
                "YouTube URL: {$youtubeUrl}",
                "Start Time: {$startTime}",
                "Duration: {$duration}",
                "Agent ID: {$agentId}",
                "📤 Social Media: {$platforms}",
            ];

            if ($caption) {
                $displayInfo[] = "Caption: " . substr($caption, 0, 50) . (strlen($caption) > 50 ? '...' : '');
            } else {
                $displayInfo[] = "Caption: (auto-generated FREELABEL marketing caption)";
            }

            $displayInfo[] = "💾 Cloud Files: (auto-saved to dashboard)";

            $io->text($displayInfo);
            $io->newLine();

            // Build parameters - social media is now required
            $params = [
                'youtube_url' => $youtubeUrl,
                'start' => $startTime,
                'duration' => $duration,
                'publish_to_social' => true,
                'social_platforms' => $platformList,
            ];

            if ($caption) {
                $params['caption'] = $caption;
            }
            // If no caption, the API will auto-generate FREELABEL marketing caption

            $io->text('⏳ Calling Agent callIntegration...');
            $result = $iris->agents->callIntegration($agentId, 'copycat-ai', 'trigger_video_clipper', $params);

            // JSON output
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::SUCCESS;
            }

            // Formatted output
            if (isset($result['result']) && ($result['result']['success'] ?? false)) {
                $data = $result['result'];
                
                $io->success('Video clip process started successfully!');
                $io->newLine();
                
                // Display job/process information
                if (isset($data['message'])) {
                    $io->text('Status: ' . $data['message']);
                }
                
                if (isset($data['job_id'])) {
                    $io->text('Job ID: ' . $data['job_id']);
                }
                
                if (isset($data['video_url'])) {
                    $io->newLine();
                    $io->text('🎥 Once processed, your clip will be available at:');
                    $io->text('  • URL: ' . $data['video_url']);
                }
                
                if (isset($data['estimated_time'])) {
                    $io->newLine();
                    $io->text('⏱️  Estimated processing time: ' . $data['estimated_time']);
                }
                
                $io->newLine();
                $io->note('The video clipping process runs in the background. Check your CloudFiles for the finished clip.');
                
                return Command::SUCCESS;
            } else {
                $io->error('Video clip process failed to start');
                if (isset($result['result']['error'])) {
                    $io->text('Error: ' . $result['result']['error']);
                } elseif (isset($result['error'])) {
                    $io->text('Error: ' . $result['error']);
                }
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Failed to start video clip process: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function runBeatboxPublish(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $url = $input->getOption('beatbox-url');
        $start = $input->getOption('beatbox-start') ?: '0:10';
        $duration = $input->getOption('beatbox-duration') ?: '90s';
        $captionPrompt = $input->getOption('beatbox-caption-prompt');
        $platformsInput = $input->getOption('beatbox-platforms');
        $dryRun = $input->getOption('beatbox-dry-run');
        $dryRunDownload = $input->getOption('beatbox-dry-run-download');
        
        if (!$url) {
            $io->error('YouTube URL is required. Use --beatbox-url="https://www.youtube.com/watch?v=..."');
            return Command::FAILURE;
        }
        
        // Validate dry-run-download requires dry-run
        if ($dryRunDownload && !$dryRun) {
            $io->error('--beatbox-dry-run-download requires --beatbox-dry-run to be set');
            return Command::FAILURE;
        }
        
        // Parse platforms
        $platforms = null;
        if ($platformsInput) {
            $platforms = array_map('trim', explode(',', $platformsInput));
            $validPlatforms = ['instagram', 'tiktok', 'x'];
            $platforms = array_intersect($platforms, $validPlatforms);
            if (empty($platforms)) {
                $io->error('No valid platforms specified. Valid options: instagram, tiktok, x');
                return Command::FAILURE;
            }
        }
        
        $io->title('🎵 Beatbox Showcase Publisher' . ($dryRun ? ' (DRY RUN - Testing Mode)' : ''));
        
        if ($dryRun) {
            $io->text('🧪 DRY RUN MODE: Testing caption generation only');
            $io->text($dryRunDownload ? '🎵 ALSO downloading audio (--beatbox-dry-run-download)' : '⏭️  Skipping: audio download, database writes, Discord, social posts');
        } else {
            $io->text('Publishing beat to social media and FreeLabel...');
        }
        
        $io->text("URL: {$url}");
        $io->text("Start: {$start}, Duration: {$duration}");
        if ($platforms) {
            $io->text("Platforms: " . implode(', ', $platforms) . ($dryRun ? ' (will be skipped)' : ''));
        } else {
            $io->text("Platforms: instagram, tiktok, x (all)" . ($dryRun ? ' (will be skipped)' : ''));
        }
        $io->newLine();
        
        try {
            $params = [
                'youtube_url' => $url,
                'start' => $start,
                'duration' => $duration,
            ];
            
            if ($captionPrompt) {
                $params['caption_prompt'] = $captionPrompt;
            }
            
            if ($platforms) {
                $params['platforms'] = $platforms;
            }
            
            if ($dryRun) {
                $params['dry_run'] = true;
            }
            
            if ($dryRunDownload) {
                $params['dry_run_download'] = true;
            }
            
            $io->text('🔄 Starting publication process...');
            $result = $iris->integrations->execute('beatbox-showcase', 'beatbox_publish', $params);
            
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }
            
            if ($result['success'] ?? false) {
                // Operations are at root level in dry-run, nested under 'data' in normal mode
                $operations = $result['operations'] ?? $result['data']['operations'] ?? [];
                
                // DRY RUN MODE: Beautiful caption display
                if ($dryRun) {
                    $clipData = $operations['clip_cut']['data'] ?? [];
                    
                    $io->success('🧪 Dry Run Completed - Caption Generated!');
                    
                    // Display YouTube metadata
                    if (isset($clipData['metadata'])) {
                        $metadata = $clipData['metadata'];
                        $io->section('🎬 YouTube Video Information');
                        
                        // Parse duration from ISO 8601 format (PT3M8S)
                        $duration = 'N/A';
                        if (isset($metadata['duration'])) {
                            $durationStr = $metadata['duration'];
                            // Check if it's ISO 8601 format (PT3M8S) or numeric
                            if (is_string($durationStr) && strpos($durationStr, 'PT') === 0) {
                                // Parse ISO 8601 duration (PT3M8S)
                                preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $durationStr, $matches);
                                $hours = isset($matches[1]) ? (int)$matches[1] : 0;
                                $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
                                $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
                                $duration = ($hours > 0 ? $hours . ':' : '') . sprintf('%02d:%02d', $minutes, $seconds);
                            } elseif (is_numeric($durationStr)) {
                                $duration = gmdate("i:s", (int)$durationStr);
                            }
                        }
                        
                        $metadataTable = [
                            ['Field', 'Value'],
                            ['Title', $metadata['title'] ?? 'N/A'],
                            ['Channel', $metadata['channelTitle'] ?? $metadata['channel_name'] ?? $metadata['author'] ?? 'N/A'],
                            ['Duration', $duration],
                            ['Views', isset($metadata['viewCount']) ? number_format($metadata['viewCount']) : (isset($metadata['view_count']) ? number_format($metadata['view_count']) : 'N/A')],
                            ['Published', $metadata['publishedAt'] ?? ($metadata['upload_date'] ?? ($metadata['published_at'] ?? 'N/A'))],
                        ];
                        
                        $io->table($metadataTable[0], array_slice($metadataTable, 1));
                        
                        // Show thumbnail URL if available
                        $thumbnailUrl = null;
                        if (isset($metadata['thumbnails']['maxres']['url'])) {
                            $thumbnailUrl = $metadata['thumbnails']['maxres']['url'];
                        } elseif (isset($metadata['thumbnails']['high']['url'])) {
                            $thumbnailUrl = $metadata['thumbnails']['high']['url'];
                        } elseif (isset($metadata['thumbnail'])) {
                            $thumbnailUrl = $metadata['thumbnail'];
                        }
                        
                        if ($thumbnailUrl) {
                            $io->newLine();
                            $io->text('🖼️  Thumbnail: ' . $thumbnailUrl);
                        }
                        
                        // Show description preview if available
                        if (!empty($metadata['description'])) {
                            $io->newLine();
                            $io->text('📄 Description Preview:');
                            $descPreview = substr($metadata['description'], 0, 300);
                            if (strlen($metadata['description']) > 300) {
                                $descPreview .= '...';
                            }
                            $io->block(wordwrap($descPreview, 80), null, 'fg=gray', ' ', true);
                        }
                    }
                    
                    // Display caption
                    if (isset($clipData['caption'])) {
                        $io->newLine();
                        $io->section('📝 AI Generated Caption (Ready to Publish)');
                        $caption = $clipData['caption'];
                        
                        // Display caption in a colored box
                        $wrappedCaption = wordwrap($caption, 80, "\n");
                        $lines = explode("\n", $wrappedCaption);
                        $io->block($lines, null, 'fg=green;options=bold', ' ', true);
                        
                        $io->newLine();
                        $io->text([
                            '📊 Caption Stats:',
                            '  • Length: ' . strlen($caption) . ' characters',
                            '  • Lines: ' . (substr_count($caption, "\n") + 1),
                            '  • Words: ' . str_word_count($caption),
                            '  • Hashtags: ' . substr_count($caption, '#'),
                            '  • Mentions: ' . substr_count($caption, '@'),
                            '  • Emojis: ' . preg_match_all('/[\x{1F300}-\x{1F9FF}]/u', $caption),
                        ]);
                    } else {
                        $io->error('Caption generation failed: ' . ($operations['clip_cut']['error'] ?? 'Unknown error'));
                    }
                    
                    // Show audio download status if attempted
                    if ($dryRunDownload) {
                        $io->newLine();
                        $io->section('🎵 Audio Download Test');
                        if ($operations['audio_download']['status'] === 'success') {
                            $audioData = $operations['audio_download']['data'];
                            $io->text([
                                '✅ Audio downloaded successfully',
                                '  • GCS URL: ' . ($audioData['gcs_url'] ?? 'N/A'),
                                '  • File Size: ' . ($audioData['file_size_mb'] ?? 'N/A') . ' MB',
                                '  • Duration: ' . ($audioData['duration'] ?? 'N/A') . ' seconds',
                            ]);
                        } else {
                            $io->error('❌ Audio download failed: ' . ($operations['audio_download']['error'] ?? 'Unknown error'));
                        }
                    }
                    
                    // Show what was skipped
                    $io->newLine();
                    $io->section('⏭️  Skipped Operations (Dry Run Mode)');
                    $skippedOps = [
                        '• Social media posting (Instagram, TikTok, X)',
                        '• Database instrumental record creation',
                        '• Discord notifications',
                    ];
                    if (!$dryRunDownload) {
                        $skippedOps[] = '• Audio download (use --beatbox-dry-run-download to test)';
                    }
                    $io->listing($skippedOps);
                    
                    return Command::SUCCESS;
                }
                
                // NORMAL MODE: Standard output
                $io->success('Beat published successfully!');
                
                // Show operation statuses
                $io->section('Operation Results');
                foreach ($operations as $opName => $opData) {
                    $status = $opData['status'] ?? 'unknown';
                    $icon = match($status) {
                        'success' => '✅',
                        'partial' => '⚠️',
                        'failed' => '❌',
                        'skipped' => '⏭️',
                        default => '❓'
                    };
                    $io->text("{$icon} {$opName}: {$status}");
                }
                
                $io->newLine();
                
                // Display key results
                if (isset($operations['instrumental_create']['data']['instrumental_id'])) {
                    $io->text('🎼 Instrumental ID: ' . $operations['instrumental_create']['data']['instrumental_id']);
                }
                
                if (isset($operations['audio_download']['data']['gcs_url'])) {
                    $io->text('🔊 MP3 URL: ' . $operations['audio_download']['data']['gcs_url']);
                }
                
                if (isset($operations['clip_cut']['data']['social_post_urls'])) {
                    $socialUrls = $operations['clip_cut']['data']['social_post_urls'];
                    if (!empty($socialUrls)) {
                        $io->newLine();
                        $io->text('📱 Social Media Posts:');
                        foreach ($socialUrls as $platform => $url) {
                            $io->text("  • {$platform}: {$url}");
                        }
                    }
                }
                
                if (isset($operations['discord_notify']) && $operations['discord_notify']['status'] === 'success') {
                    $io->newLine();
                    $io->text('💬 Discord notification sent');
                }
                
                return Command::SUCCESS;
            } else {
                $io->error('Beat publication failed: ' . ($result['error'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Failed to publish beat: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function runBeatboxSubmit(IRIS $iris, InputInterface $input, OutputInterface $output, SymfonyStyle $io): int
    {
        $producerName = $input->getOption('producer-name');
        $email = $input->getOption('producer-email');
        $instagramHandle = $input->getOption('instagram-handle');
        $youtubeUrl = $input->getOption('beatbox-url');
        $beatTitle = $input->getOption('beat-title');
        $genre = $input->getOption('genre');
        $bpm = $input->getOption('bpm');
        $notes = $input->getOption('notes');
        
        // Validate required fields
        $missing = [];
        if (!$producerName) $missing[] = 'producer-name';
        if (!$email) $missing[] = 'producer-email';
        if (!$instagramHandle) $missing[] = 'instagram-handle';
        if (!$youtubeUrl) $missing[] = 'beatbox-url';
        if (!$beatTitle) $missing[] = 'beat-title';
        if (!$genre) $missing[] = 'genre';
        
        if (!empty($missing)) {
            $io->error('Missing required fields: ' . implode(', ', $missing));
            $io->text('Usage: ./bin/iris tools beatbox-submit --producer-name="DJ Fire" --producer-email="dj@example.com" --instagram-handle="@djfire" --beatbox-url="https://youtube.com/watch?v=xyz" --beat-title="Fire Beat" --genre="Trap"');
            return Command::FAILURE;
        }
        
        $io->title('🎤 Beatbox Producer Submission');
        $io->text('Submitting beat to Beatbox showcase...');
        $io->newLine();
        
        $io->definitionList(
            ['Producer' => $producerName],
            ['Email' => $email],
            ['Instagram' => $instagramHandle],
            ['Beat Title' => $beatTitle],
            ['Genre' => $genre],
            ['YouTube URL' => $youtubeUrl]
        );
        
        if ($bpm) {
            $io->text("BPM: {$bpm}");
        }
        if ($notes) {
            $io->text("Notes: {$notes}");
        }
        
        $io->newLine();
        
        try {
            $params = [
                'producer_name' => $producerName,
                'email' => $email,
                'instagram_handle' => $instagramHandle,
                'youtube_url' => $youtubeUrl,
                'beat_title' => $beatTitle,
                'genre' => $genre,
            ];
            
            if ($bpm) {
                $params['bpm'] = (int) $bpm;
            }
            
            if ($notes) {
                $params['notes'] = $notes;
            }
            
            $io->text('🔄 Processing submission...');
            $result = $iris->integrations->execute('beatbox-showcase', 'handle_submission', $params);
            
            if ($input->getOption('json')) {
                $output->writeln(json_encode($result, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }
            
            if ($result['success'] ?? false) {
                $io->success('Beat submission received successfully!');
                
                $data = $result['data'] ?? [];
                
                $io->section('Submission Details');
                if (isset($data['enrollment_id'])) {
                    $io->text('✅ Enrollment ID: ' . $data['enrollment_id']);
                }
                if (isset($data['bloq_id'])) {
                    $io->text('✅ Added to Bloq ID: ' . $data['bloq_id']);
                }
                if (isset($data['program_id'])) {
                    $io->text('✅ Program ID: ' . $data['program_id']);
                }
                
                $io->newLine();
                $io->text('📧 Confirmation email sent to ' . $email);
                $io->text('💬 Team notified via Discord');
                $io->text('👀 Your beat will be reviewed by our team');
                
                return Command::SUCCESS;
            } else {
                $io->error('Submission failed: ' . ($result['error'] ?? 'Unknown error'));
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Failed to submit beat: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function unknownTool(string $toolName, SymfonyStyle $io): int
    {
        $io->error("Unknown tool: {$toolName}");
        $io->text('Available tools: recruitment, candidate-score, lead-enrich, newsletter-research, newsletter-write, article, demand-package, youtube-audio, clip-cut, beatbox-publish, beatbox-submit');
        $io->text('Run "./bin/iris tools" to see all available tools with descriptions.');
        return Command::FAILURE;
    }
}
