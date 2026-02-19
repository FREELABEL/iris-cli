<?php

namespace IRIS\SDK\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use IRIS\SDK\IRIS;

/**
 * Servis.ai Integration Command
 *
 * Provides CLI tools for interacting with Servis.ai functions.
 * Uses dynamic proxy pattern - any Servis.ai function can be called.
 */
class ServisAiCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('servis-ai')
            ->setDescription('Interact with Servis.ai integration')
            ->setHelp(<<<'HELP'
Execute Servis.ai functions directly from the CLI.

<info>Available actions:</info>
  test                        Test the connection
  apps                        List all apps/entities
  case <caseId>               Get case details
  timeline <caseId>           Get case activity timeline (notes, calls)
  analyze <caseId>            Analyze case comprehensively
  fields [entity]             Get fields for entity (default: case_record)
  execute <function> [json]   Execute any function with JSON params

<info>Examples:</info>
  iris servis-ai test
  iris servis-ai apps
  iris servis-ai case CAS102377
  iris servis-ai timeline CAS102377
  iris servis-ai analyze CAS102377
  iris servis-ai fields case_record
  iris servis-ai execute list_activities '{"limit": 10}'
  iris servis-ai execute get_case_details '{"case_id": "CAS102377"}'

<info>Dynamic function calls:</info>
Any Servis.ai function can be called via the 'execute' action.
Function names use snake_case (e.g., list_apps, get_case_details).
HELP
            )
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: test, apps, case, analyze, fields, execute', 'test')
            ->addArgument('param1', InputArgument::OPTIONAL, 'First parameter (caseId, function name, or entity)')
            ->addArgument('param2', InputArgument::OPTIONAL, 'Second parameter (JSON params for execute)')
            ->addOption('api-key', null, InputOption::VALUE_REQUIRED, 'API key for authentication')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getArgument('action') ?? 'test';
        $jsonOutput = $input->getOption('json');

        try {
            // Initialize SDK
            $configOptions = [];
            if ($apiKey = $input->getOption('api-key')) {
                $configOptions['api_key'] = $apiKey;
            }
            if ($userId = $input->getOption('user-id')) {
                $configOptions['user_id'] = (int)$userId;
            }

            $iris = new IRIS($configOptions);

            // Route to appropriate handler
            switch ($action) {
                case 'test':
                    return $this->testConnection($iris, $io, $jsonOutput);
                case 'apps':
                    return $this->listApps($iris, $io, $jsonOutput);
                case 'case':
                    return $this->getCase($iris, $io, $input, $jsonOutput);
                case 'timeline':
                    return $this->getCaseTimeline($iris, $io, $input, $jsonOutput);
                case 'analyze':
                    return $this->analyzeCase($iris, $io, $input, $jsonOutput);
                case 'fields':
                    return $this->getFields($iris, $io, $input, $jsonOutput);
                case 'execute':
                case 'exec':
                    return $this->executeFunction($iris, $io, $input, $jsonOutput);
                default:
                    $io->error("Unknown action: {$action}");
                    $io->text("Available actions: test, apps, case, analyze, fields, execute");
                    return Command::FAILURE;
            }
        } catch (\Exception $e) {
            if ($jsonOutput) {
                $output->writeln(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_PRETTY_PRINT));
            } else {
                $io->error($e->getMessage());
                if ($output->isVerbose()) {
                    $io->text($e->getTraceAsString());
                }
            }
            return Command::FAILURE;
        }
    }

    private function testConnection(IRIS $iris, SymfonyStyle $io, bool $jsonOutput): int
    {
        $result = $iris->servisAi->test();

        if ($jsonOutput) {
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            return $result['success'] ? Command::SUCCESS : Command::FAILURE;
        }

        $io->title('Servis.ai Connection Test');

        if ($result['success']) {
            $io->success('Connected to Servis.ai');
            if (isset($result['app_count'])) {
                $io->text("Apps available: <info>{$result['app_count']}</info>");
            }
        } else {
            $io->error('Connection failed: ' . ($result['error'] ?? 'Unknown error'));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function listApps(IRIS $iris, SymfonyStyle $io, bool $jsonOutput): int
    {
        $apps = $iris->servisAi->apps();

        if ($jsonOutput) {
            echo json_encode($apps, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $io->title('Servis.ai Apps/Entities');

        if (empty($apps)) {
            $io->warning('No apps found');
            return Command::SUCCESS;
        }

        // Handle different response formats
        if (isset($apps['data'])) {
            $apps = $apps['data'];
        }

        if (is_array($apps) && isset($apps[0])) {
            $rows = [];
            foreach ($apps as $app) {
                $rows[] = [
                    $app['id'] ?? $app['name'] ?? 'N/A',
                    $app['label'] ?? $app['display_name'] ?? $app['name'] ?? 'N/A',
                    $app['type'] ?? 'N/A',
                ];
            }

            $io->table(['ID', 'Label', 'Type'], $rows);
        } else {
            // Display as key-value if it's a different structure
            $this->displayData($io, $apps);
        }

        $io->text(sprintf('Total: <info>%d</info> app(s)', count($apps)));

        return Command::SUCCESS;
    }

    private function getCase(IRIS $iris, SymfonyStyle $io, InputInterface $input, bool $jsonOutput): int
    {
        $caseId = $input->getArgument('param1');

        if (!$caseId) {
            $io->error('Case ID is required');
            $io->text('Usage: <info>iris servis-ai case CAS102377</info>');
            return Command::FAILURE;
        }

        $case = $iris->servisAi->case($caseId);

        if ($jsonOutput) {
            echo json_encode($case, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $io->title("Case: {$caseId}");

        if (isset($case['error'])) {
            $io->error($case['error']);
            return Command::FAILURE;
        }

        $this->displayData($io, $case);

        return Command::SUCCESS;
    }

    private function getCaseTimeline(IRIS $iris, SymfonyStyle $io, InputInterface $input, bool $jsonOutput): int
    {
        $caseId = $input->getArgument('param1');

        if (!$caseId) {
            $io->error('Case ID is required');
            $io->text('Usage: <info>iris servis-ai timeline CAS102377</info>');
            return Command::FAILURE;
        }

        $io->text("Getting timeline for case {$caseId}...");

        $result = $iris->servisAi->execute('get_case_timeline', ['case_id' => $caseId]);

        if ($jsonOutput) {
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $io->title("Timeline: {$caseId}");

        if (!($result['success'] ?? false)) {
            $io->error($result['error'] ?? 'Failed to get timeline');
            return Command::FAILURE;
        }

        $timeline = $result['timeline'] ?? [];

        if (empty($timeline)) {
            $io->warning('No activities found for this case');
            return Command::SUCCESS;
        }

        foreach ($timeline as $activity) {
            $type = strtoupper($activity['type'] ?? 'unknown');
            $date = $activity['formatted_date'] ?? $activity['created_at'] ?? 'Unknown';
            $by = $activity['created_by'] ?? 'Unknown';

            $io->newLine();
            $io->text("<comment>[{$type}]</comment> {$date} - <info>{$by}</info>");

            if ($activity['type'] === 'note' && !empty($activity['content'])) {
                $content = $activity['content'];
                if (strlen($content) > 200) {
                    $content = substr($content, 0, 200) . '...';
                }
                $io->text("  {$content}");
            } elseif ($activity['type'] === 'phone_call') {
                $status = $activity['status'] ?? 'Unknown';
                $to = $activity['to_number'] ?? '';
                $io->text("  Status: {$status}" . ($to ? " | To: {$to}" : ''));
                if (!empty($activity['note'])) {
                    $io->text("  Note: " . substr($activity['note'], 0, 150));
                }
            }
        }

        $io->newLine();
        $io->text(sprintf('Total: <info>%d</info> activities', count($timeline)));

        return Command::SUCCESS;
    }

    private function analyzeCase(IRIS $iris, SymfonyStyle $io, InputInterface $input, bool $jsonOutput): int
    {
        $caseId = $input->getArgument('param1');

        if (!$caseId) {
            $io->error('Case ID is required');
            $io->text('Usage: <info>iris servis-ai analyze CAS102377</info>');
            return Command::FAILURE;
        }

        $io->text("Analyzing case {$caseId}... (this may take a moment)");

        $analysis = $iris->servisAi->analyze($caseId);

        if ($jsonOutput) {
            echo json_encode($analysis, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $io->title("Case Analysis: {$caseId}");

        if (isset($analysis['error'])) {
            $io->error($analysis['error']);
            return Command::FAILURE;
        }

        $this->displayData($io, $analysis);

        return Command::SUCCESS;
    }

    private function getFields(IRIS $iris, SymfonyStyle $io, InputInterface $input, bool $jsonOutput): int
    {
        $entity = $input->getArgument('param1') ?? 'case_record';

        $fields = $iris->servisAi->fields($entity);

        if ($jsonOutput) {
            echo json_encode($fields, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $io->title("Fields for: {$entity}");

        if (isset($fields['error'])) {
            $io->error($fields['error']);
            return Command::FAILURE;
        }

        // Handle different response formats
        $fieldList = $fields['data'] ?? $fields['fields'] ?? $fields;

        if (is_array($fieldList) && !empty($fieldList)) {
            $rows = [];
            foreach ($fieldList as $key => $field) {
                if (is_array($field)) {
                    $rows[] = [
                        $field['name'] ?? $field['id'] ?? $key,
                        $field['label'] ?? $field['display_name'] ?? 'N/A',
                        $field['type'] ?? 'N/A',
                        isset($field['required']) ? ($field['required'] ? 'Yes' : 'No') : 'N/A',
                    ];
                } else {
                    $rows[] = [$key, $field, 'N/A', 'N/A'];
                }
            }

            $io->table(['Name', 'Label', 'Type', 'Required'], $rows);
            $io->text(sprintf('Total: <info>%d</info> field(s)', count($rows)));
        } else {
            $this->displayData($io, $fields);
        }

        return Command::SUCCESS;
    }

    private function executeFunction(IRIS $iris, SymfonyStyle $io, InputInterface $input, bool $jsonOutput): int
    {
        $function = $input->getArgument('param1');
        $paramsJson = $input->getArgument('param2');

        if (!$function) {
            $io->error('Function name is required');
            $io->text([
                'Usage: <info>iris servis-ai execute <function> [json_params]</info>',
                '',
                'Examples:',
                '  <info>iris servis-ai execute list_apps</info>',
                '  <info>iris servis-ai execute list_activities \'{"limit": 10}\'</info>',
                '  <info>iris servis-ai execute get_case_details \'{"case_id": "CAS102377"}\'</info>',
            ]);
            return Command::FAILURE;
        }

        // Parse JSON params
        $params = [];
        if ($paramsJson) {
            $params = json_decode($paramsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $io->error('Invalid JSON parameters: ' . json_last_error_msg());
                return Command::FAILURE;
            }
        }

        if (!$jsonOutput) {
            $io->text("Executing: <info>{$function}</info>");
            if (!empty($params)) {
                $io->text("Params: <comment>" . json_encode($params) . "</comment>");
            }
            $io->newLine();
        }

        $result = $iris->servisAi->execute($function, $params);

        if ($jsonOutput) {
            echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
            return Command::SUCCESS;
        }

        $io->title("Result: {$function}");

        if (isset($result['error'])) {
            $io->error($result['error']);
            return Command::FAILURE;
        }

        $this->displayData($io, $result);

        return Command::SUCCESS;
    }

    /**
     * Display data in a readable format
     */
    private function displayData(SymfonyStyle $io, array $data, int $indent = 0): void
    {
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    $io->text("{$prefix}<comment>{$key}:</comment>");
                    $this->displayData($io, $value, $indent + 1);
                } else {
                    // Simple list
                    $io->text("{$prefix}<comment>{$key}:</comment> [" . count($value) . " items]");
                    if (count($value) <= 5) {
                        foreach ($value as $i => $item) {
                            if (is_array($item)) {
                                $io->text("{$prefix}  [{$i}]:");
                                $this->displayData($io, $item, $indent + 2);
                            } else {
                                $io->text("{$prefix}  - " . $this->formatValue($item));
                            }
                        }
                    }
                }
            } else {
                $formattedValue = $this->formatValue($value);
                $io->text("{$prefix}<comment>{$key}:</comment> {$formattedValue}");
            }
        }
    }

    private function formatValue($value): string
    {
        if (is_bool($value)) {
            return $value ? '<info>true</info>' : '<error>false</error>';
        }
        if (is_null($value)) {
            return '<comment>null</comment>';
        }
        if (is_string($value) && strlen($value) > 100) {
            return substr($value, 0, 100) . '...';
        }
        return (string)$value;
    }

    private function isAssociativeArray(array $arr): bool
    {
        if (empty($arr)) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
